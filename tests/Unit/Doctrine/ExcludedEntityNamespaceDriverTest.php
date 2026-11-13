<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Persistence\Mapping\MappingException;
use Nowo\CookieConsentBundle\DependencyInjection\Compiler\CookieConsentEntityManagerPass;
use Nowo\CookieConsentBundle\Doctrine\ExcludedEntityNamespaceDriver;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentLog;
use PHPUnit\Framework\TestCase;

final class ExcludedEntityNamespaceDriverTest extends TestCase
{
    public function testMapsNothing(): void
    {
        $driver = new ExcludedEntityNamespaceDriver();

        self::assertTrue($driver->isTransient(CookieConsentLog::class));
        self::assertSame([], $driver->getAllClassNames());

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(CookieConsentLog::class);
        $driver->loadMetadataForClass(CookieConsentLog::class, new ClassMetadata(CookieConsentLog::class));
    }

    /**
     * A chain asks its namespace drivers before its default driver: a default driver that claims every entity class
     * no longer claims the bundle entities.
     */
    public function testWinsOverTheDefaultDriverOfAChain(): void
    {
        $catchAll = $this->createStub(MappingDriver::class);
        $catchAll->method('isTransient')->willReturn(false);
        $chain = new MappingDriverChain();
        $chain->setDefaultDriver($catchAll);
        self::assertFalse($chain->isTransient(CookieConsentLog::class));

        $chain->addDriver(new ExcludedEntityNamespaceDriver(), CookieConsentEntityManagerPass::ENTITY_NAMESPACE);

        self::assertTrue($chain->isTransient(CookieConsentLog::class));
        self::assertTrue($chain->isTransient(CookieConsentConfig::class));
        self::assertFalse($chain->isTransient(self::class), 'other classes still go to the default driver');
    }
}
