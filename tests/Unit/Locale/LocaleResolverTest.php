<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Locale;

use Nowo\CookieConsentBundle\Locale\LocaleResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class LocaleResolverTest extends TestCase
{
    private LocaleResolver $resolver;
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->resolver     = new LocaleResolver(['en', 'es', 'it', 'fr', 'de', 'pt', 'nl', 'pl', 'ca'], 'en', true, $this->requestStack);
    }

    public function testResolveUsesQueryLocaleFirst(): void
    {
        $request = Request::create('/', 'GET', ['locale' => 'es']);
        $request->setLocale('en');

        self::assertSame('es', $this->resolver->resolve($request));
    }

    public function testResolveUsesRouteLocaleAttribute(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_locale', 'fr');

        self::assertSame('fr', $this->resolver->resolve($request));
    }

    public function testResolveUsesMainRequestLocaleForSubrequests(): void
    {
        $mainRequest = Request::create('/');
        $mainRequest->setLocale('it');
        $this->requestStack->push($mainRequest);

        $subRequest = $mainRequest->duplicate();
        $subRequest->setLocale('en');
        $this->requestStack->push($subRequest);

        self::assertSame('it', $this->resolver->resolve($subRequest));
    }

    public function testResolveUsesAcceptLanguageWhenEnabled(): void
    {
        $request = Request::create('/');
        $request->setLocale('ru');
        $request->headers->set('Accept-Language', 'fr-FR,fr;q=0.9,en;q=0.8');

        self::assertSame('fr', $this->resolver->resolve($request));
    }

    public function testResolveFallsBackToDefaultLocale(): void
    {
        $request = Request::create('/');
        $request->headers->set('Accept-Language', 'de-DE,de;q=0.9');

        self::assertSame('en', $this->resolver->resolve($request));
    }

    public function testResolveSkipsAcceptLanguageWhenDetectionDisabled(): void
    {
        $resolver = new LocaleResolver(['en', 'es'], 'en', false, new RequestStack());
        $request  = Request::create('/');
        $request->headers->set('Accept-Language', 'es-ES,es;q=0.9');

        self::assertSame('en', $resolver->resolve($request));
    }

    public function testResolveIgnoresUnsupportedLocales(): void
    {
        $request = Request::create('/', 'GET', ['locale' => 'xx']);

        self::assertSame('en', $this->resolver->resolve($request));
    }

    public function testAccessorsAndLocaleAttribute(): void
    {
        self::assertSame(['en', 'es', 'it', 'fr', 'de', 'pt', 'nl', 'pl', 'ca'], $this->resolver->getEnabledLocales());
        self::assertSame('en', $this->resolver->getDefaultLocale());
        self::assertTrue($this->resolver->isEnabled('ca'));
        self::assertFalse($this->resolver->isEnabled('ru'));

        $request = Request::create('/');
        $request->attributes->set('locale', 'pt');

        self::assertSame('pt', $this->resolver->resolve($request));
    }

    public function testResolveFallsBackToFirstEnabledWhenDefaultUnsupported(): void
    {
        $resolver = new LocaleResolver(['es', 'fr'], 'xx', false, new RequestStack());
        $request  = Request::create('/');

        self::assertSame('es', $resolver->resolve($request));
    }

    public function testAcceptLanguageSkipsWildcardAndUsesPrimaryTag(): void
    {
        $request = Request::create('/');
        $request->setLocale('ru');
        $request->headers->set('Accept-Language', '*,de;q=0.9');

        self::assertSame('de', $this->resolver->resolve($request));
    }

    public function testAcceptLanguageUsesPrimaryTagWhenRegionalVariantUnsupported(): void
    {
        $resolver = new LocaleResolver(['en', 'fr'], 'en', true, new RequestStack());
        $request  = Request::create('/');
        $request->setLocale('ru');
        $request->headers->set('Accept-Language', 'fr-CA;q=0.9');

        self::assertSame('fr', $resolver->resolve($request));
    }

    public function testAcceptLanguageReturnsNullForEmptyHeader(): void
    {
        $request = Request::create('/');
        $request->setLocale('ru');
        $request->headers->set('Accept-Language', '');

        self::assertSame('en', $this->resolver->resolve($request));
    }

    public function testResolveReturnsDefaultLocaleWhenNoCandidateMatches(): void
    {
        $request = Request::create('/');
        $request->setLocale('ru');
        $request->headers->set('Accept-Language', 'xx-YY,yy;q=0.9');

        self::assertSame('en', $this->resolver->resolve($request));
    }
}
