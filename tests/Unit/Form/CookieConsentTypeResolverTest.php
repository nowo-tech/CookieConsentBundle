<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Form;

use Nowo\CookieConsentBundle\Config\CookieConsentConfigResolver;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigSelector;
use Nowo\CookieConsentBundle\Config\CookieConsentRoutePatternMatcher;
use Nowo\CookieConsentBundle\Config\CookieInventoryProvider;
use Nowo\CookieConsentBundle\Cookie\CookieChecker;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use Nowo\CookieConsentBundle\Form\CookieConsentType;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigTranslationRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CookieConsentTypeResolverTest extends TypeTestCase
{
    public function testBuildsInventoryFromResolvedConfigViaResolver(): void
    {
        $form = $this->factory->create(CookieConsentType::class);

        self::assertTrue($form->has('analytics'));
        self::assertTrue($form->has('marketing'));
    }

    public function testPreSubmitKeepsCategoryDefaultsWhenInventoryEmpty(): void
    {
        $form = $this->factory->create(CookieConsentType::class);
        $form->submit([
            'required'  => '1',
            'analytics' => '0',
            'save'      => '',
        ]);

        self::assertFalse($form->get('analytics')->getData());
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
        $config = (new CookieConsentConfig())
            ->setEnabled(true)
            ->setGranularCookieSelection(true);

        $translation = (new CookieConsentConfigTranslation())
            ->setLocale('en')
            ->setConsentModalTitle('Title');

        $configRepository = $this->createMock(CookieConsentConfigRepository::class);
        $configRepository->method('findAllEnabledNonDefault')->willReturn([]);
        $configRepository->method('findDefaultEnabled')->willReturn($config);

        $translationRepository = $this->createMock(CookieConsentConfigTranslationRepository::class);
        $translationRepository->method('findOneForConfigAndLocale')->willReturn($translation);

        $resolver = new CookieConsentConfigResolver(
            new CookieConsentConfigSelector($configRepository, new CookieConsentRoutePatternMatcher()),
            $translationRepository,
            true,
        );

        $request = Request::create('/page');
        $request->attributes->set('_route', 'home');
        $request->setLocale('en');

        $stack = new RequestStack();
        $stack->push($request);

        return [
            new CookieConsentType(
                new CookieChecker($stack),
                $resolver,
                new CookieInventoryProvider(
                    $this->createMock(CookieDefinitionRepository::class),
                    true,
                    [[
                        'name'         => '_gid',
                        'duration'     => 'Session',
                        'category'     => 'analytics',
                        'type'         => 'first_party',
                        'sort_order'   => 0,
                        'translations' => ['en' => ['provider' => 'Site', 'purpose' => 'Session']],
                    ]],
                ),
                $stack,
                ['analytics', 'marketing'],
                true,
            ),
        ];
    }
}
