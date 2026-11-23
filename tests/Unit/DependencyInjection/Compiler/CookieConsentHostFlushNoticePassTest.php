<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\CookieConsentBundle\Controller\CookieDefinitionAdminController;
use Nowo\CookieConsentBundle\DependencyInjection\Compiler\CookieConsentHostFlushNoticePass;
use Nowo\CookieConsentBundle\DependencyInjection\NowoCookieConsentExtension;
use Nowo\CookieConsentBundle\Doctrine\CookieConsentHostFlushReporter;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentPrivateManagerBoundarySubscriber;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use Nowo\CookieConsentBundle\Tests\Integration\Kernel\HostOverride\HostCookieDefinitionAdminController;
use Nowo\CookieConsentBundle\Tests\Integration\Kernel\HostOverride\LegacyConfigRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use function array_map;
use function restore_error_handler;
use function set_error_handler;
use function strval;

use const E_USER_DEPRECATED;

/**
 * 1.35: the `doctrine.private_entity_manager` notice and the host-flush reporter exist exactly when the bundle writes
 * through the application's default manager.
 */
#[CoversClass(CookieConsentHostFlushNoticePass::class)]
#[CoversClass(NowoCookieConsentExtension::class)]
final class CookieConsentHostFlushNoticePassTest extends TestCase
{
    /**
     * @return iterable<string, array{0: bool|null, 1: string|null, 2: string|null, 3: string|null}>
     */
    public static function settings(): iterable
    {
        // [private_entity_manager, entity_manager, doctrine.default_entity_manager, expected notice]
        yield 'unset, no entity manager' => [null, null, null, NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_UNSET_DEPRECATION];
        yield 'false, no entity manager' => [false, null, 'default', NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_FALSE_DEPRECATION];
        yield 'unset, entity_manager: default' => [null, 'default', 'default', NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_UNSET_DEPRECATION];
        yield 'unset, entity_manager: default (no param)' => [null, 'default', null, NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_UNSET_DEPRECATION];
        yield 'false, entity_manager names the default' => [false, 'main', 'main', NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_FALSE_DEPRECATION];
        yield 'unset, dedicated entity manager (DDD)' => [null, 'cookie_consent', 'default', null];
        yield 'false, dedicated entity manager (DDD)' => [false, 'cookie_consent', 'main', null];
        yield 'true, no entity manager' => [true, null, 'default', null];
        yield 'true, entity_manager: default' => [true, 'default', 'default', null];
    }

    #[DataProvider('settings')]
    public function testTheNoticeAndTheReporterFollowTheSetting(?bool $private, ?string $entityManager, ?string $default, ?string $expected): void
    {
        $container = $this->container($private, $entityManager, $default);

        $deprecations = $this->process($container);

        if ($expected === null) {
            self::assertSame([], $deprecations);
            self::assertFalse($container->hasDefinition(CookieConsentHostFlushReporter::class));
            self::assertFalse($container->hasDefinition(CookieConsentHostFlushNoticePass::BOUNDARY_SUBSCRIBER_SERVICE));
            self::assertSame([], $container->getDefinition(CookieConsentConfigRepository::class)->getArguments());

            return;
        }

        self::assertSame(['Since nowo-tech/cookie-consent-bundle 1.35: ' . $expected], $deprecations);
        self::assertStringContainsString('2.0 always writes them through a private entity manager', $expected);
        self::assertMatchesRegularExpression('/private_entity_manager: true|Set it to true now/', $expected, 'it says how to opt in');

        $reporter = $container->getDefinition(CookieConsentHostFlushReporter::class);
        self::assertTrue($reporter->hasTag('kernel.reset'));
        foreach ([CookieConsentConfigRepository::class, CookieDefinitionRepository::class, CookieDefinitionAdminController::class] as $id) {
            $argument = $container->getDefinition($id)->getArgument('$hostFlushReporter');
            self::assertInstanceOf(Reference::class, $argument);
            self::assertSame(CookieConsentHostFlushReporter::class, (string) $argument);
        }

        $boundary = $container->getDefinition(CookieConsentHostFlushNoticePass::BOUNDARY_SUBSCRIBER_SERVICE);
        self::assertSame(CookieConsentPrivateManagerBoundarySubscriber::class, $boundary->getClass());
        self::assertTrue($boundary->hasTag('kernel.event_subscriber'));
        $resettables = $boundary->getArgument(0);
        self::assertInstanceOf(IteratorArgument::class, $resettables);
        self::assertSame([CookieConsentHostFlushReporter::class], array_map(strval(...), $resettables->getValues()));
    }

    public function testWithoutDoctrineNothingHappens(): void
    {
        $container = $this->container(null, null, null);
        $container->getParameterBag()->remove(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_SETTING_PARAMETER);

        self::assertSame([], $this->process($container));
        self::assertFalse($container->hasDefinition(CookieConsentHostFlushReporter::class));
    }

    public function testWithoutTheEntityManagerParameterTheDefaultManagerIsAssumed(): void
    {
        $container = $this->container(null, null, null);
        $container->getParameterBag()->remove(NowoCookieConsentExtension::ENTITY_MANAGER_PARAMETER);

        self::assertCount(1, $this->process($container));
        self::assertTrue($container->hasDefinition(CookieConsentHostFlushReporter::class));
    }

    /** Audit M1: a host class whose constructor has no `$hostFlushReporter` (or an unknown class) is left as configured. */
    public function testAHostReplacementWithoutTheArgumentIsLeftAlone(): void
    {
        $container = $this->container(null, null, null);
        $container->getDefinition(CookieConsentConfigRepository::class)->setClass(LegacyConfigRepository::class);
        $container->getDefinition(CookieDefinitionRepository::class)->setClass('App\\Missing\\Repository');
        $container->getDefinition(CookieDefinitionAdminController::class)->setClass(HostCookieDefinitionAdminController::class);

        self::assertCount(1, $this->process($container), 'the notice is still triggered');
        self::assertTrue($container->hasDefinition(CookieConsentHostFlushReporter::class));
        foreach ([CookieConsentConfigRepository::class, CookieDefinitionRepository::class, CookieDefinitionAdminController::class] as $id) {
            self::assertSame([], $container->getDefinition($id)->getArguments(), $id);
        }
    }

    public function testTheReporterIsWiredOnlyIntoTheServicesThatExist(): void
    {
        $container = $this->container(null, null, null);
        $container->removeDefinition(CookieDefinitionAdminController::class);

        self::assertCount(1, $this->process($container));
        self::assertTrue($container->hasDefinition(CookieConsentHostFlushReporter::class));
        self::assertFalse($container->hasDefinition(CookieDefinitionAdminController::class));
    }

    private function container(?bool $private, ?string $entityManager, ?string $default): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_SETTING_PARAMETER, $private);
        $container->setParameter(NowoCookieConsentExtension::ENTITY_MANAGER_PARAMETER, $entityManager);
        if ($default !== null) {
            $container->setParameter('doctrine.default_entity_manager', $default);
        }
        foreach ([CookieConsentConfigRepository::class, CookieDefinitionRepository::class, CookieDefinitionAdminController::class] as $id) {
            $container->register($id, $id);
        }

        return $container;
    }

    /**
     * @return list<string> the deprecations the pass triggered
     */
    private function process(ContainerBuilder $container): array
    {
        $deprecations = [];
        // @phpstan-ignore frankenphp.worker.noSetErrorExceptionHandler (test only; restored below)
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, E_USER_DEPRECATED);

        try {
            (new CookieConsentHostFlushNoticePass())->process($container);
        } finally {
            restore_error_handler();
        }

        return $deprecations;
    }
}
