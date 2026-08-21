<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Render;

use Nowo\CookieConsentBundle\Config\CookieConsentConfigResolver;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigSelector;
use Nowo\CookieConsentBundle\Config\CookieConsentRoutePatternMatcher;
use Nowo\CookieConsentBundle\Config\CookieConsentRouteTargeting;
use Nowo\CookieConsentBundle\Locale\LocaleResolver;
use Nowo\CookieConsentBundle\Render\CookieConsentModalRenderer;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigTranslationRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class CookieConsentModalRendererTest extends TestCase
{
    public function testRenderHtmlReturnsEmptyWithoutRequest(): void
    {
        $renderer = new CookieConsentModalRenderer(
            $this->createMock(Environment::class),
            $this->createMock(FormFactoryInterface::class),
            $this->createMock(RouterInterface::class),
            new LocaleResolver(['en'], 'en', false, new RequestStack()),
            new RequestStack(),
            new CookieConsentConfigResolver(
                new CookieConsentConfigSelector(
                    $this->createMock(CookieConsentConfigRepository::class),
                    new CookieConsentRoutePatternMatcher(),
                ),
                $this->createMock(CookieConsentConfigTranslationRepository::class),
                false,
            ),
            $this->createMock(TranslatorInterface::class),
            new CookieConsentRouteTargeting(new CookieConsentRoutePatternMatcher()),
        );

        self::assertSame('', $renderer->renderHtml());
    }
}
