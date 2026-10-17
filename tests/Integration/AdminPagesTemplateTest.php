<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration;

use Nowo\CookieConsentBundle\Admin\AdminChrome;
use Nowo\CookieConsentBundle\Admin\CookieConsentConfigSettingsSection;
use Nowo\CookieConsentBundle\Tests\Support\AdminPageFixtures;
use Nowo\CookieConsentBundle\Twig\CookieConsentAdminTwigExtension;
use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;
use Nowo\UiKitBundle\Test\RenderedPage;
use Nowo\UiKitBundle\View\ListPage;
use Twig\Environment;

use function dirname;
use function strpos;
use function substr_count;

/**
 * Bundle-specific behaviour of the admin pages on top of the shared page contract: area navigation, cells, row
 * actions behind the UiKit confirm dialog, pagination / empty state, and the deprecated 1.x chrome hooks that have
 * no theme slot (`web_ui.chrome.templates.styles_after|form_theme|form_fields`, `row_actions_display`). The contract
 * assertions also run on the legacy-hook fixtures.
 */
final class AdminPagesTemplateTest extends PageContractTestCase
{
    private const VIEWS = '@NowoCookieConsentBundle/admin/';

    /** @var array<string, mixed> */
    private array $chrome = [];

    public static function pageFixtures(): iterable
    {
        yield 'cookie definitions (row actions as text)' => new PageFixture(self::VIEWS . 'cookie_definition/index.html.twig', AdminPageFixtures::definitionList(false, AdminPageFixtures::factory(null, 'text')));
        yield 'cookie definition (host layout override)' => new PageFixture(self::VIEWS . 'cookie_definition/form.html.twig', AdminPageFixtures::definitionForm(false, AdminPageFixtures::factory('@NowoUiKitContract/layout/admin.html.twig')));
    }

    protected static function templatePaths(): array
    {
        return ['NowoCookieConsentBundle' => dirname(__DIR__, 2) . '/src/Resources/views'];
    }

    protected static function translationDirs(): array
    {
        return [dirname(__DIR__, 2) . '/src/Resources/translations'];
    }

    protected static function templatesDocument(): string
    {
        return dirname(__DIR__, 2) . '/docs/TEMPLATES.md';
    }

    protected static function inlineTemplates(): array
    {
        return [
            '@Host/styles.html.twig'     => '<link rel="stylesheet" href="/host/cookie-admin.css" data-host-styles>',
            '@Host/fields.html.twig'     => '<div data-host-fields>{{ form_row(form.children|first) }}</div>',
            '@Host/form_theme.html.twig' => "{% extends 'form_div_layout.html.twig' %}{% block form_row %}<div data-host-row>{{ parent() }}</div>{% endblock %}",
        ];
    }

    protected function configureTwig(Environment $twig): void
    {
        $twig->addExtension(new CookieConsentAdminTwigExtension(chrome: AdminChrome::resolve($this->chrome)));
    }

    public function testListRendersTheAreaNavAboveThePageHeader(): void
    {
        $page   = $this->render(self::VIEWS . 'cookie_definition/index.html.twig', AdminPageFixtures::definitionList());
        $header = $page->blockHtml('nowo_ui_page_header');

        self::assertStringContainsString('data-nowo-ui-region="area-nav"', $header);
        self::assertLessThan(strpos($header, 'data-nowo-ui-contract-partial="page_header"'), strpos($header, 'data-nowo-ui-region="area-nav"'));
        self::assertStringContainsString('aria-label="Cookie consent admin"', $header);
        self::assertMatchesRegularExpression('#<a[^>]*href="/nowo-cookie-consent-cookie-definitions-index\?configId=1"[^>]*aria-current="page"#s', $header);
        self::assertStringNotContainsString('aria-current="page"', (string) preg_replace('#<a[^>]*cookie-definitions-index.*?</a>#s', '', $header));
        self::assertStringContainsString('Cookie inventory', $header);
    }

    public function testSettingsMarksTheSettingsAreaAndTheCurrentSectionTab(): void
    {
        $page    = $this->render(self::VIEWS . 'config/settings.html.twig', AdminPageFixtures::settings(CookieConsentConfigSettingsSection::RouteTargeting));
        $header  = $page->blockHtml('nowo_ui_page_header');
        $content = $page->blockHtml('nowo_ui_content');

        self::assertMatchesRegularExpression('#href="/nowo-cookie-consent-config-settings-edit\?configId=1"[^>]*aria-current="page"#s', $header);
        self::assertSame(6, substr_count($content, 'href="/nowo-cookie-consent-config-settings-section?'));
        self::assertMatchesRegularExpression('#section=route-targeting"[^>]*aria-current="page"#s', $content);
        self::assertSame(1, substr_count($content, 'data-nowo-ui-region="section"'), 'only the current section renders');
        self::assertStringContainsString('Auto-show targeting', $content);
        self::assertStringContainsString('Control on which Symfony routes the modal opens automatically.', $content);
        self::assertStringContainsString('Save settings', $content);
    }

    public function testListCellsTranslateValuesAndMarkEmptyOnes(): void
    {
        $content = $this->render(self::VIEWS . 'cookie_definition/index.html.twig', AdminPageFixtures::definitionList())->blockHtml('nowo_ui_content');

        self::assertStringContainsString('<code>_ga</code>', $content);
        self::assertStringContainsString('Analytics', $content);
        self::assertStringContainsString('Required', $content);
        self::assertStringContainsString('Distinguishes visitors for Google Analytics.', $content);
        self::assertMatchesRegularExpression('#class="[^"]*pc-badge-success[^"]*"[^>]*>Yes</span>#', $content);
        self::assertMatchesRegularExpression('#class="[^"]*pc-badge-neutral[^"]*"[^>]*>No</span>#', $content);
        self::assertMatchesRegularExpression('#class="[^"]*pc-table-cell-muted[^"]*"[^>]*>—</td>#', $content, 'empty provider of "lang"');
        self::assertStringContainsString('data-label="Allowed by default"', $content);
        self::assertStringContainsString('data-testid="cookie-consent-definitions"', $content);
    }

    public function testDeleteUsesTheUiKitConfirmDialogWithTheCsrfForm(): void
    {
        $content = $this->render(self::VIEWS . 'cookie_definition/index.html.twig', AdminPageFixtures::definitionList())->blockHtml('nowo_ui_content');

        self::assertSame(3, substr_count($content, '<dialog'));
        self::assertStringContainsString('Delete cookie "PHPSESSID"?', $content);
        self::assertStringContainsString('action="/nowo-cookie-consent-cookie-definitions-delete?configId=1&amp;id=8"', $content);
        self::assertStringContainsString('name="delete_cookie_definition_8[_token]" value="token-delete-cookie-definition-8"', $content);
        self::assertStringContainsString('href="/nowo-cookie-consent-cookie-definitions-edit?configId=1&amp;id=7"', $content);
        self::assertStringContainsString('Edit _ga', $content);
    }

    public function testPaginationAndEmptyState(): void
    {
        $list = $this->render(self::VIEWS . 'cookie_definition/index.html.twig', AdminPageFixtures::definitionList())->blockHtml('nowo_ui_content');
        self::assertStringContainsString('data-nowo-ui-region="pagination"', $list);
        self::assertStringContainsString('/nowo_cookie_consent_cookie_definitions_index', $list, 'pagination links keep the list route');

        $empty = $this->render(self::VIEWS . 'cookie_definition/index.html.twig', AdminPageFixtures::definitionList(true))->blockHtml('nowo_ui_content');
        self::assertStringContainsString('data-nowo-ui-region="empty"', $empty);
        self::assertStringContainsString('No cookie definitions yet.', $empty);
        self::assertStringNotContainsString('<table', $empty);
    }

    public function testRowActionsDisplayFromTheDeprecatedChromeOption(): void
    {
        $context = AdminPageFixtures::definitionList(false, AdminPageFixtures::factory(null, 'icon'));
        $page    = $context['page'];
        self::assertInstanceOf(ListPage::class, $page);

        foreach ($page->rowActions($page->rows[7], 7) as $action) {
            self::assertSame('icon', $action->display);
        }
        foreach (AdminPageFixtures::definitionList()['page']->rowActions($page->rows[7], 7) as $action) {
            self::assertNull($action->display, 'inherit nowo_ui_kit.row_actions_display by default');
        }
    }

    public function testFormPageSubmitCancelAndHostLayoutOverride(): void
    {
        $page    = $this->render(self::VIEWS . 'cookie_definition/form.html.twig', AdminPageFixtures::definitionForm());
        $content = $page->blockHtml('nowo_ui_content');

        self::assertStringContainsString('Save cookie', $content);
        self::assertStringContainsString('href="/nowo-cookie-consent-cookie-definitions-index?configId=1"', $content);
        self::assertStringContainsString('name="cookie_definition[name]"', $content);
        self::assertStringContainsString('Edit cookie _ga', $page->blockHtml('nowo_ui_document_title'));
        self::assertStringContainsString('New cookie', $this->render(self::VIEWS . 'cookie_definition/form.html.twig', AdminPageFixtures::definitionForm(true))->blockHtml('nowo_ui_document_title'));
    }

    public function testDeprecatedStylesAfterFormThemeAndFormFieldsStillApply(): void
    {
        $this->chrome = ['templates' => [
            'styles_after' => '@Host/styles.html.twig',
            'form_theme'   => '@Host/form_theme.html.twig',
            'form_fields'  => '@Host/fields.html.twig',
        ]];

        foreach ([
            [self::VIEWS . 'cookie_definition/form.html.twig', AdminPageFixtures::definitionForm()],
            [self::VIEWS . 'config/settings.html.twig', AdminPageFixtures::settings()],
        ] as [$template, $context]) {
            $page    = $this->render($template, $context);
            $content = $page->blockHtml('nowo_ui_content');

            self::assertStringContainsString('data-host-styles', $page->blockHtml('nowo_ui_styles'), $template);
            self::assertStringContainsString('data-host-fields', $content, $template);
            self::assertStringContainsString('data-host-row', $content, $template);
            self::assertSame(1, substr_count($content, '<form'), $template);
        }

        $this->chrome = [];
        $content      = $this->render(self::VIEWS . 'config/settings.html.twig', AdminPageFixtures::settings())->blockHtml('nowo_ui_content');
        self::assertStringNotContainsString('data-host-', $content);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context): RenderedPage
    {
        return $this->renderPage(new PageFixture($template, $context));
    }
}
