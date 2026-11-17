<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * REQ-ASSETS-004: the URL rendered by `asset('nowo-cookie-consent.css', 'nowo_cookie_consent')` keeps the bundle base
 * path; the host's default `framework.assets` (CDN `base_urls`, `base_path`) is not inherited by the package.
 */
final class AssetPackageKernelTest extends TestCase
{
    private const EXPECTED = '/bundles/nowocookieconsent/nowo-cookie-consent.css';

    private ?CookieConsentTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testTheRenderedUrlKeepsTheBasePath(): void
    {
        self::assertSame(self::EXPECTED, $this->url([]));
    }

    public function testTheHostsDefaultBaseUrlsAreNotInherited(): void
    {
        self::assertSame(self::EXPECTED, $this->url(['base_urls' => ['https://cdn.example.test']]));
    }

    public function testTheHostsDefaultBasePathIsNotInherited(): void
    {
        self::assertSame(self::EXPECTED, $this->url(['base_path' => '/sub']));
    }

    /**
     * @param array<string, mixed> $assets host `framework.assets`
     */
    private function url(array $assets): string
    {
        $this->kernel = new CookieConsentTestKernel(false, ['web_ui' => ['enabled' => false]], [], ['assets' => $assets], true, null, null, ['assets.packages']);
        $this->kernel->boot();

        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $packages = $container->get('assets.packages');
        self::assertInstanceOf(Packages::class, $packages);

        return $packages->getUrl('nowo-cookie-consent.css', 'nowo_cookie_consent');
    }
}
