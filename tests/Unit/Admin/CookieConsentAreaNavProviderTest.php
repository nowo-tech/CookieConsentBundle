<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Admin;

use Nowo\CookieConsentBundle\Admin\AdminPageFactory;
use Nowo\CookieConsentBundle\Admin\CookieConsentAreaNavProvider;
use Nowo\CookieConsentBundle\Admin\CookieConsentConfigSettingsSection;
use Nowo\CookieConsentBundle\Tests\Support\AdminPageFixtures;
use Nowo\UiKitBundle\Navigation\AreaNavProviderInterface;
use Nowo\UiKitBundle\Navigation\AreaNavRegistry;
use Nowo\UiKitBundle\View\AreaNav;
use Nowo\UiKitBundle\View\AreaNavItem;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\Label;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormView;

use function array_map;

final class CookieConsentAreaNavProviderTest extends TestCase
{
    public function testUnknownAreaOrMissingProfileIsNull(): void
    {
        $provider = $this->provider();

        self::assertNull($provider->areaNav('http_log', ['configId' => 1]));
        self::assertNull($provider->areaNav(CookieConsentAreaNavProvider::AREA));
        self::assertNull($provider->areaNav(CookieConsentAreaNavProvider::AREA, ['configId' => 'abc']));
        self::assertNull($provider->areaNav(CookieConsentAreaNavProvider::AREA, ['configId' => 1.5]));
    }

    public function testNavigationOfAProfile(): void
    {
        $provider = $this->provider();

        foreach ([['configId' => 1], ['configId' => '1'], ['config' => AdminPageFixtures::config()]] as $context) {
            $nav = $provider->areaNav(CookieConsentAreaNavProvider::AREA, $context);
            self::assertInstanceOf(AreaNav::class, $nav);
            self::assertSame(
                ['/nowo-cookie-consent-config-settings-edit?configId=1', '/nowo-cookie-consent-cookie-definitions-index?configId=1'],
                array_map(static fn (AreaNavItem $item): string => $item->href, $nav->items),
            );
            self::assertSame(['tabler:settings', 'tabler:cookie'], array_map(static fn (AreaNavItem $item): ?string => $item->icon, $nav->items));
            self::assertNull($nav->active());
            self::assertSame(['data-turbo' => 'false'], $nav->attributes);
            self::assertInstanceOf(Label::class, $nav->label);
            self::assertSame('nowo_cookie_consent.admin.area_nav.label', $nav->label->key);
        }
    }

    public function testSectionOrCurrentHrefMarksTheActiveTab(): void
    {
        $provider = $this->provider();

        self::assertSame(
            '/nowo-cookie-consent-cookie-definitions-index?configId=1',
            $provider->areaNav(CookieConsentAreaNavProvider::AREA, ['configId' => 1, 'section' => AdminPageFactory::AREA_COOKIES])?->active()?->href,
        );
        self::assertNull($provider->areaNav(CookieConsentAreaNavProvider::AREA, ['configId' => 1, 'section' => 'other'])?->active());

        $registry = new AreaNavRegistry([$provider]);
        self::assertSame(
            '/nowo-cookie-consent-config-settings-edit?configId=1',
            $registry->get('cookie_consent', ['configId' => 1, 'current' => '/nowo-cookie-consent-config-settings-edit?configId=1'])?->active()?->href,
        );
    }

    public function testPageHeadersTakeTheTabsFromTheRegistry(): void
    {
        $config = AdminPageFixtures::config();
        $form   = AdminPageFixtures::definitionForm()['page'];
        self::assertInstanceOf(FormPage::class, $form);
        $view = $form->form;
        self::assertInstanceOf(FormView::class, $view, 'UiKit 2.2: FormPage::$form is nullable (body-only mode)');

        $own     = new AdminPageFactory(AdminPageFixtures::urlGenerator());
        $ownTabs = new AreaNavRegistry([new CookieConsentAreaNavProvider($own)]);
        $page    = (new AdminPageFactory(AdminPageFixtures::urlGenerator(), areaNavRegistry: $ownTabs))->settings($config, CookieConsentConfigSettingsSection::Profile, $view);
        self::assertSame('/nowo-cookie-consent-config-settings-edit?configId=1', $page->header->tabs?->active()?->href);

        // A host provider (higher priority) can drop the tabs with an empty AreaNav...
        $host = new class implements AreaNavProviderInterface {
            /** @var list<array<string, mixed>> */
            public array $contexts = [];

            public function areaNav(string $area, array $context = []): ?AreaNav
            {
                $this->contexts[] = $context;

                return $area === CookieConsentAreaNavProvider::AREA ? new AreaNav([]) : null;
            }
        };
        $factory = new AdminPageFactory(AdminPageFixtures::urlGenerator(), areaNavRegistry: new AreaNavRegistry([$host, new CookieConsentAreaNavProvider($own)]));
        self::assertTrue($factory->definitionForm($config, AdminPageFixtures::definition(7, '_ga', 'analytics', '2 years', '', '', false), $view, false)->header->tabs?->isEmpty());
        self::assertSame(['config' => $config, 'configId' => 1, 'section' => AdminPageFactory::AREA_COOKIES], $host->contexts[0] ?? null);

        // ...and without any provider for the area the bundle's own navigation is used.
        $factory = new AdminPageFactory(AdminPageFixtures::urlGenerator(), areaNavRegistry: new AreaNavRegistry([]));
        self::assertCount(2, $factory->definitionList($config, [], [], 1, 20, 0, 'en')->header->tabs->items ?? []);
    }

    private function provider(): CookieConsentAreaNavProvider
    {
        return new CookieConsentAreaNavProvider(AdminPageFixtures::factory());
    }
}
