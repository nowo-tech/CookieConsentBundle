<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Config;

use Nowo\CookieConsentBundle\Config\ConsentProfileView;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigReader;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * 1.34: the config read port answers view objects and ids of the ENABLED profiles, never an entity.
 */
#[CoversClass(CookieConsentConfigReader::class)]
final class CookieConsentConfigReaderTest extends TestCase
{
    public function testDefaultProfileAndItsId(): void
    {
        $default = $this->profile(4, 'site', true);
        $default->addTranslation((new CookieConsentConfigTranslation())->setLocale('es')->setConsentModalTitle('Cookies'));
        $reader = new CookieConsentConfigReader($this->repository($default, [$default]));

        self::assertSame(4, $reader->defaultProfileId());

        $view = $reader->defaultProfile();
        self::assertInstanceOf(ConsentProfileView::class, $view);
        self::assertSame([4, 'site', true, null, []], [$view->id, $view->name, $view->isDefault, $view->locale, $view->translationMessages]);

        $localized = $reader->defaultProfile('es');
        self::assertSame('es', $localized?->locale);
        self::assertSame('Cookies', $localized->translationMessages['nowo_cookie_consent.title']);
        self::assertNull($reader->defaultProfile('fr')?->locale, 'no copy for that locale');
        self::assertNull($reader->defaultProfile('')?->locale);
    }

    public function testNoDefaultProfile(): void
    {
        $reader = new CookieConsentConfigReader($this->repository(null, []));

        self::assertNull($reader->defaultProfileId());
        self::assertNull($reader->defaultProfile());
        self::assertSame([], $reader->enabledProfiles());
        self::assertNull($reader->enabledProfile(1));
    }

    public function testEnabledProfilesInRepositoryOrderAndLookupById(): void
    {
        $checkout = $this->profile(9, 'checkout', false);
        $site     = $this->profile(4, 'site', true);
        $reader   = new CookieConsentConfigReader($this->repository($site, [$checkout, $site]));

        self::assertSame([9, 4], array_map(static fn (ConsentProfileView $view): ?int => $view->id, $reader->enabledProfiles()));
        self::assertSame('checkout', $reader->enabledProfile(9)?->name);
        self::assertNull($reader->enabledProfile(5), 'missing or disabled');
    }

    public function testWithoutDoctrineThereAreNoProfiles(): void
    {
        $reader = new CookieConsentConfigReader();

        self::assertNull($reader->defaultProfileId());
        self::assertNull($reader->defaultProfile('en'));
        self::assertNull($reader->enabledProfile(1));
        self::assertSame([], $reader->enabledProfiles('en'));
    }

    /**
     * @param list<CookieConsentConfig> $enabled
     */
    private function repository(?CookieConsentConfig $default, array $enabled): CookieConsentConfigRepository
    {
        $repository = $this->createStub(CookieConsentConfigRepository::class);
        $repository->method('findDefaultEnabled')->willReturn($default);
        $repository->method('findAllEnabled')->willReturn($enabled);

        return $repository;
    }

    private function profile(int $id, string $name, bool $default): CookieConsentConfig
    {
        $config = (new CookieConsentConfig())->setName($name)->setEnabled(true)->setDefault($default);
        (new ReflectionProperty(CookieConsentConfig::class, 'id'))->setValue($config, $id);

        return $config;
    }
}
