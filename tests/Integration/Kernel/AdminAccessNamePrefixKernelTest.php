<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use function sprintf;

/**
 * Audit LR-C-L1: a host that imports the bundle routes with a `name_prefix` keeps the admin access check — it is
 * decided by the controller class, not by the route name. Before 1.28 an anonymous request reached the controller.
 */
final class AdminAccessNamePrefixKernelTest extends TestCase
{
    private ?CookieConsentTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    /**
     * @return iterable<string, array{string|null, string}>
     */
    public static function imports(): iterable
    {
        foreach ([null, 'admin_'] as $prefix) {
            foreach (['/cookie-consent-config/%d/cookies', '/cookie-consent-config/%d/settings/profile'] as $path) {
                yield ($prefix ?? 'no prefix') . ' ' . $path => [$prefix, $path];
            }
        }
    }

    #[DataProvider('imports')]
    public function testAnonymousVisitorsNeverReachTheAdminControllers(?string $prefix, string $path): void
    {
        $this->kernel = new CookieConsentTestKernel(true, ['security' => ['access_roles' => ['ROLE_ADMIN']]], routeNamePrefix: $prefix);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $manager = $container->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        (new SchemaTool($manager))->createSchema($manager->getMetadataFactory()->getAllMetadata());
        $config = (new CookieConsentConfig())->setName('Public site');
        $manager->persist($config);
        $manager->flush();

        $this->expectException(AccessDeniedException::class);
        $this->kernel->handle(Request::create(sprintf($path, (int) $config->getId())), HttpKernelInterface::MAIN_REQUEST, false);
    }
}
