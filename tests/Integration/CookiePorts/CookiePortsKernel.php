<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\CookiePorts;

use Nowo\CookieConsentBundle\Cookie\ConsentCookieWriterInterface;
use Nowo\CookieConsentBundle\Cookie\CookieHandler;
use Nowo\CookieConsentBundle\Cookie\CookieHandlerInterface;
use Nowo\CookieConsentBundle\Cookie\CookieLoggerInterface;
use Nowo\CookieConsentBundle\NowoCookieConsentBundle;
use Nowo\CookieConsentBundle\Tests\Support\Host\HostConsentStore;
use Nowo\CookieConsentBundle\Tests\Support\HostPorts\DualCookieHandler;
use Nowo\CookieConsentBundle\Tests\Support\HostPorts\HostConsentCookieWriter;
use Nowo\CookieConsentBundle\Tests\Support\HostPorts\LegacyDecoratingCookieHandler;
use Nowo\CookieConsentBundle\Tests\Support\HostPorts\SubclassCookieHandler;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

use function getmypid;
use function sys_get_temp_dir;

/**
 * A host (no Doctrine) that customises the consent cookie ports the 1.29 ways (decorator on the concrete id or on the
 * interface, replaced class, alias to a subclass) or the 1.30 ways (dual handler, host writer); audit MR-C-M1 / M2.
 */
final class CookiePortsKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(
        private readonly string $mode,
    ) {
        parent::__construct('test', false);
    }

    public static function cacheRoot(): string
    {
        return sys_get_temp_dir() . '/nowo_cookie_consent_ports/' . getmypid();
    }

    /**
     * @return iterable<BundleInterface>
     */
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new TwigExtraBundle();
        yield new NowoUiKitBundle();
        yield new NowoFormKitBundle();
        yield new NowoCookieConsentBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return self::cacheRoot() . '/' . $this->mode . '/cache';
    }

    public function getLogDir(): string
    {
        return self::cacheRoot() . '/log';
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'secret'               => 'test',
            'test'                 => true,
            'http_method_override' => false,
            'router'               => ['utf8' => true],
            'csrf_protection'      => true,
            'form'                 => ['enabled' => true],
            'session'              => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);
        $container->loadFromExtension('twig', []);
        $container->loadFromExtension('nowo_cookie_consent', ['csrf_protection' => false]);
        $container->register(HostConsentStore::class, HostConsentStore::class)->setPublic(true);
        $container->setAlias(CookieLoggerInterface::class, HostConsentStore::class)->setPublic(true);

        match ($this->mode) {
            'decorate_concrete' => $container->register('host.cookie_handler', LegacyDecoratingCookieHandler::class)
                ->setDecoratedService(CookieHandler::class)
                ->setArguments([new Reference('.inner')]),
            'decorate_interface' => $container->register('host.cookie_handler', LegacyDecoratingCookieHandler::class)
                ->setDecoratedService(CookieHandlerInterface::class)
                ->setArguments([new Reference('.inner')]),
            'replace_subclass' => $container->addCompilerPass(new class implements CompilerPassInterface {
                public function process(ContainerBuilder $container): void
                {
                    $container->getDefinition(CookieHandler::class)->setClass(SubclassCookieHandler::class);
                }
            }),
            'alias_subclass' => $this->aliasSubclass($container),
            'dual'           => $this->dualHandler($container),
            'host_writer'    => $this->hostWriterOverLegacyDecorator($container),
            default          => null,
        };
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__ . '/../../../src/Resources/config/routing.yaml');
    }

    private function aliasSubclass(ContainerBuilder $container): void
    {
        $container->register(SubclassCookieHandler::class, SubclassCookieHandler::class)
            ->setArguments([false, new Reference(ClockInterface::class)]);
        $container->setAlias(CookieHandlerInterface::class, SubclassCookieHandler::class);
    }

    private function dualHandler(ContainerBuilder $container): void
    {
        $container->register(DualCookieHandler::class, DualCookieHandler::class);
        $container->setAlias(CookieHandlerInterface::class, DualCookieHandler::class);
    }

    private function hostWriterOverLegacyDecorator(ContainerBuilder $container): void
    {
        $container->register('host.cookie_handler', LegacyDecoratingCookieHandler::class)
            ->setDecoratedService(CookieHandler::class)
            ->setArguments([new Reference('.inner')]);
        $container->register(HostConsentCookieWriter::class, HostConsentCookieWriter::class);
        $container->setAlias(ConsentCookieWriterInterface::class, HostConsentCookieWriter::class);
    }
}
