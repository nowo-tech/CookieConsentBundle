<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

use function dirname;

/**
 * nowo-tech CSP convention: bundle <script>/<style> tags carry the request attribute `csp_nonce`.
 */
final class CspNonceTemplateTest extends TestCase
{
    public function testDiagnosticsScriptCarriesNonceFromRequestAttribute(): void
    {
        $request = Request::create('/');
        $request->attributes->set('csp_nonce', 'abc123');

        $html = $this->twig($request)->render('_diagnostics_script.html.twig');

        self::assertStringContainsString('<script nonce="abc123">', $html);
    }

    public function testDiagnosticsScriptOmitsNonceWithoutAttribute(): void
    {
        $html = $this->twig(Request::create('/'))->render('_diagnostics_script.html.twig');

        self::assertStringContainsString("<script>\n", $html);
        self::assertStringNotContainsString('nonce=', $html);
    }

    public function testDiagnosticsScriptToleratesMissingRequest(): void
    {
        $html = $this->twig(null)->render('_diagnostics_script.html.twig');

        self::assertStringNotContainsString('nonce=', $html);
    }

    public function testPreferencesBubbleUsesDataAttributeInsteadOfInlineStyle(): void
    {
        $html = $this->twig(Request::create('/'))->render('cookie_consent_preferences_bubble.html.twig', [
            'border_color' => '#ff0000',
        ]);

        self::assertStringContainsString('data-nowo-bubble-accent="&#x23;ff0000"', $html);
        self::assertStringNotContainsString('style=', $html);
    }

    private function twig(?Request $request): Environment
    {
        $views  = dirname(__DIR__, 3) . '/src/Resources/views';
        $loader = new FilesystemLoader($views);
        $loader->addPath($views, 'NowoCookieConsentBundle');
        $twig = new Environment($loader, ['strict_variables' => true]);

        $app   = new AppVariable();
        $stack = new RequestStack();
        if ($request instanceof Request) {
            $stack->push($request);
        }
        $app->setRequestStack($stack);
        $twig->addGlobal('app', $app);

        $twig->addFunction(new TwigFunction('nowo_cookie_consent_diagnostic_report', static fn (): array => ['ok' => true]));
        $twig->addFunction(new TwigFunction('nowo_cookie_consent_preferences_bubble_position', static fn (): string => 'bottom-left'));
        $twig->addFunction(new TwigFunction('nowo_cookie_consent_preferences_bubble_border_color', static fn (): ?string => null));
        $twig->addFunction(new TwigFunction('nowo_cookie_consent_preferences_bubble_icon', static fn (): ?string => null));
        $twig->addFilter(new TwigFilter('trans', static fn (string $id): string => $id));

        return $twig;
    }
}
