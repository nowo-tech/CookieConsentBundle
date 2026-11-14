<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\CookieConsentBundle\Cookie\ConsentCookieWriterInterface;
use Nowo\CookieConsentBundle\Cookie\CookieHandler;
use Nowo\CookieConsentBundle\Cookie\CookieHandlerInterface;
use Nowo\CookieConsentBundle\DependencyInjection\Compiler\ConsentCookieWriterPass;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentFormSubscriber;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentOutdatedConsentSubscriber;
use Nowo\CookieConsentBundle\Tests\Support\HostPorts\LegacyCookieHandler;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use function restore_error_handler;
use function set_error_handler;

use const E_USER_DEPRECATED;

/**
 * Audit MR-C-M1 / M2 / L1 (1.30): the port is chosen once at container build.
 */
final class ConsentCookieWriterPassTest extends TestCase
{
    public function testWithoutThePortsNothingChanges(): void
    {
        $container = new ContainerBuilder();
        $container->register(CookieConsentOutdatedConsentSubscriber::class, CookieConsentOutdatedConsentSubscriber::class);

        (new ConsentCookieWriterPass())->process($container);

        self::assertSame([], $container->getDefinition(CookieConsentOutdatedConsentSubscriber::class)->getArguments());
    }

    public function testDefaultHandlerGetsTheBundleWriterAndAReplacedListenerIsLeftAlone(): void
    {
        $container = $this->container(CookieHandler::class);
        $container->register(CookieConsentFormSubscriber::class, stdClass::class);

        (new ConsentCookieWriterPass())->process($container);

        $writer = $container->getDefinition(CookieConsentOutdatedConsentSubscriber::class)->getArgument(2);
        self::assertInstanceOf(Reference::class, $writer);
        self::assertSame(ConsentCookieWriterPass::WRITER_SERVICE_ID, (string) $writer);
        self::assertSame([], $container->getDefinition(CookieConsentFormSubscriber::class)->getArguments());
    }

    public function testLegacyHandlerGetsNoWriterAndOneDeprecation(): void
    {
        $container    = $this->container(LegacyCookieHandler::class);
        $deprecations = [];
        // @phpstan-ignore frankenphp.worker.noSetErrorExceptionHandler (test only; restored below)
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, E_USER_DEPRECATED);

        try {
            (new ConsentCookieWriterPass())->process($container);
        } finally {
            restore_error_handler();
        }

        self::assertNull($container->getDefinition(CookieConsentOutdatedConsentSubscriber::class)->getArgument(2));
        self::assertCount(1, $deprecations);
        self::assertStringContainsString(LegacyCookieHandler::class, $deprecations[0]);
    }

    private function container(string $handlerClass): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('host.handler', $handlerClass);
        $container->setAlias(CookieHandlerInterface::class, 'host.handler');
        $container->register(ConsentCookieWriterPass::WRITER_SERVICE_ID, CookieHandler::class);
        $container->setAlias(ConsentCookieWriterInterface::class, ConsentCookieWriterPass::WRITER_SERVICE_ID);
        $container->register(CookieConsentOutdatedConsentSubscriber::class, CookieConsentOutdatedConsentSubscriber::class);

        return $container;
    }
}
