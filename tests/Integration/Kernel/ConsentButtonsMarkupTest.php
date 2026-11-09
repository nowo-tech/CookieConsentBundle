<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use Nowo\CookieConsentBundle\Form\CookieConsentType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function getenv;

/**
 * Audit JD-M1 / JD-I15 (1.23): the consent buttons as the form really renders them, through the bundle form theme of
 * each modal template (`name="cookie_consent[…]"` plus the role classes). The rendered markup is the fixture the
 * Vitest equal-prominence and remote-config tests load, so the CSS and the scripts are tested against the real names.
 * UPDATE_CONSENT_BUTTON_FIXTURES=1 rewrites the fixtures.
 */
final class ConsentButtonsMarkupTest extends TestCase
{
    private ?CookieConsentTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function themes(): iterable
    {
        yield 'bootstrap' => ['bootstrap', '@NowoCookieConsentBundle/form/cookie_consent_theme.html.twig'];
        yield 'tailwind' => ['tailwind', '@NowoCookieConsentBundle/form/cookie_consent_theme.tailwind.html.twig'];
    }

    #[DataProvider('themes')]
    public function testTheButtonsCarryTheirRoleClassesAndTheFixtureIsCurrent(string $name, string $theme): void
    {
        $html = $this->render($theme);

        self::assertMatchesRegularExpression('/<button[^>]*name="cookie_consent\[use_only_functional_cookies\]"[^>]*class="[^"]*nowo-cookie-consent__btn--reject/', $html);
        self::assertMatchesRegularExpression('/<button[^>]*name="cookie_consent\[use_all_cookies\]"[^>]*class="[^"]*nowo-cookie-consent__btn--accept/', $html);
        self::assertMatchesRegularExpression('/<button[^>]*name="cookie_consent\[save\]"/', $html);
        self::assertStringNotContainsString('name="use_only_functional_cookies"', $html);

        $fixture = dirname(__DIR__, 2) . '/Fixtures/consent_buttons/' . $name . '.html';
        if (getenv('UPDATE_CONSENT_BUTTON_FIXTURES') === '1') {
            file_put_contents($fixture, $html);
        }
        self::assertSame((string) file_get_contents($fixture), $html, 'Run with UPDATE_CONSENT_BUTTON_FIXTURES=1 and commit the fixture');
    }

    private function render(string $theme): string
    {
        $this->kernel = new CookieConsentTestKernel(false, ['web_ui' => ['enabled' => false]]);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $factory = $container->get('form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $factory);
        $twig = $container->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $form = $factory->create(CookieConsentType::class, null, ['csrf_protection' => false]);

        return $twig->createTemplate(
            "{% form_theme form with ['" . $theme . "'] %}"
            . "<div class=\"nowo-cookie-consent__btn-group\">{{ form_row(form.save) }}{{ form_row(form.use_only_functional_cookies) }}{{ form_row(form.use_all_cookies) }}</div>\n",
        )->render(['form' => $form->createView()]);
    }
}
