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
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use Nowo\CookieConsentBundle\Form\CookieConsentType;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigTranslationRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CookieConsentTypeTest extends TypeTestCase
{
    public function testBuildsCategoryFieldsAndSubmitButtons(): void
    {
        $form = $this->factory->create(CookieConsentType::class);
        $view = $form->createView();

        self::assertTrue($form->has('required'));
        self::assertTrue($form->has('analytics'));
        self::assertTrue($form->has('marketing'));
        self::assertTrue($form->has('save'));
        self::assertTrue($form->has('use_all_cookies'));
        self::assertTrue($form->has('use_only_functional_cookies'));
        self::assertSame('NowoCookieConsentBundle', $view->vars['translation_domain']);
    }

    public function testPreSubmitSetsAllCategoriesWhenAcceptAllClicked(): void
    {
        $form = $this->factory->create(CookieConsentType::class);
        $form->submit([
            'required'        => '1',
            'use_all_cookies' => '',
        ]);

        self::assertTrue($form->get('analytics')->getData());
        self::assertTrue($form->get('marketing')->getData());
    }

    public function testPreSubmitClearsOptionalCategoriesForFunctionalOnly(): void
    {
        $form = $this->factory->create(CookieConsentType::class);
        $form->submit([
            'required'                    => '1',
            'use_only_functional_cookies' => '',
        ]);

        self::assertFalse($form->get('analytics')->getData());
        self::assertFalse($form->get('marketing')->getData());
    }

    public function testPreSubmitKeepsExplicitCategorySelectionOnSave(): void
    {
        $form = $this->factory->create(CookieConsentType::class);
        $form->submit([
            'required'  => '1',
            'analytics' => '1',
            'save'      => '',
        ]);

        self::assertTrue($form->get('analytics')->getData());
        self::assertFalse($form->get('marketing')->getData());
    }

    protected function getExtensions(): array
    {
        return [];
    }

    /**
     * @return list<\Symfony\Component\Form\FormTypeInterface<mixed>>
     */
    protected function getTypes(): array
    {
        $request = Request::create('/');
        $request->cookies->set(CookieNameEnum::getCookieCategoryName('analytics'), 'true');

        $stack = new RequestStack();
        $stack->push($request);

        return [
            new CookieConsentType(
                new CookieChecker($stack),
                new CookieConsentConfigResolver(
                    new CookieConsentConfigSelector(
                        $this->createMock(CookieConsentConfigRepository::class),
                        new CookieConsentRoutePatternMatcher(),
                    ),
                    $this->createMock(CookieConsentConfigTranslationRepository::class),
                    false,
                ),
                new CookieInventoryProvider($this->createMock(CookieDefinitionRepository::class), false, []),
                $stack,
                ['analytics', 'marketing'],
                true,
            ),
        ];
    }
}

final class CookieConsentTypeGranularTest extends TypeTestCase
{
    public function testBuildsGranularCookieFieldsWhenInventoryEnabled(): void
    {
        $form = $this->factory->create(CookieConsentType::class);

        self::assertTrue($form->has('cookies'));
        self::assertTrue($form->get('cookies')->has('_ga'));
        self::assertTrue($form->get('cookies')->has('_fbp'));
    }

    public function testPreSubmitSyncsCategoriesFromGranularCookies(): void
    {
        $form = $this->factory->create(CookieConsentType::class);
        $form->submit([
            'required' => '1',
            'cookies'  => ['_ga' => '1', '_fbp' => '0'],
            'save'     => '',
        ]);

        self::assertTrue($form->get('analytics')->getData());
        self::assertFalse($form->get('marketing')->getData());
    }

    public function testPreSubmitAcceptAllEnablesGranularCookies(): void
    {
        $form = $this->factory->create(CookieConsentType::class);
        $form->submit([
            'required'        => '1',
            'use_all_cookies' => '',
        ]);

        self::assertTrue($form->get('cookies')->get('_ga')->getData());
        self::assertTrue($form->get('cookies')->get('_fbp')->getData());
    }

    public function testFunctionalOnlyClearsGranularCookies(): void
    {
        $form = $this->factory->create(CookieConsentType::class);
        $form->submit([
            'required'                    => '1',
            'use_only_functional_cookies' => '',
        ]);

        self::assertFalse($form->get('cookies')->get('_ga')->getData());
        self::assertFalse($form->get('cookies')->get('_fbp')->getData());
    }

    protected function getExtensions(): array
    {
        return [];
    }

    /**
     * @return list<\Symfony\Component\Form\FormTypeInterface<mixed>>
     */
    protected function getTypes(): array
    {
        $config  = (new CookieConsentConfig())->setGranularCookieSelection(true);
        $request = Request::create('/');
        $request->attributes->set('nowo_cookie_consent_config', new ResolvedCookieConsentConfig($config, null));

        $stack = new RequestStack();
        $stack->push($request);

        return [
            new CookieConsentType(
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
                    [
                        [
                            'name'         => '_ga',
                            'duration'     => '2 years',
                            'category'     => 'analytics',
                            'type'         => 'third_party',
                            'sort_order'   => 0,
                            'translations' => ['en' => ['provider' => 'Google', 'purpose' => 'Analytics']],
                        ],
                        [
                            'name'         => '_fbp',
                            'duration'     => '3 months',
                            'category'     => 'marketing',
                            'type'         => 'third_party',
                            'sort_order'   => 1,
                            'translations' => ['en' => ['provider' => 'Meta', 'purpose' => 'Ads']],
                        ],
                    ],
                ),
                $stack,
                ['analytics', 'marketing'],
                true,
            ),
        ];
    }
}

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
     * @return list<\Symfony\Component\Form\FormTypeInterface<mixed>>
     */
    protected function getTypes(): array
    {
        $config  = (new CookieConsentConfig())->setGranularCookieSelection(true);
        $request = Request::create('/');
        $request->attributes->set('nowo_cookie_consent_config', new ResolvedCookieConsentConfig($config, null));

        $stack = new RequestStack();
        $stack->push($request);

        return [
            new CookieConsentType(
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
            ),
        ];
    }
}
