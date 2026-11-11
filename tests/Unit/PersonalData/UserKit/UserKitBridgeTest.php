<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\PersonalData\UserKit;

use DateTimeImmutable;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentIpWindow;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataSubject;
use Nowo\CookieConsentBundle\PersonalData\IpWindowSubjectResolver;
use Nowo\CookieConsentBundle\PersonalData\UserKit\CookieConsentLogsPersonalDataEraser;
use Nowo\CookieConsentBundle\PersonalData\UserKit\CookieConsentsPersonalDataExportProvider;
use Nowo\CookieConsentBundle\Tests\Support\PersonalData\FixedSubjectResolver;
use Nowo\CookieConsentBundle\Tests\Support\PersonalData\SpyPersonalDataEraser;
use Nowo\CookieConsentBundle\Tests\Support\PersonalData\SpyPersonalDataExporter;
use Nowo\CookieConsentBundle\Tests\Support\PersonalData\SpySubjectResolver;
use Nowo\UserKitBundle\PersonalData\ErasureRequest;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class UserKitBridgeTest extends TestCase
{
    public function testKeysAndPriorities(): void
    {
        $eraser   = new CookieConsentLogsPersonalDataEraser($this->eraser(), new IpWindowSubjectResolver());
        $provider = new CookieConsentsPersonalDataExportProvider($this->exporter(), new IpWindowSubjectResolver());

        self::assertSame('cookie_consent_logs', $eraser->getKey());
        self::assertTrue($eraser->supports(new stdClass()));
        self::assertSame('cookie_consents', $provider->getSectionKey());
        self::assertTrue($provider->supports(new stdClass()));
    }

    public function testWithoutProviderTheBridgeIsANoOp(): void
    {
        $port = $this->eraser();
        (new CookieConsentLogsPersonalDataEraser($port, new IpWindowSubjectResolver()))->erase(new ErasureRequest(new InMemoryUser('alice', null)));

        self::assertSame([], $port->subjects);
        self::assertSame(['decisions' => []], (new CookieConsentsPersonalDataExportProvider($this->exporter(), new IpWindowSubjectResolver()))->export(new InMemoryUser('alice', null)));
    }

    public function testErasesOncePerDistinctSubjectOfTheOriginalIdentifierAndThePreviousEmail(): void
    {
        $resolver = $this->resolver();
        $port     = $this->eraser();
        $user     = new InMemoryUser('anonymised-1', null);

        (new CookieConsentLogsPersonalDataEraser($port, $resolver))->erase(new ErasureRequest($user, context: ['previous_email' => 'old@example.test'], originalIdentifier: 'alice@example.test'));

        self::assertSame([[$user, 'alice@example.test'], [$user, 'old@example.test']], $resolver->calls);
        self::assertSame([['alice@example.test'], ['old@example.test']], array_map(static fn (CookieConsentPersonalDataSubject $s): array => $s->consentKeys(), $port->subjects));
    }

    public function testIgnoresAnEmptyOrRepeatedPreviousEmailAndDuplicateSubjects(): void
    {
        $resolver = $this->resolver(constant: 'same');
        $port     = $this->eraser();
        $user     = new InMemoryUser('alice', null);
        $eraser   = new CookieConsentLogsPersonalDataEraser($port, $resolver);

        $eraser->erase(new ErasureRequest($user, context: ['previous_email' => ''], originalIdentifier: 'alice'));
        $eraser->erase(new ErasureRequest($user, context: ['previous_email' => 'alice']));
        $eraser->erase(new ErasureRequest($user, context: ['previous_email' => 'other']));

        self::assertSame([[$user, 'alice'], [$user, 'alice'], [$user, 'alice'], [$user, 'other']], $resolver->calls);
        self::assertCount(3, $port->subjects, 'the two identical subjects of the last request are erased once');
    }

    public function testIpWindowsAreDroppedUnlessErasureByIpWindowIsOn(): void
    {
        $window   = CookieConsentIpWindow::of('203.0.113.42', new DateTimeImmutable('2026-03-10T00:00:00+00:00'), new DateTimeImmutable('2026-03-11T00:00:00+00:00'));
        $resolver = new FixedSubjectResolver([CookieConsentPersonalDataSubject::of([$window]), CookieConsentPersonalDataSubject::of([$window], ['k-1'])]);
        $user     = new InMemoryUser('alice', null);

        $off = $this->eraser();
        (new CookieConsentLogsPersonalDataEraser($off, $resolver))->erase(new ErasureRequest($user, originalIdentifier: 'alice'));
        self::assertCount(1, $off->subjects, 'the window-only subject is dropped');
        self::assertSame(['consent_keys' => ['k-1'], 'ip_windows' => []], $off->subjects[0]->toArray());

        $on = $this->eraser();
        (new CookieConsentLogsPersonalDataEraser($on, $resolver, eraseByIpWindow: true))->erase(new ErasureRequest($user, originalIdentifier: 'alice'));
        self::assertCount(2, $on->subjects);
        self::assertCount(1, $on->subjects[1]->ipWindows());

        $exporter = $this->exporter();
        (new CookieConsentsPersonalDataExportProvider($exporter, $resolver))->export($user);
        self::assertSame([['k-1']], $exporter->keys, 'the export never uses IP windows');
        self::assertSame([], $exporter->subjects[0]->ipWindows());
    }

    public function testExportMergesTheSubjects(): void
    {
        $exporter = $this->exporter();
        $provider = new CookieConsentsPersonalDataExportProvider($exporter, $this->resolver(extra: 'k-2'));

        self::assertSame(['decisions' => [['cookie_name' => 'analytics', 'allowed' => true, 'consent_revision' => null, 'recorded_at' => '2026-01-01T00:00:00+00:00']]], $provider->export(new InMemoryUser('alice', null)));
        self::assertSame([['alice', 'k-2']], $exporter->keys);
    }

    private function eraser(): SpyPersonalDataEraser
    {
        return new SpyPersonalDataEraser();
    }

    private function exporter(): SpyPersonalDataExporter
    {
        return new SpyPersonalDataExporter();
    }

    private function resolver(?string $constant = null, ?string $extra = null): SpySubjectResolver
    {
        return new SpySubjectResolver($constant, $extra);
    }
}
