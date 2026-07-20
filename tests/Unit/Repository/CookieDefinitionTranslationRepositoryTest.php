<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionTranslationRepository;
use PHPUnit\Framework\TestCase;

final class CookieDefinitionTranslationRepositoryTest extends TestCase
{
    public function testRepositoryClass(): void
    {
        $registry   = $this->createMock(ManagerRegistry::class);
        $repository = new CookieDefinitionTranslationRepository($registry);

        self::assertInstanceOf(CookieDefinitionTranslationRepository::class, $repository);
    }
}
