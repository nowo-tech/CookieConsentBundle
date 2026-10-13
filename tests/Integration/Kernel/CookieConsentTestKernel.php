<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\CookieConsentBundle\NowoCookieConsentBundle;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

use function sys_get_temp_dir;

/**
 * Minimal real application kernel: compiles the bundle exactly like a host app (with or without SecurityBundle).
 */
final class CookieConsentTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $bundleConfig nowo_cookie_consent configuration
     */
    public function __construct(
        private readonly bool $withSecurity,
        private readonly array $bundleConfig,
        private readonly string $cacheKey,
    ) {
        parent::__construct('test', false);
    }

    /**
     * @return iterable<BundleInterface>
     */
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new TwigExtraBundle();
        yield new DoctrineBundle();
        if ($this->withSecurity) {
            yield new SecurityBundle();
        }
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
        return sys_get_temp_dir() . '/nowo_cookie_consent_kernel/' . $this->cacheKey . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/nowo_cookie_consent_kernel/' . $this->cacheKey . '/log';
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
        $container->loadFromExtension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm'  => [],
        ]);
        if ($this->withSecurity) {
            $container->loadFromExtension('security', [
                'providers' => ['in_memory' => ['memory' => null]],
                'firewalls' => ['main' => ['lazy' => true]],
            ]);
        }
        $container->loadFromExtension('nowo_cookie_consent', $this->bundleConfig);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__ . '/../../../src/Resources/config/routing.yaml');
    }
}
