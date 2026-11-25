<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\DependencyInjection\Compiler;

use LogicException;
use Nowo\CookieConsentBundle\Cookie\ConsentCookieWriterInterface;
use Nowo\CookieConsentBundle\Cookie\CookieHandler;
use Nowo\CookieConsentBundle\DependencyInjection\Compiler\ConsentCookieWriterPass;
use Nowo\CookieConsentBundle\Tests\Support\HostPorts\DecoratingConsentCookieWriter;
use Nowo\CookieConsentBundle\Tests\Support\HostPorts\HostConsentCookieWriter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * 2.0: a decorated or replaced `CookieHandler` service (the 1.x deprecated way) fails the build while the port is still
 * the bundle's writer; everything else passes.
 */
final class ConsentCookieWriterPassTest extends TestCase
{
    public function testWithoutThePortNothingHappens(): void
    {
        $container = new ContainerBuilder();
        $container->register(CookieHandler::class, DecoratingConsentCookieWriter::class);

        (new ConsentCookieWriterPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testTheBundleServicesPass(): void
    {
        (new ConsentCookieWriterPass())->process($this->container());

        $this->addToAssertionCount(1);
    }

    public function testAHostWriterOnThePortLeavesCookieHandlerAlone(): void
    {
        $container = $this->container();
        $container->register('host.writer', HostConsentCookieWriter::class);
        $container->setAlias(ConsentCookieWriterInterface::class, 'host.writer');
        $container->setAlias(CookieHandler::class, 'host.writer');

        (new ConsentCookieWriterPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testADecoratedCookieHandlerFailsTheBuild(): void
    {
        $container = $this->container();
        $container->register('host.handler', DecoratingConsentCookieWriter::class);
        $container->setAlias(CookieHandler::class, 'host.handler');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('service "host.handler", class "' . DecoratingConsentCookieWriter::class . '"');

        (new ConsentCookieWriterPass())->process($container);
    }

    public function testAReplacedCookieHandlerClassFailsTheBuild(): void
    {
        $container = $this->container();
        $container->getDefinition(CookieHandler::class)->setClass(HostConsentCookieWriter::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('decorate "nowo_cookie_consent.cookie_writer"');

        (new ConsentCookieWriterPass())->process($container);
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(CookieHandler::class, CookieHandler::class);
        $container->register(ConsentCookieWriterPass::WRITER_SERVICE_ID, CookieHandler::class);
        $container->setAlias(ConsentCookieWriterInterface::class, ConsentCookieWriterPass::WRITER_SERVICE_ID);

        return $container;
    }
}
