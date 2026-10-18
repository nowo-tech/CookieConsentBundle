<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\CookieConsentBundle\Config\ResolvedCookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Regression (1.17): the database profile copy was resolved at kernel.request priority 19, before Symfony's
 * LocaleListener (16) applied `_locale`, so `/en/…` on an application whose default locale is `es` got the Spanish
 * profile copy mixed with English bundle strings. Through a real kernel, a prefixed non-default locale now resolves
 * the copy of that locale.
 */
final class LocalizedProfileCopyKernelTest extends TestCase
{
    private ?CookieConsentTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testPrefixedNonDefaultLocaleResolvesItsOwnProfileCopy(): void
    {
        $this->kernel = new CookieConsentTestKernel(false, [
            'use_database_config' => true,
            'default_locale'      => 'es',
            'enabled_locales'     => ['es', 'en'],
            'web_ui'              => ['enabled' => false],
        ], [], ['default_locale' => 'es', 'enabled_locales' => ['es', 'en']]);
        $this->kernel->boot();

        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $em = $container->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

        $config = (new CookieConsentConfig())->setName('Site')->setEnabled(true)->setDefault(true);
        $config->addTranslation((new CookieConsentConfigTranslation())->setLocale('es')->setConsentModalTitle('Título ES'));
        $config->addTranslation((new CookieConsentConfigTranslation())->setLocale('en')->setConsentModalTitle('Title EN'));
        $em->persist($config);
        $em->flush();
        $em->clear();

        $request = Request::create('/en/cookie-consent/config');
        $this->kernel->handle($request);

        $resolved = $request->attributes->get('nowo_cookie_consent_config');
        self::assertInstanceOf(ResolvedCookieConsentConfig::class, $resolved);
        self::assertSame('Title EN', $resolved->getTranslationMessages()['nowo_cookie_consent.title'] ?? null);
    }
}
