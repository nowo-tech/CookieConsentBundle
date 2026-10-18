<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\DependencyInjection;

use Nowo\CookieConsentBundle\DependencyInjection\NowoCookieConsentExtension;
use Nowo\CookieConsentBundle\Locale\ConfiguredLocales;
use Nowo\CookieConsentBundle\Locale\LocaleResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * nowo_cookie_consent.enabled_locales as a literal list, a parameter or an env placeholder (direct load() callers).
 */
final class LocalesConfigTest extends TestCase
{
    public function testParameterStringIsResolvedInLoad(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('app.locales', ['es', 'en', 'es']);
        (new NowoCookieConsentExtension())->load([['enabled_locales' => '%app.locales%']], $container);

        self::assertSame(['es', 'en'], $container->getParameter('nowo_cookie_consent.enabled_locales'));
        self::assertSame('%nowo_cookie_consent.enabled_locales%', $container->getDefinition(LocaleResolver::class)->getArgument('$enabledLocales'));
    }

    public function testEnvPlaceholderIsResolvedAtRuntimeByAFactory(): void
    {
        $container = new ContainerBuilder();
        (new NowoCookieConsentExtension())->load([['enabled_locales' => '%env(json:APP_LOCALES)%']], $container);

        self::assertSame('%env(json:APP_LOCALES)%', $container->resolveEnvPlaceholders($container->getParameter('nowo_cookie_consent.enabled_locales')));
        $argument = $container->getDefinition(LocaleResolver::class)->getArgument('$enabledLocales');
        self::assertInstanceOf(Definition::class, $argument);
        self::assertSame([ConfiguredLocales::class, 'normalize'], $argument->getFactory());
        self::assertSame(['%nowo_cookie_consent.enabled_locales%'], $argument->getArguments());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideInvalidLocales(): iterable
    {
        yield 'nested list' => [['en', ['es']]];
        yield 'integer' => [42];
    }

    #[DataProvider('provideInvalidLocales')]
    public function testInvalidLocalesAreRejected(mixed $locales): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid nowo_cookie_consent.enabled_locales');

        (new NowoCookieConsentExtension())->load([['enabled_locales' => $locales]], new ContainerBuilder());
    }

    public function testPrependIgnoresParameterStringsOutsideTheWebUiNode(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'nowo_ui_kit';
            }
        });
        $container->registerExtension($extension = new NowoCookieConsentExtension());
        $container->loadFromExtension('nowo_cookie_consent', [
            'enabled_locales' => '%kernel.enabled_locales%',
            'target_routes'   => '%app.consent_routes%',
            'web_ui'          => ['icon_set' => 'none'],
        ]);

        $extension->prepend($container);

        self::assertSame([['icon_set' => 'none']], $container->getExtensionConfig('nowo_ui_kit'));
    }
}
