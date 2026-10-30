<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Loader\AttributeServicesLoader;
use Symfony\Component\Routing\RouterInterface;

use function is_string;
use function str_starts_with;

/**
 * REQ-SF-004 rule 7: the bundle routes are opt-in. A host whose routing only holds the Symfony default
 * `routing.controllers` resource must not get any bundle route (on Symfony >= 7.3 an autoconfigured #[Route]
 * controller is tagged `routing.controller` and that resource would load it).
 */
final class HostRoutingControllersKernelTest extends TestCase
{
    private ?CookieConsentTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testRoutingControllersResourceDoesNotLoadTheBundleRoutes(): void
    {
        if (!class_exists(AttributeServicesLoader::class)) {
            self::markTestSkipped('The routing.controllers resource needs symfony/routing >= 7.3.');
        }

        $bundleRoutes = [];
        foreach ($this->routes(false) as $name => $controller) {
            if (str_starts_with($name, 'nowo_cookie_consent') || (is_string($controller) && str_starts_with($controller, 'Nowo\\CookieConsentBundle\\'))) {
                $bundleRoutes[] = $name;
            }
        }

        self::assertSame([], $bundleRoutes, 'The bundle routes must only exist when the host imports routing.yaml.');
    }

    public function testImportedRoutingYamlRegistersTheBundleRoutes(): void
    {
        $routes = $this->routes(true);

        self::assertArrayHasKey('nowo_cookie_consent.show', $routes);
        self::assertArrayHasKey('nowo_cookie_consent.config', $routes);
        self::assertArrayHasKey('nowo_cookie_consent_cookie_definitions_index', $routes);
    }

    /**
     * @return array<string, mixed> route name => `_controller` default
     */
    private function routes(bool $importBundleRoutes): array
    {
        $this->kernel = new CookieConsentTestKernel(false, ['web_ui' => ['enabled' => true], 'security' => ['allow_unauthenticated' => true]], importBundleRoutes: $importBundleRoutes);
        $this->kernel->boot();

        $router = $this->kernel->getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        $routes = [];
        foreach ($router->getRouteCollection()->all() as $name => $route) {
            $routes[$name] = $route->getDefault('_controller');
        }

        return $routes;
    }
}
