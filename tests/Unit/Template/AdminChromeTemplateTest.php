<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Template;

use Nowo\CookieConsentBundle\Admin\AdminChrome;
use Nowo\CookieConsentBundle\DependencyInjection\Configuration;
use Nowo\CookieConsentBundle\Tests\Support\AdminTemplateRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Yaml;

use function dirname;
use function file_get_contents;
use function trim;

/**
 * Spec 002 (FR-010 upstream): admin chrome hooks — default output is unchanged (goldens rendered from the
 * 1.12.0 templates) and every class / attribute / partial / block hook reaches the rendered page.
 */
final class AdminChromeTemplateTest extends TestCase
{
    private AdminTemplateRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new AdminTemplateRenderer();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function goldenPages(): iterable
    {
        yield 'index' => ['index'];
        yield 'index_empty' => ['index_empty'];
        yield 'form' => ['form'];
        yield 'settings' => ['settings'];
    }

    #[DataProvider('goldenPages')]
    public function testDefaultOutputMatchesPreviousRelease(string $page): void
    {
        $html = match ($page) {
            'index'       => $this->renderer->index(),
            'index_empty' => $this->renderer->index(true, [], 1),
            'form'        => $this->renderer->form(),
            default       => $this->renderer->settings(),
        };

        $golden = trim((string) file_get_contents(dirname(__DIR__, 2) . '/Fixtures/admin_chrome/' . $page . '.html'));

        self::assertSame($golden, AdminTemplateRenderer::normalize($html));
    }

    public function testClassAndAttributeMapReachListPage(): void
    {
        $html = $this->renderer->index(false, AdminChrome::resolve([
            'page_title_tag' => 'h2',
            'classes'        => [
                'page_header'    => 'toolbar',
                'page_title'     => 'section-title',
                'table_panel'    => 'panel nowo-ui-table-wrap',
                'table'          => 'data-table',
                'button_primary' => 'btn-primary',
                'area_nav'       => 'mb-4 nowo-ui-tabs--area',
                'pagination'     => 'pager',
            ],
            'attributes' => [
                'table_panel' => ['data-testid' => 'cookie-consent-definitions-table'],
                'area_nav'    => ['data-testid' => 'cookie-consent-area-tabs'],
            ],
        ]));

        self::assertStringContainsString('<div class="toolbar">', $html);
        self::assertStringContainsString('<h2 class="section-title">', $html);
        self::assertStringNotContainsString('<h1', $html);
        self::assertStringContainsString('<div class="panel nowo-ui-table-wrap" data-testid="cookie-consent-definitions-table">', $html);
        self::assertStringContainsString('<table class="data-table">', $html);
        self::assertStringContainsString('class="btn-primary nowo-ui-action-create"', $html);
        self::assertStringContainsString('class="mb-4 nowo-ui-tabs--area" aria-label="Cookie consent admin" data-turbo="false" data-testid="cookie-consent-area-tabs"', $html);
        self::assertStringContainsString('<nav class="pager" aria-label="Pagination">', $html);
    }

    public function testPageTitleCanBeHidden(): void
    {
        $html = $this->renderer->form(AdminChrome::resolve(['page_title_tag' => 'none']));

        self::assertStringNotContainsString('<h1', $html);
        self::assertStringContainsString('Default profile&quot; consent profile.', $html);
    }

    public function testRowActionsDisplayCanInheritUiKitSetting(): void
    {
        $html = $this->renderer->index(false, AdminChrome::resolve(['row_actions_display' => 'inherit']), 1, [], '@NowoCookieConsentBundle/admin/cookie_definition/index.html.twig', [
            'nowo_ui_kit_row_actions_display' => 'icon_text',
        ]);

        self::assertStringContainsString('nowo-ui-row-actions--icon-text', $html);
        self::assertStringNotContainsString('nowo-ui-row-actions--text', $html);
    }

    public function testEmptyStateAndConfirmButtonsUseClassMap(): void
    {
        $empty = $this->renderer->index(true, AdminChrome::resolve([
            'classes'    => ['empty_cell' => 'empty-state'],
            'attributes' => ['empty_cell' => ['data-testid' => 'empty']],
        ]), 1);
        self::assertStringContainsString('<td colspan="7" class="empty-state" data-testid="empty">', $empty);

        $list = $this->renderer->index(false, AdminChrome::resolve([
            'classes' => ['confirm_cancel' => 'btn-ghost', 'confirm_delete' => 'btn-danger'],
        ]), 1);
        self::assertStringContainsString('class="btn-ghost"', $list);
        self::assertStringContainsString('class="btn-danger"', $list);
    }

    public function testHostPartialsReplaceTabsAndPagination(): void
    {
        $html = $this->renderer->index(false, AdminChrome::resolve([
            'templates' => [
                'tabs'       => '@Test/tabs.html.twig',
                'pagination' => '@Test/pagination.html.twig',
            ],
        ]), 3, [
            '@Test/tabs.html.twig'       => '<host-tabs data-variant="{{ variant }}" data-class="{{ nav_class }}">{% for item in items %}[{{ item.label }}{{ item.current ? "*" }}]{% endfor %}</host-tabs>',
            '@Test/pagination.html.twig' => '<host-pager>{{ page }}/{{ pages }}/{{ total }}/{{ page_size }}/{{ item_count }}/{{ framework }}</host-pager>',
        ]);

        self::assertStringContainsString('<host-tabs data-variant="area" data-class="nowo-ui-tabs nowo-ui-tabs--area mb-3">[Settings][Cookie inventory*]</host-tabs>', $html);
        self::assertStringContainsString('<host-pager>1/3/2/20/2/bootstrap5</host-pager>', $html);
    }

    public function testSettingsSectionTabsUseTabsPartial(): void
    {
        $html = $this->renderer->settings(AdminChrome::resolve([
            'classes'    => ['section_tabs' => 'beacon-tabs--sections'],
            'attributes' => ['settings_panel' => ['data-testid' => 'cookie-consent-settings-form']],
            'templates'  => ['tabs' => '@Test/tabs.html.twig'],
        ]), [
            '@Test/tabs.html.twig' => '<host-tabs data-variant="{{ variant }}" data-class="{{ nav_class }}">{{ items|length }}</host-tabs>',
        ]);

        self::assertStringContainsString('<host-tabs data-variant="sections" data-class="beacon-tabs--sections">6</host-tabs>', $html);
        self::assertStringContainsString('data-testid="cookie-consent-settings-form"', $html);
    }

    public function testFormThemeAndFieldsPartialAreConfigurable(): void
    {
        $chrome = AdminChrome::resolve([
            'classes' => [
                'form_panel'       => 'panel',
                'form_panel_body'  => '',
                'form'             => 'nowo-ui-form nowo-ui-form--stack',
                'form_actions'     => 'nowo-ui-form-actions',
                'button_secondary' => 'btn-ghost',
            ],
            'templates' => [
                'form_theme'  => null,
                'form_fields' => '@Test/fields.html.twig',
            ],
        ]);
        $templates = ['@Test/fields.html.twig' => '<host-fields>{{ form.children|length }}</host-fields>'];

        $form = $this->renderer->form($chrome, $templates);
        self::assertStringContainsString('<div class="panel"><form name="cookie_definition" method="post" class="nowo-ui-form nowo-ui-form--stack"><host-fields>2</host-fields>', AdminTemplateRenderer::normalize($form));
        self::assertStringNotContainsString('card-body', $form);
        self::assertStringNotContainsString('form-control', $form);
        self::assertStringContainsString('class="btn-ghost nowo-ui-action-cancel"', $form);

        $settings = $this->renderer->settings(AdminChrome::resolve([
            'classes'   => ['settings_panel_body' => ''],
            'templates' => ['form_theme' => null],
        ]));
        self::assertStringNotContainsString('card-body', $settings);
        self::assertStringNotContainsString('form-control', $settings);
        self::assertStringContainsString('name="settings[displayName]"', $settings);

        $settingsFields = $this->renderer->settings(AdminChrome::resolve(['templates' => ['form_fields' => '@Test/fields.html.twig']]), $templates);
        self::assertStringContainsString('<host-fields>2</host-fields>', $settingsFields);
    }

    public function testStylesAfterTemplateIsIncludedAfterUiKitStylesheet(): void
    {
        $twig = $this->renderer->twig(AdminChrome::resolve(['templates' => ['styles_after' => '@Test/styles.html.twig']]), 'tailwind', [
            '@Test/styles.html.twig' => '<style id="host-remaps"></style>',
            '@Test/page.html.twig'   => '{% extends "@NowoCookieConsentBundle/admin/base.html.twig" %}{% block body %}x{% endblock %}',
        ]);
        $html = AdminTemplateRenderer::normalize($twig->render('@Test/page.html.twig'));

        self::assertStringContainsString('<link rel="stylesheet" href="/assets/nowo_ui_kit/css/nowo-ui.css"><style id="host-remaps"></style>', $html);
    }

    public function testOriginalNamespaceForkCanOverrideASingleBlock(): void
    {
        $html = $this->renderer->index(false, [], 1, [
            '@Test/index_fork.html.twig' => '{% extends "@!NowoCookieConsentBundle/admin/cookie_definition/index.html.twig" %}{% block nowo_cookie_consent_admin_page_actions %}<a class="host-cta">new</a>{% endblock %}{% block nowo_cookie_consent_admin_area_nav %}{% endblock %}',
        ], '@Test/index_fork.html.twig');

        self::assertStringContainsString('<a class="host-cta">new</a>', $html);
        self::assertStringNotContainsString('nowo-ui-action-create', $html);
        self::assertStringNotContainsString('nowo-ui-tabs--area', $html);
        self::assertStringContainsString('<code>_ga</code>', $html);
    }

    public function testHostThemeConfigReplacesEveryBootstrapWrapper(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [Yaml::parseFile(dirname(__DIR__, 2) . '/Fixtures/admin_chrome/host_theme.yaml')]);
        $chrome = AdminChrome::resolve($config['web_ui']['chrome']);
        $hosts  = [
            '@Test/tabs.html.twig'       => '<host-tabs class="{{ nav_class }}"{% for k, v in attr %} {{ k }}="{{ v }}"{% endfor %}></host-tabs>',
            '@Test/pagination.html.twig' => '<host-pager></host-pager>',
            '@Test/fields.html.twig'     => '<host-fields></host-fields>',
            '@Test/styles.html.twig'     => '<style id="host"></style>',
        ];

        $pages = [
            'index'    => $this->renderer->index(false, $chrome, 2, $hosts),
            'form'     => $this->renderer->form($chrome, $hosts),
            'settings' => $this->renderer->settings($chrome, $hosts),
        ];

        foreach ($pages as $name => $html) {
            self::assertStringNotContainsString('card', $html, $name);
            self::assertStringNotContainsString('<h1', $html, $name);
            self::assertStringContainsString('data-testid="cookie-consent-area-tabs"', $html, $name);
            self::assertStringContainsString('<style id="host"></style>', $html, $name);
        }
        self::assertStringContainsString('data-testid="cookie-consent-definitions-table"', $pages['index']);
        self::assertStringContainsString('<host-pager></host-pager>', $pages['index']);
        self::assertStringContainsString('data-testid="cookie-consent-definition-form"', $pages['form']);
        self::assertStringContainsString('<host-fields></host-fields>', $pages['form']);
        self::assertStringContainsString('data-testid="cookie-consent-settings-form"', $pages['settings']);
        self::assertStringContainsString('data-testid="cookie-consent-section-tabs"', $pages['settings']);
    }
}
