<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\PersonalData;

use Doctrine\Persistence\ManagerRegistry;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataErasedEvent;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataEraser;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataExporter;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataSubject;
use Nowo\CookieConsentBundle\Tests\Support\PersonalData\ConsentLogDatabase;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class PortsWithoutTableTest extends TestCase
{
    public function testNoManagerMappingTheLogMeansNothingToDo(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);
        $subject = CookieConsentPersonalDataSubject::fromConsentKey('k');

        self::assertSame(0, (new CookieConsentPersonalDataEraser($registry))->erase($subject)->total());
        self::assertSame(['decisions' => []], (new CookieConsentPersonalDataExporter($registry))->export($subject));
    }

    public function testAMissingTableMeansNothingToDoAndNoEvent(): void
    {
        if (ConsentLogDatabase::url() !== null) {
            self::markTestSkipped('SQLite only.');
        }

        $db         = ConsentLogDatabase::create(withSchema: false);
        $dispatcher = new EventDispatcher();
        $events     = 0;
        $dispatcher->addListener(CookieConsentPersonalDataErasedEvent::class, static function () use (&$events): void {
            ++$events;
        });
        $subject = CookieConsentPersonalDataSubject::fromConsentKey('k');

        self::assertSame(0, (new CookieConsentPersonalDataEraser($db, $dispatcher))->erase($subject)->total());
        self::assertSame(['decisions' => []], (new CookieConsentPersonalDataExporter($db))->export($subject));
        self::assertSame(0, $events);
    }
}
