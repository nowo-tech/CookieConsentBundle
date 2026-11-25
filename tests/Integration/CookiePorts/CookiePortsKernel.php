<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\CookiePorts;

use Nowo\CookieConsentBundle\Cookie\ConsentCookieWriterInterface;
use Nowo\CookieConsentBundle\Cookie\CookieHandler;
use Nowo\CookieConsentBundle\Cookie\CookieLoggerInterface;
use Nowo\CookieConsentBundle\NowoCookieConsentBundle;
use Nowo\CookieConsentBundle\Tests\Support\Host\HostConsentStore;
use Nowo\CookieConsentBundle\Tests\Support\HostPorts\DecoratingConsentCookieWriter;
use Nowo\CookieConsentBundle\Tests\Support\HostPorts\HostConsentCookieWriter;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

use function getmypid;
use function str_starts_with;
use function substr;
use function sys_get_temp_dir;

/**
 * A host (no Doctrine) that customises the consent cookie port: the supported ways (a host writer aliased to the port, a
 * decorator of `nowo_cookie_consent.cookie_writer`) and, since 2.0, the removed 1.x ways that must fail the build (a
 * decorator of `CookieHandler`, an alias of the removed `CookieHandlerInterface`, and the autodiscovered application
 * classes of `tests/Fixtures/RemovedPorts/<Case>`: mode `discover:<Case>`); audit MR-C-M1 / M2, lesson W1-1.
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

        if (str_starts_with($this->mode, 'discover:')) {
            $case = substr($this->mode, 9);
            (new PhpFileLoader($container, new FileLocator(__DIR__)))->registerClasses(
                (new Definition())->setAutowired(true)->setAutoconfigured(true),
                'Nowo\\CookieConsentBundle\\Tests\\Fixtures\\RemovedPorts\\' . $case . '\\',
                __DIR__ . '/../../Fixtures/RemovedPorts/' . $case . '/*',
            );

            return;
        }

        match ($this->mode) {
            'host_writer'     => $this->hostWriter($container),
            'decorate_writer' => $container->register('host.cookie_writer', DecoratingConsentCookieWriter::class)
                ->setDecoratedService('nowo_cookie_consent.cookie_writer')
                ->setArguments([new Reference('.inner')]),
            'decorate_handler' => $container->register('host.cookie_handler', DecoratingConsentCookieWriter::class)
                ->setDecoratedService(CookieHandler::class)
                ->setArguments([new Reference('.inner')]),
            'decorate_handler_with_host_writer' => $this->hostWriter($container)
                ->register('host.cookie_handler', DecoratingConsentCookieWriter::class)
                ->setDecoratedService(CookieHandler::class)
                ->setArguments([new Reference('.inner')]),
            'alias_removed_port' => $container->setAlias('Nowo\\CookieConsentBundle\\Cookie\\CookieHandlerInterface', CookieHandler::class),
            default              => null,
        };
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__ . '/../../../src/Resources/config/routing.yaml');
    }

    private function hostWriter(ContainerBuilder $container): ContainerBuilder
    {
        $container->register(HostConsentCookieWriter::class, HostConsentCookieWriter::class);
        $container->setAlias(ConsentCookieWriterInterface::class, HostConsentCookieWriter::class);

        return $container;
    }
}
