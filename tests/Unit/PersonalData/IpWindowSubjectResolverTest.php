<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\PersonalData;

use DateTimeImmutable;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentIpWindow;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentIpWindowProviderInterface;
use Nowo\CookieConsentBundle\PersonalData\IpWindowSubjectResolver;
use PHPUnit\Framework\TestCase;
use stdClass;

final class IpWindowSubjectResolverTest extends TestCase
{
    public function testWithoutProvidersThereIsNoSubject(): void
    {
        self::assertSame([], (new IpWindowSubjectResolver())->subjectsFor(new stdClass(), 'alice'));
    }

    public function testWidensEveryProviderWindowIntoOneSubject(): void
    {
        $user      = new stdClass();
        $providerA = new class implements CookieConsentIpWindowProviderInterface {
            /** @var list<array{object, ?string}> */
            public array $calls = [];

            public function ipWindowsFor(object $user, ?string $originalIdentifier): iterable
            {
                $this->calls[] = [$user, $originalIdentifier];

                yield CookieConsentIpWindow::of('203.0.113.10', new DateTimeImmutable('2026-01-10T00:00:00+00:00'), new DateTimeImmutable('2026-01-11T00:00:00+00:00'));
            }
        };
        $providerB = new class implements CookieConsentIpWindowProviderInterface {
            public function ipWindowsFor(object $user, ?string $originalIdentifier): iterable
            {
                return [CookieConsentIpWindow::of('192.0.2.1', new DateTimeImmutable('2026-02-10T00:00:00+00:00'), new DateTimeImmutable('2026-02-10T00:00:00+00:00'))];
            }
        };

        $subjects = (new IpWindowSubjectResolver([$providerA, $providerB], 'PT12H'))->subjectsFor($user, 'alice');

        self::assertCount(1, $subjects);
        self::assertSame([
            ['ip' => '203.0.113.xx', 'from' => '2026-01-09T12:00:00+00:00', 'to' => '2026-01-11T12:00:00+00:00'],
            ['ip' => '192.0.2.x', 'from' => '2026-02-09T12:00:00+00:00', 'to' => '2026-02-10T12:00:00+00:00'],
        ], $subjects[0]->toArray()['ip_windows']);
        self::assertSame([[$user, 'alice']], $providerA->calls);
    }
}
