<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Form;

use Nowo\CookieConsentBundle\Config\CookieConsentConfigResolver;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigSelector;
use Nowo\CookieConsentBundle\Config\CookieConsentRoutePatternMatcher;
use Nowo\CookieConsentBundle\Config\CookieInventoryProvider;
use Nowo\CookieConsentBundle\Config\ResolvedCookieConsentConfig;
use Nowo\CookieConsentBundle\Cookie\CookieChecker;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use Nowo\CookieConsentBundle\Form\CookieConsentType;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigTranslationRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use Nowo\CookieConsentBundle\Tests\Support\FormKitTestSupport;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CookieConsentTypeDatabaseLabelsTest extends TypeTestCase
{
    private RequestStack $requestStack;

    public function testConsecutiveRequestsOnSameTypeUseTheirOwnButtonLabels(): void
    {
        $first = Request::create('/');
        $first->attributes->set('nowo_cookie_consent_config', new ResolvedCookieConsentConfig(
            new CookieConsentConfig(),
            (new CookieConsentConfigTranslation())
                ->setConsentModalAcceptAllBtn('Accept everything')
                ->setConsentModalAcceptNecessaryBtn('Only necessary')
                ->setPreferencesModalSavePreferencesBtn('Store my choice'),
        ));
        $this->requestStack->push($first);

        $form = $this->factory->create(CookieConsentType::class);

        $this->assertButtonLabel($form, 'use_all_cookies', 'Accept everything', false);
        $this->assertButtonLabel($form, 'use_only_functional_cookies', 'Only necessary', false);
        $this->assertButtonLabel($form, 'save', 'Store my choice', false);

        $this->requestStack->pop();
        $this->requestStack->push(Request::create('/'));

        $form = $this->factory->create(CookieConsentType::class);

        $this->assertButtonLabel($form, 'use_all_cookies', 'nowo_cookie_consent.use_all_cookies', 'NowoCookieConsentBundle');
        $this->assertButtonLabel($form, 'use_only_functional_cookies', 'nowo_cookie_consent.use_only_functional_cookies', 'NowoCookieConsentBundle');
        $this->assertButtonLabel($form, 'save', 'nowo_cookie_consent.save', 'NowoCookieConsentBundle');
    }

    protected function getExtensions(): array
    {
        return [];
    }

    /**
     * @return list<FormTypeInterface<mixed>>
     */
    protected function getTypes(): array
    {
        $this->requestStack = new RequestStack();

        return [
            FormKitTestSupport::withMerger(new CookieConsentType(
                new CookieChecker($this->requestStack),
                new CookieConsentConfigResolver(
                    new CookieConsentConfigSelector(
                        $this->createMock(CookieConsentConfigRepository::class),
                        new CookieConsentRoutePatternMatcher(),
                    ),
                    $this->createMock(CookieConsentConfigTranslationRepository::class),
                    false,
                ),
                new CookieInventoryProvider($this->createMock(CookieDefinitionRepository::class), false, []),
                $this->requestStack,
                ['analytics'],
                false,
            )),
        ];
    }

    /**
     * @param FormInterface<array<string, mixed>|null> $form
     */
    private function assertButtonLabel(FormInterface $form, string $name, string $label, string|false $domain): void
    {
        $options = $form->get($name)->getConfig()->getOptions();

        self::assertSame($label, $options['label']);
        self::assertSame($domain, $options['translation_domain']);
    }
}
