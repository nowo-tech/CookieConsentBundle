<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\DependencyInjection;

use Nowo\CookieConsentBundle\DependencyInjection\Configuration;
use Nowo\CookieConsentBundle\DependencyInjection\NowoCookieConsentExtension;
use Nowo\CookieConsentBundle\EventListener\CookieConsentConflictListener;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\WriteUnit\CommitLedger;
use Nowo\TransactionDoctrine\WriteUnit\Dbal\CommitTrackingMiddleware;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * 1.31 (REQ-RUNTIME-003 rule 6): `conflict_response` options and the wiring of the conflict answer.
 */
final class ConflictResponseConfigTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);

        self::assertSame(['status' => null, 'retry_after' => null, 'commit_tracking' => 'bundle'], $config['conflict_response']);
    }

    public function testTheStatusIs409Or503(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [['conflict_response' => ['status' => 503, 'retry_after' => 3]]]);
        self::assertSame(503, $config['conflict_response']['status']);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must be 409 or 503');
        (new Processor())->processConfiguration(new Configuration(), [['conflict_response' => ['status' => 500]]]);
    }

    public function testByDefaultTheListenerFollowsTransactionDoctrineAndTracksCommits(): void
    {
        $container = $this->load([]);

        self::assertSame('bundle', $container->getParameter(NowoCookieConsentExtension::COMMIT_TRACKING_PARAMETER));
        self::assertSame(CommitLedger::class, $container->getDefinition(NowoCookieConsentExtension::COMMIT_LEDGER_SERVICE)->getClass());
        $middleware = $container->getDefinition(NowoCookieConsentExtension::COMMIT_TRACKING_MIDDLEWARE);
        self::assertSame(CommitTrackingMiddleware::class, $middleware->getClass());
        self::assertTrue($middleware->hasTag('doctrine.middleware'));

        $listener = $container->getDefinition(CookieConsentConflictListener::class);
        $policy   = $listener->getArgument(0);
        self::assertInstanceOf(Reference::class, $policy);
        self::assertSame(ConflictResponsePolicy::class, (string) $policy);
        self::assertSame(NowoCookieConsentExtension::COMMIT_LEDGER_SERVICE, (string) $listener->getArgument(1));
        self::assertSame('doctrine', (string) $listener->getArgument(3));
        self::assertNull($listener->getArgument(4));
        $events = array_column($listener->getTag('kernel.event_listener'), 'event');
        self::assertSame([KernelEvents::REQUEST, KernelEvents::EXCEPTION], $events);
    }

    public function testHostOptionsBuildTheirOwnPolicyAndOffRegistersNoMiddleware(): void
    {
        $container = $this->load(['conflict_response' => ['status' => 503, 'commit_tracking' => 'off'], 'doctrine' => ['entity_manager' => 'consent']]);

        self::assertFalse($container->hasDefinition(NowoCookieConsentExtension::COMMIT_LEDGER_SERVICE));
        self::assertFalse($container->hasDefinition(NowoCookieConsentExtension::COMMIT_TRACKING_MIDDLEWARE));
        $listener = $container->getDefinition(CookieConsentConflictListener::class);
        $policy   = $listener->getArgument(0);
        self::assertInstanceOf(Definition::class, $policy);
        self::assertSame([503, ConflictResponsePolicy::DEFAULT_RETRY_AFTER], $policy->getArguments());
        self::assertNull($listener->getArgument(1));
        self::assertSame(NowoCookieConsentExtension::MANAGER_REGISTRY_SERVICE, (string) $listener->getArgument(3));
        self::assertSame('consent', $listener->getArgument(4));
    }

    public function testRetryAfterAloneKeeps409(): void
    {
        $policy = $this->load(['conflict_response' => ['retry_after' => 4]])->getDefinition(CookieConsentConflictListener::class)->getArgument(0);

        self::assertInstanceOf(Definition::class, $policy);
        self::assertSame([409, 4], $policy->getArguments());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new NowoCookieConsentExtension())->load([$config], $container);

        return $container;
    }
}
