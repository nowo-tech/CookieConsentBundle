<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Form;

use Closure;
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
use Nowo\CookieConsentBundle\Tests\Support\FormKitTestSupport;
use ReflectionMethod;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use function is_array;

final class CookieConsentTypeSavedConsentTest extends TypeTestCase
{
    public function testInitialValuesRespectSavedConsentCookies(): void
    {
        $form = $this->factory->create(CookieConsentType::class);

        self::assertTrue($form->get('analytics')->getData());
        self::assertTrue($form->get('cookies')->get('_ga')->getData());
        self::assertFalse($form->get('cookies')->get('_sid')->getData());
    }

    public function testRequiredInventoryCookiesAreSkippedFromGranularFields(): void
    {
        $form = $this->factory->create(CookieConsentType::class);

        self::assertTrue($form->has('cookies'));
        self::assertFalse($form->get('cookies')->has('PHPSESSID'));
        self::assertTrue($form->get('cookies')->has('_ga'));
    }

    public function testPreSubmitIgnoresNonArrayData(): void
    {
        $form = $this->factory->create(CookieConsentType::class);

        foreach ($form->getConfig()->getEventDispatcher()->getListeners(FormEvents::PRE_SUBMIT) as $listener) {
            $callable = is_array($listener) ? ($listener[0] ?? null) : $listener;

            if (!$callable instanceof Closure) {
                continue;
            }

            $callable(new FormEvent($form, 'not-an-array'));
        }

        self::assertTrue($form->get('required')->getData());
    }

    public function testRequiredCookieRowIsAlwaysInitiallyAllowed(): void
    {
        $type   = $this->getTypes()[0];
        $method = new ReflectionMethod(CookieConsentType::class, 'resolveInitialCookieAllowed');

        self::assertTrue($method->invoke($type, [
            'name'     => 'PHPSESSID',
            'category' => 'required',
        ]));
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
        $request->cookies->set(CookieNameEnum::COOKIE_CONSENT_NAME, date('r'));
        $request->cookies->set(CookieNameEnum::getCookieCategoryName('analytics'), 'true');
        $request->cookies->set(CookieNameEnum::COOKIE_CONSENT_GRANULAR_NAME, '{"_ga": true, "_sid": false}');
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
                    [
                        [
                            'name'         => 'PHPSESSID',
                            'duration'     => 'Session',
                            'category'     => 'required',
                            'type'         => 'first_party',
                            'sort_order'   => 0,
                            'translations' => ['en' => ['provider' => 'Site', 'purpose' => 'Session']],
                        ],
                        [
                            'name'         => '_ga',
                            'duration'     => '2 years',
                            'category'     => 'analytics',
                            'type'         => 'third_party',
                            'sort_order'   => 1,
                            'translations' => ['en' => ['provider' => 'Google', 'purpose' => 'Analytics']],
                        ],
                        [
                            'name'         => '_sid',
                            'duration'     => '1 year',
                            'category'     => 'analytics',
                            'type'         => 'third_party',
                            'sort_order'   => 2,
                            'translations' => ['en' => ['provider' => 'Site', 'purpose' => 'Id']],
                        ],
                    ],
                ),
                $stack,
                ['analytics'],
                true,
            )),
        ];
    }
}
