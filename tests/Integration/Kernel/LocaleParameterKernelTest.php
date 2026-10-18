<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use Nowo\CookieConsentBundle\Locale\LocaleResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

use function implode;

/**
 * Real kernel: enabled_locales given as %app.locales%, %kernel.enabled_locales% or an env var (the host keeps one
 * locale list) must compile and reach the locale resolver and the nowo_cookie_consent_enabled_locales() Twig function
 * as the resolved list.
 */
final class LocaleParameterKernelTest extends TestCase
{
    private ?CookieConsentTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>}>
     */
    public static function provideLocaleConfigs(): iterable
    {
        yield 'app parameter' => [['enabled_locales' => '%app.locales%'], ['es', 'en', 'fr']];
        yield 'kernel.enabled_locales' => [['enabled_locales' => '%kernel.enabled_locales%'], ['de', 'en']];
        yield 'env var (json)' => [['enabled_locales' => '%env(json:' . TestEnvVarLoader::LOCALES_JSON . ')%'], ['pt', 'en']];
        yield 'env var (csv)' => [['enabled_locales' => '%env(csv:' . TestEnvVarLoader::LOCALES_CSV . ')%'], ['it', 'en']];
    }

    /**
     * @param array<string, mixed> $bundleConfig
     * @param list<string> $expectedLocales
     */
    #[DataProvider('provideLocaleConfigs')]
    public function testLocaleListFromParameterCompilesAndResolves(array $bundleConfig, array $expectedLocales): void
    {
        $this->kernel = new CookieConsentTestKernel(
            false,
            $bundleConfig + ['web_ui' => ['enabled' => false]],
            ['app.locales' => ['es', 'en', 'fr']],
            ['enabled_locales' => ['de', 'en']],
        );
        $this->kernel->boot();

        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        $resolver = $container->get(LocaleResolver::class);
        self::assertInstanceOf(LocaleResolver::class, $resolver);
        self::assertSame($expectedLocales, $resolver->getEnabledLocales());
        self::assertSame($expectedLocales[0], $resolver->resolve(Request::create('/?locale=' . $expectedLocales[0])));
        self::assertTrue($resolver->isEnabled('en'));
        self::assertFalse($resolver->isEnabled('zz'));

        $twig = $container->get('twig');
        self::assertInstanceOf(Environment::class, $twig);
        self::assertSame(
            implode(',', $expectedLocales),
            $twig->createTemplate('{{ nowo_cookie_consent_enabled_locales()|join(",") }}')->render(),
        );
    }
}
