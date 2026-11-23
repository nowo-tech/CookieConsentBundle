<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Twig;

use Nowo\CookieConsentBundle\Config\ResolvedCookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use Nowo\CookieConsentBundle\Tests\Support\RecordingLogger;
use Nowo\CookieConsentBundle\Twig\CookieConsentTranslationTwigExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader as TwigArrayLoader;
use Twig\TwigFunction;

final class CookieConsentTranslationTwigExtensionTest extends TestCase
{
    public function testRegistersTransFunction(): void
    {
        $extension = new CookieConsentTranslationTwigExtension(new RequestStack(), $this->createTranslator());

        self::assertSame(['nowo_cookie_consent_trans', 'nowo_cookie_consent_privacy_route', 'nowo_cookie_consent_privacy_url'], array_map(
            static fn (TwigFunction $function): string => $function->getName(),
            $extension->getFunctions(),
        ));
    }

    public function testPrivacyRouteUsesTheConfigFallbackWhenTheTranslationIsEmpty(): void
    {
        self::assertNull((new CookieConsentTranslationTwigExtension(new RequestStack(), $this->createTranslator()))->privacyRoute());
        self::assertSame('legal_privacy', (new CookieConsentTranslationTwigExtension(new RequestStack(), $this->createTranslator(), 'legal_privacy'))->privacyRoute());
        self::assertNull((new CookieConsentTranslationTwigExtension(new RequestStack(), $this->createTranslator(), ''))->privacyRoute());
        self::assertSame('legal_privacy', (new CookieConsentTranslationTwigExtension(new RequestStack(), $this->createTranslator(['nowo_cookie_consent.privacy_route' => '']), 'legal_privacy'))->privacyRoute());
    }

    public function testPrivacyRouteTranslationWinsOverTheFallback(): void
    {
        $extension = new CookieConsentTranslationTwigExtension(new RequestStack(), $this->createTranslator(['nowo_cookie_consent.privacy_route' => 'app_privacy']), 'legal_privacy');

        self::assertSame('app_privacy', $extension->privacyRoute());
    }

    public function testPrivacyUrlGeneratesTheRoute(): void
    {
        $routes = new RouteCollection();
        $routes->add('app_privacy', new Route('/privacy'));
        $generator = new UrlGenerator($routes, new RequestContext());

        $extension = new CookieConsentTranslationTwigExtension(new RequestStack(), $this->createTranslator(['nowo_cookie_consent.privacy_route' => 'app_privacy']), null, $generator);
        self::assertSame('/privacy', $extension->privacyUrl());

        self::assertNull((new CookieConsentTranslationTwigExtension(new RequestStack(), $this->createTranslator(), null, $generator))->privacyUrl(), 'no route: no link');
        self::assertNull((new CookieConsentTranslationTwigExtension(new RequestStack(), $this->createTranslator(['nowo_cookie_consent.privacy_route' => 'app_privacy'])))->privacyUrl(), 'no router: no link');
    }

    /**
     * Audit ZAH-1: a route that does not exist (a URL stored as the route, a typo) or needs parameters renders the modal
     * without the link and logs a warning, instead of failing every page that shows the banner.
     */
    public function testPrivacyUrlOmitsTheLinkOfARouteThatCannotBeGenerated(): void
    {
        $routes = new RouteCollection();
        $routes->add('app_legal', new Route('/legal/{slug}'));
        $generator = new UrlGenerator($routes, new RequestContext());

        foreach (['https://example.com/privacy', 'missing_route', 'app_legal'] as $route) {
            $logger    = new RecordingLogger();
            $extension = new CookieConsentTranslationTwigExtension(new RequestStack(), $this->createTranslator(['nowo_cookie_consent.privacy_route' => $route]), null, $generator, $logger);

            self::assertNull($extension->privacyUrl(), $route);
            self::assertCount(1, $logger->records, $route);
            self::assertStringContainsString('cannot be generated', $logger->records[0]);
        }

        // Without a logger too.
        self::assertNull((new CookieConsentTranslationTwigExtension(new RequestStack(), $this->createTranslator(['nowo_cookie_consent.privacy_route' => 'missing_route']), null, $generator))->privacyUrl());
    }

    public function testTheModalTemplateRendersWithoutTheLinkOfABrokenRoute(): void
    {
        $generator = new UrlGenerator(new RouteCollection(), new RequestContext());
        $extension = new CookieConsentTranslationTwigExtension(new RequestStack(), $this->createTranslator(['nowo_cookie_consent.privacy_route' => '/privacy']), null, $generator, new RecordingLogger());
        $twig      = new Environment(new TwigArrayLoader(['t' => '{% set privacy_url = nowo_cookie_consent_privacy_url() %}{% if privacy_url %}<a href="{{ privacy_url }}">x</a>{% endif %}ok']));
        $twig->addExtension($extension);

        self::assertSame('ok', $twig->render('t'));
    }

    public function testFallsBackToTranslatorWithoutResolvedConfig(): void
    {
        $extension = new CookieConsentTranslationTwigExtension(new RequestStack(), $this->createTranslator());

        self::assertSame('YAML title', $extension->trans('nowo_cookie_consent.title'));
        self::assertSame('YAML title', $extension->trans('nowo_cookie_consent.title', 'not-a-config'));
    }

    public function testPrefersExplicitDisplayConfig(): void
    {
        $extension = new CookieConsentTranslationTwigExtension(new RequestStack(), $this->createTranslator());

        self::assertSame('Explicit', $extension->trans('nowo_cookie_consent.title', $this->createResolved('Explicit')));
    }

    public function testFallsBackToTranslatorForMessagesTheProfileDoesNotDefine(): void
    {
        $extension = new CookieConsentTranslationTwigExtension(new RequestStack(), $this->createTranslator());

        self::assertSame('YAML read more', $extension->trans('nowo_cookie_consent.read_more', $this->createResolved('DB')));
    }

    public function testReadsResolvedConfigFromMainRequestWhenSubRequestHasNone(): void
    {
        $stack = new RequestStack();
        $main  = Request::create('/');
        $main->attributes->set('nowo_cookie_consent_config', $this->createResolved('Main'));
        $stack->push($main);
        $stack->push(Request::create('/_fragment'));

        $extension = new CookieConsentTranslationTwigExtension($stack, $this->createTranslator());

        self::assertSame('Main', $extension->trans('nowo_cookie_consent.title'));
    }

    public function testConsecutiveRequestsDoNotLeakDatabaseTextsOrMutateTranslator(): void
    {
        $stack      = new RequestStack();
        $translator = $this->createTranslator();
        $extension  = new CookieConsentTranslationTwigExtension($stack, $translator);
        $twig       = new Environment(new TwigArrayLoader([
            'modal' => "{{ nowo_cookie_consent_trans('nowo_cookie_consent.title', display_config ?? null) }}",
        ]));
        $twig->addExtension($extension);

        $first = Request::create('/');
        $first->attributes->set('nowo_cookie_consent_config', $this->createResolved('Profile A'));
        $stack->push($first);

        self::assertSame('Profile A', $extension->trans('nowo_cookie_consent.title'));
        self::assertSame('Profile A', $twig->render('modal'));
        self::assertSame('Explicit B', $twig->render('modal', ['display_config' => $this->createResolved('Explicit B')]));

        $stack->pop();
        $stack->push(Request::create('/'));

        self::assertSame('YAML title', $extension->trans('nowo_cookie_consent.title'));
        self::assertSame('YAML title', $twig->render('modal'));
        self::assertSame('YAML title', $translator->trans('nowo_cookie_consent.title', [], 'NowoCookieConsentBundle'));
    }

    private function createResolved(string $title): ResolvedCookieConsentConfig
    {
        return new ResolvedCookieConsentConfig(
            new CookieConsentConfig(),
            (new CookieConsentConfigTranslation())
                ->setConsentModalTitle($title)
                ->setConsentModalDescription('Intro')
                ->setConsentModalAcceptAllBtn('All')
                ->setConsentModalAcceptNecessaryBtn('Necessary'),
        );
    }

    /**
     * @param array<string, string> $extra
     */
    private function createTranslator(array $extra = []): Translator
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', $extra + [
            'nowo_cookie_consent.title'     => 'YAML title',
            'nowo_cookie_consent.read_more' => 'YAML read more',
        ], 'en', CookieConsentTranslationTwigExtension::TRANSLATION_DOMAIN);

        return $translator;
    }
}
