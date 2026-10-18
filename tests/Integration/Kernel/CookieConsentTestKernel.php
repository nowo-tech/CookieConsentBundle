<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Configuration as DoctrineConfiguration;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\CookieConsentBundle\NowoCookieConsentBundle;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\BaseNode;
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

use const PHP_VERSION_ID;

/**
 * Minimal real application kernel: compiles the bundle exactly like a host app (with or without SecurityBundle).
 *
 * Cache layout: one root per PHPUnit process ({@see KernelCacheExtension} removes it when the run starts and ends)
 * and one directory per kernel configuration inside it, never deleted while the process runs. The dumped container
 * class name only depends on the kernel class/env/debug and its inner namespace on the generated code, so once PHP
 * has loaded a container class every later kernel with the same configuration reuses that class, whose __DIR__ (lazy
 * ghost proxy files on PHP < 8.4) points at the directory it was first dumped to. Deleting that directory between
 * tests breaks the reused container with "Failed opening required .../UriSignerGhost*.php".
 */
final class CookieConsentTestKernel extends Kernel
{
    use MicroKernelTrait;

    private readonly string $cacheKey;

    /**
     * @param array<string, mixed> $bundleConfig nowo_cookie_consent configuration
     * @param array<string, mixed> $hostParameters container parameters of the host app (e.g. app.locales)
     * @param array<string, mixed> $frameworkConfig extra framework configuration (e.g. enabled_locales)
     */
    public function __construct(
        private readonly bool $withSecurity,
        private readonly array $bundleConfig,
        private readonly array $hostParameters = [],
        private readonly array $frameworkConfig = [],
    ) {
        $this->cacheKey = hash('xxh128', serialize([$withSecurity, $bundleConfig, $hostParameters, $frameworkConfig]));
        parent::__construct('test', false);
    }

    /**
     * Cache root of the current PHPUnit process (removed before and after the run by {@see KernelCacheExtension}).
     */
    public static function processCacheRoot(): string
    {
        return sys_get_temp_dir() . '/nowo_cookie_consent_kernel/' . getmypid();
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
        return self::processCacheRoot() . '/' . $this->cacheKey . '/cache';
    }

    public function getLogDir(): string
    {
        return self::processCacheRoot() . '/' . $this->cacheKey . '/log';
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        foreach ($this->hostParameters as $name => $value) {
            $container->setParameter($name, $value);
        }
        // Env vars of the host (no process env mutation in tests): read through Symfony's env var loaders.
        $container->register(TestEnvVarLoader::class, TestEnvVarLoader::class)->addTag('container.env_var_loader');
        $container->loadFromExtension('framework', $this->frameworkConfig + [
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
            'orm'  => $this->ormConfig(),
        ]);
        if ($this->withSecurity) {
            $container->loadFromExtension('security', [
                'providers' => ['in_memory' => ['memory' => null]],
                'firewalls' => ['main' => ['lazy' => true]],
            ]);
        }
        $container->loadFromExtension('nowo_cookie_consent', $this->bundleConfig);
    }

    /**
     * ORM 3 on PHP 8.4+ needs either symfony/var-exporter < 8 (LazyGhost) or native lazy objects. DoctrineBundle 2.x
     * defaults "enable_native_lazy_objects" to false, so enable it there; 3.x always uses native lazy objects and
     * deprecates the option, so leave it unset.
     *
     * @return array<string, mixed>
     */
    private function ormConfig(): array
    {
        if (PHP_VERSION_ID < 80400) {
            return [];
        }

        $root = (new DoctrineConfiguration(false))->getConfigTreeBuilder()->buildTree();
        $orm  = $root instanceof ArrayNode ? ($root->getChildren()['orm'] ?? null) : null;
        $node = $orm instanceof ArrayNode ? ($orm->getChildren()['enable_native_lazy_objects'] ?? null) : null;

        return $node instanceof BaseNode && !$node->isDeprecated() ? ['enable_native_lazy_objects' => true] : [];
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__ . '/../../../src/Resources/config/routing.yaml');
    }
}
