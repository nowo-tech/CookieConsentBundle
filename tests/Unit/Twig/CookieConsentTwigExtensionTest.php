<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Twig;

use Nowo\CookieConsentBundle\Config\CookieConsentRoutePatternMatcher;
use Nowo\CookieConsentBundle\Config\CookieConsentRouteTargeting;
use Nowo\CookieConsentBundle\Config\ResolvedCookieConsentConfig;
use Nowo\CookieConsentBundle\Cookie\CookieChecker;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use Nowo\CookieConsentBundle\Locale\LocaleResolver;
use Nowo\CookieConsentBundle\Twig\CookieConsentTwigExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CookieConsentTwigExtensionTest extends TestCase
{
    public function testOpenByDefaultWhenConsentMissing(): void
    {
        $extension = $this->createExtension($this->createChecker(false));

        self::assertSame('true', $extension->isCookieConsentOpenByDefault('home', ['privacy']));
    }

    public function testClosedOnDisabledRoute(): void
    {
        $extension = $this->createExtension($this->createChecker(false));

        self::assertSame('false', $extension->isCookieConsentOpenByDefault('privacy', ['privacy']));
    }

    public function testClosedWhenConsentAlreadySaved(): void
    {
        $extension = $this->createExtension($this->createChecker(true));

        self::assertSame('false', $extension->isCookieConsentOpenByDefault('home', []));
    }

    public function testClosedWhenDatabaseTargetingExcludesCurrentRoute(): void
    {
        $config = (new CookieConsentConfig())
            ->setAutoShowRouteMode(CookieConsentConfig::AUTO_SHOW_ROUTE_MODE_EXCEPT)
            ->setAutoShowRoutes(['admin']);

        $request = Request::create('/');
        $request->attributes->set('nowo_cookie_consent_config', new ResolvedCookieConsentConfig($config, null));

        $stack = new RequestStack();
        $stack->push($request);

        $extension = $this->createExtension($this->createChecker(false), $stack, true);

        self::assertSame('false', $extension->isCookieConsentOpenByDefault('admin', []));
        self::assertSame('true', $extension->isCookieConsentOpenByDefault('home', []));
    }

    public function testYamlOnlyModeWhenDatabaseConfigDisabled(): void
    {
        $extension = $this->createExtension(
            $this->createChecker(false),
            yamlMode: CookieConsentConfig::AUTO_SHOW_ROUTE_MODE_ONLY,
            yamlRoutes: ['home'],
        );

        self::assertSame('true', $extension->isCookieConsentOpenByDefault('home', []));
        self::assertSame('false', $extension->isCookieConsentOpenByDefault('admin', []));
    }

    public function testUsesMainRequestRouteWhenCookieConsentIsRenderedAsSubRequest(): void
    {
        $mainRequest = Request::create('/en/demo/admin/cookie-consent-config');
        $mainRequest->attributes->set('_route', 'demo_cookie_consent_config_index');

        $subRequest = Request::create('/cookie_consent_alt');
        $subRequest->attributes->set('_route', 'nowo_cookie_consent.show_if_not_set');

        $stack = new RequestStack();
        $stack->push($mainRequest);
        $stack->push($subRequest);

        $config = (new CookieConsentConfig())
            ->setAutoShowRouteMode(CookieConsentConfig::AUTO_SHOW_ROUTE_MODE_EXCEPT)
            ->setAutoShowRoutes(['demo_cookie_consent_config_index']);

        $mainRequest->attributes->set('nowo_cookie_consent_config', new ResolvedCookieConsentConfig($config, null));

        $extension = $this->createExtension($this->createChecker(false), $stack, true);

        self::assertSame(
            'false',
            $extension->isCookieConsentOpenByDefault('nowo_cookie_consent.show_if_not_set', []),
        );
    }

    public function testEnabledLocalesAndResolvedLocale(): void
    {
        $request = Request::create('/');
        $request->setLocale('es');

        $stack = new RequestStack();
        $stack->push($request);

        $extension = $this->createExtension($this->createChecker(false), $stack);

        self::assertSame(['en', 'es', 'it', 'fr'], $extension->getEnabledLocales());
        self::assertSame('es', $extension->getResolvedLocale());
    }

    public function testResolvedLocaleFallsBackToDefaultWithoutRequest(): void
    {
        $extension = $this->createExtension($this->createChecker(false), new RequestStack());

        self::assertSame('en', $extension->getResolvedLocale());
    }

    public function testSavedAndCategoryHelpers(): void
    {
        $request = Request::create('/');
        $request->cookies->set(CookieNameEnum::COOKIE_CONSENT_NAME, '1');
        $request->cookies->set(CookieNameEnum::getCookieCategoryName('analytics'), 'true');

        $stack = new RequestStack();
        $stack->push($request);

        $extension = $this->createExtension(new CookieChecker($stack), $stack);

        self::assertTrue($extension->isCookieConsentSavedByUser());
        self::assertTrue($extension->isCategoryAllowedByUser('analytics'));
        self::assertFalse($extension->isCategoryAllowedByUser('marketing'));
    }

    public function testClosedWhenAutoShowDisabledInDatabaseConfig(): void
    {
        $config  = (new CookieConsentConfig())->setAutoShow(false);
        $request = Request::create('/');
        $request->attributes->set('nowo_cookie_consent_config', new ResolvedCookieConsentConfig($config, null));

        $stack = new RequestStack();
        $stack->push($request);

        $extension = $this->createExtension($this->createChecker(false), $stack, true);

        self::assertSame('false', $extension->isCookieConsentOpenByDefault('home', []));
    }

    public function testGetFunctionsRegistersTwigCallbacks(): void
    {
        $extension = $this->createExtension($this->createChecker(false));
        $names     = array_map(static fn (\Twig\TwigFunction $function): string => $function->getName(), $extension->getFunctions());

        self::assertContains('nowo_cookie_consent_is_saved', $names);
        self::assertContains('nowo_cookie_consent_locale', $names);
    }

    /**
     * @param list<string> $yamlRoutes
     */
    private function createExtension(
        CookieChecker $checker,
        ?RequestStack $stack = null,
        bool $useDatabaseConfig = false,
        string $yamlMode = CookieConsentConfig::AUTO_SHOW_ROUTE_MODE_ALL,
        array $yamlRoutes = [],
    ): CookieConsentTwigExtension {
        $stack ??= new RequestStack();

        return new CookieConsentTwigExtension(
            $checker,
            new LocaleResolver(['en', 'es', 'it', 'fr'], 'en', true, $stack),
            $stack,
            new CookieConsentRouteTargeting(new CookieConsentRoutePatternMatcher()),
            $yamlMode,
            $yamlRoutes,
            $useDatabaseConfig,
        );
    }

    private function createChecker(bool $saved): CookieChecker
    {
        $request = Request::create('/');
        if ($saved) {
            $request->cookies->set(CookieNameEnum::COOKIE_CONSENT_NAME, date('r'));
        }

        $stack = new RequestStack();
        $stack->push($request);

        return new CookieChecker($stack);
    }
}
