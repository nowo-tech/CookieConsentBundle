<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Twig;

use Nowo\CookieConsentBundle\Config\ResolvedCookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use Nowo\CookieConsentBundle\Twig\CookieConsentTranslationTwigExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
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

        self::assertSame(['nowo_cookie_consent_trans'], array_map(
            static fn (TwigFunction $function): string => $function->getName(),
            $extension->getFunctions(),
        ));
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

    private function createTranslator(): Translator
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'nowo_cookie_consent.title'     => 'YAML title',
            'nowo_cookie_consent.read_more' => 'YAML read more',
        ], 'en', CookieConsentTranslationTwigExtension::TRANSLATION_DOMAIN);

        return $translator;
    }
}
