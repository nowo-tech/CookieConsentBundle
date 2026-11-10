<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Event;

use DateTimeImmutable;
use Nowo\CookieConsentBundle\Event\ConsentSavedEvent;
use PHPUnit\Framework\TestCase;

final class ConsentSavedEventTest extends TestCase
{
    public function testCarriesScalarsAndAnswersCategoryQuestions(): void
    {
        $at    = new DateTimeImmutable('2026-11-10T10:00:00Z');
        $event = new ConsentSavedEvent('key', ['analytics' => true, 'marketing' => false], ['_ga' => true], true, $at);

        self::assertSame('key', $event->consentKey);
        self::assertSame(['_ga' => true], $event->granularCookies);
        self::assertTrue($event->logged);
        self::assertSame($at, $event->occurredAt);
        self::assertTrue($event->isCategoryAllowed('required'));
        self::assertTrue($event->isCategoryAllowed('analytics'));
        self::assertFalse($event->isCategoryAllowed('marketing'));
        self::assertFalse($event->isCategoryAllowed('unknown'));
    }
}
