<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Form\CookieDefinitionType;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigAppearanceSettingsType;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigFullSettingsType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormTypeInterface;
use Twig\Environment;

use function array_count_values;
use function array_filter;
use function preg_match;
use function preg_match_all;
use function preg_split;

use const PREG_SPLIT_NO_EMPTY;

/**
 * Regression: admin settings fields rendered through the default admin form theme (bootstrap_5_layout) had a
 * second id attribute (overlay checkbox), doubled form-check-input / form-select / form-control classes, a
 * nested .form-check wrapper and raw FormKit "{form}.{field}.placeholder" keys.
 */
final class AdminSettingsFormRenderTest extends TestCase
{
    private ?CookieConsentTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testOverlayCheckboxRendersOneIdOneClassAndOneWrapper(): void
    {
        $html = $this->render(CookieConsentConfigAppearanceSettingsType::class, new CookieConsentConfig(), 'form_row(form.disablePageInteraction)');

        self::assertSame(1, preg_match('/<input type="checkbox"[^>]*>/', $html, $input));
        self::assertSame(1, preg_match_all('/\sid="/', $input[0]), $input[0]);
        self::assertStringContainsString('class="form-check-input"', $input[0]);
        self::assertStringNotContainsString('placeholder', $input[0]);
        self::assertSame(1, preg_match_all('/class="(?:[^"]*\s)?form-check(?:\s[^"]*)?"/', $html), $html);
        self::assertStringNotContainsString('nowo-cookie-consent-overlay-setting', $html);
    }

    public function testFullSettingsFormHasNoRawPlaceholderKeysNorDuplicatedClasses(): void
    {
        $html = $this->render(CookieConsentConfigFullSettingsType::class, new CookieConsentConfig(), 'form_widget(form)');

        self::assertStringNotContainsString('.placeholder', $html);
        self::assertSame([], $this->duplicatedClassAttributes($html));

        // Explicit placeholders set by the form types are kept.
        self::assertStringContainsString("placeholder=\"admin_*\ncookie_consent_*\"", $html);
        self::assertStringContainsString("placeholder=\"home\nadmin_*\"", $html);
    }

    public function testOptionalPositionSelectKeepsItsTranslatedEmptyOption(): void
    {
        $html = $this->render(CookieConsentConfigFullSettingsType::class, new CookieConsentConfig(), 'form_widget(form.preferencesModalPositionX)');

        self::assertStringContainsString('<option value="">Not applicable</option>', $html);
    }

    public function testCookieDefinitionFormHasNoRawPlaceholderKeysNorDuplicatedClasses(): void
    {
        $html = $this->render(CookieDefinitionType::class, new CookieDefinition(), 'form_widget(form)');

        self::assertStringNotContainsString('.placeholder', $html);
        self::assertSame([], $this->duplicatedClassAttributes($html));
    }

    /**
     * @param class-string<FormTypeInterface<mixed>> $type
     */
    private function render(string $type, object $data, string $expression): string
    {
        $this->kernel = new CookieConsentTestKernel(false, ['web_ui' => ['enabled' => false]]);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        $factory = $container->get('form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $factory);
        $twig = $container->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $form = $factory->create($type, $data, ['csrf_protection' => false]);

        return $twig->createTemplate("{% form_theme form with ['bootstrap_5_layout.html.twig'] %}{{ " . $expression . ' }}')
            ->render(['form' => $form->createView()]);
    }

    /**
     * @return list<string>
     */
    private function duplicatedClassAttributes(string $html): array
    {
        preg_match_all('/class="([^"]*)"/', $html, $matches);

        return array_values(array_filter($matches[1], static function (string $classes): bool {
            $tokens = preg_split('/\s+/', $classes, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            return array_filter(array_count_values($tokens), static fn (int $count): bool => $count > 1) !== [];
        }));
    }
}
