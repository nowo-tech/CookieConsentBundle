<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\DependencyInjection;

use Nowo\CookieConsentBundle\DependencyInjection\NowoCookieConsentExtension;
use Nowo\CookieConsentBundle\DependencyInjection\TablePrefixListener;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentConfigTranslationSubscriber;
use Nowo\CookieConsentBundle\Security\AllowAllCookieConsentAccessChecker;
use Nowo\CookieConsentBundle\Security\ConfigurableCookieConsentAccessChecker;
use Nowo\CookieConsentBundle\Security\CookieConsentAccessCheckerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;

final class NowoCookieConsentExtensionTest extends TestCase
{
    public function testLoadsParametersWithoutTablePrefixListener(): void
    {
        $container = new ContainerBuilder();
        $extension = new NowoCookieConsentExtension();
        $extension->load([['table_prefix' => '']], $container);

        self::assertSame('', $container->getParameter('nowo_cookie_consent.table_prefix'));
        self::assertSame('en', $container->getParameter('nowo_cookie_consent.default_locale'));
        self::assertSame([], $container->getParameter('nowo_cookie_consent.skip_render_routes'));
        self::assertSame([], $container->getParameter('nowo_cookie_consent.render_routes'));
        self::assertSame(['en', 'es', 'it', 'fr', 'de', 'pt', 'nl', 'pl', 'ca'], $container->getParameter('nowo_cookie_consent.enabled_locales'));
        self::assertSame('bootstrap', $container->getParameter('nowo_cookie_consent.ui_theme'));
        self::assertFalse($container->hasDefinition(TablePrefixListener::class));
    }

    public function testRegistersTablePrefixListenerWhenDoctrinePrefixConfigured(): void
    {
        $container = new ContainerBuilder();
        $extension = new NowoCookieConsentExtension();
        $extension->load([['doctrine' => ['table_prefix' => 'demo_']]], $container);

        self::assertSame('demo_', $container->getParameter('nowo_cookie_consent.table_prefix'));
        self::assertSame('demo_', $container->getParameter('nowo_cookie_consent.doctrine.table_prefix'));
        self::assertTrue($container->hasDefinition(TablePrefixListener::class));
    }

    public function testDatabaseConfigRegistersArrayLoaderAndKeepsTranslationSubscriber(): void
    {
        $container = new ContainerBuilder();
        $extension = new NowoCookieConsentExtension();
        $extension->load([['use_database_config' => true]], $container);

        self::assertTrue($container->hasDefinition('nowo_cookie_consent.translation.loader.array'));
        self::assertTrue($container->hasDefinition(CookieConsentConfigTranslationSubscriber::class));
    }

    public function testDatabaseConfigDisabledRemovesTranslationSubscriber(): void
    {
        $container = new ContainerBuilder();
        $extension = new NowoCookieConsentExtension();
        $extension->load([['use_database_config' => false]], $container);

        self::assertFalse($container->hasDefinition(CookieConsentConfigTranslationSubscriber::class));
    }

    public function testLoadsWebUiAndSecurityDefaults(): void
    {
        $container = new ContainerBuilder();
        $extension = new NowoCookieConsentExtension();
        $extension->load([[]], $container);

        self::assertTrue($container->getParameter('nowo_cookie_consent.web_ui.enabled'));
        self::assertSame('/cookie-consent-config', $container->getParameter('nowo_cookie_consent.web_ui.path_prefix'));
        self::assertSame('@NowoCookieConsentBundle/admin/layout.html.twig', $container->getParameter('nowo_cookie_consent.web_ui.layout_template'));
        self::assertSame('bootstrap5', $container->getParameter('nowo_cookie_consent.web_ui.css_framework'));
        self::assertSame('bootstrap-icons', $container->getParameter('nowo_cookie_consent.web_ui.icon_set'));
        self::assertSame(20, $container->getParameter('nowo_cookie_consent.web_ui.list_page_size'));
        self::assertSame(['ROLE_ADMIN'], $container->getParameter('nowo_cookie_consent.security.access_roles'));
        self::assertFalse($container->getParameter('nowo_cookie_consent.security.allow_unauthenticated'));
        self::assertTrue($container->hasAlias(CookieConsentAccessCheckerInterface::class));
    }

    public function testUsesCustomAccessCheckerServiceId(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.custom_access_checker', new Definition(AllowAllCookieConsentAccessChecker::class));
        $extension = new NowoCookieConsentExtension();
        $extension->load([[
            'security' => [
                'access_checker' => 'app.custom_access_checker',
            ],
        ]], $container);

        self::assertTrue($container->hasAlias(CookieConsentAccessCheckerInterface::class));
        self::assertSame(
            'app.custom_access_checker',
            (string) $container->getAlias(CookieConsentAccessCheckerInterface::class),
        );
    }

    public function testAllowUnauthenticatedWithoutSecurityUsesAllowAllChecker(): void
    {
        $container = new ContainerBuilder();
        $extension = new NowoCookieConsentExtension();
        $extension->load([[
            'security' => [
                'allow_unauthenticated' => true,
            ],
        ]], $container);

        self::assertTrue($container->hasDefinition('nowo_cookie_consent.access_checker.allow_all'));
        self::assertSame(
            AllowAllCookieConsentAccessChecker::class,
            $container->getDefinition('nowo_cookie_consent.access_checker.allow_all')->getClass(),
        );
        self::assertSame(
            'nowo_cookie_consent.access_checker.allow_all',
            (string) $container->getAlias(CookieConsentAccessCheckerInterface::class),
        );
    }

    public function testDefaultAccessCheckerWiresAuthorizationCheckerWhenPresent(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('security.authorization_checker', new Definition());
        $extension = new NowoCookieConsentExtension();
        $extension->load([[]], $container);

        $definition = $container->getDefinition('nowo_cookie_consent.access_checker.default');
        self::assertSame(ConfigurableCookieConsentAccessChecker::class, $definition->getClass());
        self::assertArrayHasKey('$authorizationChecker', $definition->getArguments());
    }

    public function testGetAlias(): void
    {
        self::assertSame('nowo_cookie_consent', (new NowoCookieConsentExtension())->getAlias());
    }

    public function testPrependConfiguresAssets(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FrameworkExtension());
        (new NowoCookieConsentExtension())->prepend($container);
        $configs = $container->getExtensionConfig('framework');
        self::assertSame('/bundles/nowocookieconsent', $configs[0]['assets']['packages']['nowo_cookie_consent']['base_path']);
    }

    public function testPrependSkipsWithoutFramework(): void
    {
        $container = new ContainerBuilder();
        (new NowoCookieConsentExtension())->prepend($container);
        self::assertFalse($container->hasExtension('framework'));
    }

    public function testPrependAlignsUiKitDefaultsFromWebUiWhenHostUnset(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'nowo_ui_kit';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });
        $container->prependExtensionConfig('nowo_cookie_consent', [
            'web_ui' => [
                'css_framework' => 'tailwind',
                'icon_set'      => 'tabler-icons',
            ],
        ]);

        (new NowoCookieConsentExtension())->prepend($container);

        $found = false;
        foreach ($container->getExtensionConfig('nowo_ui_kit') as $cfg) {
            if (($cfg['css_framework'] ?? null) === 'tailwind'
                && ($cfg['icon_set'] ?? null) === 'tabler-icons'
            ) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Expected nowo_ui_kit defaults from web_ui config.');
    }

    public function testPrependNormalizesBootstrapAliasForUiKit(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'nowo_ui_kit';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });
        $container->prependExtensionConfig('nowo_cookie_consent', [
            'web_ui' => [
                'css_framework' => 'bootstrap',
            ],
        ]);

        (new NowoCookieConsentExtension())->prepend($container);

        $found = false;
        foreach ($container->getExtensionConfig('nowo_ui_kit') as $cfg) {
            if (($cfg['css_framework'] ?? null) === 'bootstrap5') {
                $found = true;
                break;
            }
        }
        self::assertTrue($found);
    }

    public function testPrependDoesNotOverrideExplicitUiKitHostConfig(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'nowo_ui_kit';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });
        $container->prependExtensionConfig('nowo_ui_kit', [
            'css_framework' => 'custom',
            'icon_set'      => 'ux_icon',
        ]);
        $container->prependExtensionConfig('nowo_cookie_consent', [
            'web_ui' => [
                'css_framework' => 'tailwind',
                'icon_set'      => 'tabler-icons',
            ],
        ]);

        (new NowoCookieConsentExtension())->prepend($container);

        foreach ($container->getExtensionConfig('nowo_ui_kit') as $cfg) {
            if (($cfg['css_framework'] ?? null) === 'tailwind'
                || ($cfg['icon_set'] ?? null) === 'tabler-icons'
            ) {
                self::fail('web_ui must not prepend UiKit defaults when host set nowo_ui_kit explicitly.');
            }
        }
        $uiKitConfigs = $container->getExtensionConfig('nowo_ui_kit');
        self::assertSame('custom', $uiKitConfigs[0]['css_framework'] ?? null);
        self::assertSame('ux_icon', $uiKitConfigs[0]['icon_set'] ?? null);
    }

    public function testPrependSkipsUiKitWhenExtensionMissing(): void
    {
        $container = new ContainerBuilder();
        (new NowoCookieConsentExtension())->prepend($container);

        self::assertSame([], $container->getExtensionConfig('nowo_ui_kit'));
    }

    public function testPrependAlignsFormKitDefaultsWhenHostUnset(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'nowo_form_kit';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });

        (new NowoCookieConsentExtension())->prepend($container);

        $foundCss     = false;
        $foundProfile = false;
        foreach ($container->getExtensionConfig('nowo_form_kit') as $cfg) {
            if (($cfg['css_framework'] ?? null) === 'bootstrap') {
                $foundCss = true;
            }
            if (isset($cfg['profiles']['cookie_consent'])) {
                $foundProfile = true;
            }
        }
        self::assertTrue($foundCss);
        self::assertTrue($foundProfile);
    }

    public function testPrependDoesNotOverrideExplicitFormKitHostConfig(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'nowo_form_kit';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });
        $container->prependExtensionConfig('nowo_form_kit', [
            'css_framework' => 'tailwind',
            'profiles'      => [
                'cookie_consent' => [
                    'alias' => 'cookie_consent',
                ],
            ],
        ]);

        (new NowoCookieConsentExtension())->prepend($container);

        foreach ($container->getExtensionConfig('nowo_form_kit') as $cfg) {
            if (($cfg['css_framework'] ?? null) === 'bootstrap') {
                self::fail('Must not prepend FormKit css_framework when host already set it.');
            }
            if (isset($cfg['profiles']['cookie_consent']['translation_domain'])) {
                self::fail('Must not prepend FormKit cookie_consent profile when host already set it.');
            }
        }
        $configs = $container->getExtensionConfig('nowo_form_kit');
        self::assertSame('tailwind', $configs[0]['css_framework'] ?? null);
    }

    public function testPrependSkipsFormKitWhenExtensionMissing(): void
    {
        $container = new ContainerBuilder();
        (new NowoCookieConsentExtension())->prepend($container);

        self::assertSame([], $container->getExtensionConfig('nowo_form_kit'));
    }
}
