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
use Nowo\CookieConsentBundle\Form\CookieConsentType;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigTranslationRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use Nowo\CookieConsentBundle\Tests\Support\FormKitTestSupport;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CookieConsentTypeMarketingDefaultTest extends TypeTestCase
{
    public function testInitialCategoryFalseWhenInventoryDisallowsByDefault(): void
    {
        $form = $this->factory->create(CookieConsentType::class);

        self::assertFalse($form->get('marketing')->getData());
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
        $config  = (new CookieConsentConfig())->setGranularCookieSelection(true);
        $request = Request::create('/');
        $request->attributes->set('nowo_cookie_consent_config', new ResolvedCookieConsentConfig($config, null));

        $stack = new RequestStack();
        $stack->push($request);

        return [
            FormKitTestSupport::withMerger(new CookieConsentType(
                new CookieChecker($stack),
                new CookieConsentConfigResolver(
                    new CookieConsentConfigSelector(
                        $this->createMock(CookieConsentConfigRepository::class),
                        new CookieConsentRoutePatternMatcher(),
                    ),
                    $this->createMock(CookieConsentConfigTranslationRepository::class),
                    false,
                ),
                new CookieInventoryProvider(
                    $this->createMock(CookieDefinitionRepository::class),
                    true,
                    [[
                        'name'               => '_optout',
                        'duration'           => '1 year',
                        'category'           => 'marketing',
                        'type'               => 'third_party',
                        'sort_order'         => 0,
                        'allowed_by_default' => false,
                        'translations'       => ['en' => ['provider' => 'Ads', 'purpose' => 'Tracking']],
                    ]],
                ),
                $stack,
                ['marketing'],
                true,
            )),
        ];
    }
}
