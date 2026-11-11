<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Ddd;

use Nowo\CookieConsentBundle\Cookie\ConsentCookieWriterInterface;
use Nowo\CookieConsentBundle\Cookie\CookieLoggerInterface;
use Nowo\CookieConsentBundle\NowoCookieConsentBundle;
use Nowo\CookieConsentBundle\Tests\Support\Host\HostConsentStore;
use Nowo\CookieConsentBundle\Tests\Support\HostPorts\HostConsentCookieWriter;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

use function getmypid;
use function hash;
use function serialize;
use function sys_get_temp_dir;

/**
 * A DDD host without Doctrine (DoctrineBundle not registered; doctrine/orm installed through the bundle's composer
 * requirements): YAML configuration and inventory, and the consent log aliased to the host's own store; optionally the
 * consent cookies written by the host's framework-free {@see ConsentCookieWriterInterface} (1.30).
 */
final class HostWithoutDoctrineKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $bundleConfig nowo_cookie_consent configuration
     * @param bool $hostLogger alias CookieLoggerInterface to {@see HostConsentStore}
     * @param bool $hostCookieWriter alias ConsentCookieWriterInterface to {@see HostConsentCookieWriter}
     */
    public function __construct(
        private readonly array $bundleConfig,
        private readonly bool $hostLogger = true,
        private readonly bool $hostCookieWriter = false,
    ) {
        parent::__construct('test', false);
    }

    public static function cacheRoot(): string
    {
        return sys_get_temp_dir() . '/nowo_cookie_consent_ddd/' . getmypid();
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
        return self::cacheRoot() . '/' . hash('xxh128', serialize([$this->bundleConfig, $this->hostLogger, $this->hostCookieWriter])) . '/cache';
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
        $container->loadFromExtension('nowo_cookie_consent', $this->bundleConfig);
        if ($this->hostLogger) {
            $container->register(HostConsentStore::class, HostConsentStore::class)->setPublic(true);
            $container->setAlias(CookieLoggerInterface::class, HostConsentStore::class)->setPublic(true);
        }
        if ($this->hostCookieWriter) {
            $container->register(HostConsentCookieWriter::class, HostConsentCookieWriter::class)->setPublic(true);
            $container->setAlias(ConsentCookieWriterInterface::class, HostConsentCookieWriter::class)->setPublic(true);
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__ . '/../../../src/Resources/config/routing.yaml');
    }
}
