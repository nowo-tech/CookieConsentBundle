<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\PersonalData\UserKit;

use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataSubject;
use Nowo\CookieConsentBundle\PersonalData\IpWindowSubjectResolver;
use Nowo\CookieConsentBundle\PersonalData\UserKit\CookieConsentLogsPersonalDataEraser;
use Nowo\CookieConsentBundle\PersonalData\UserKit\CookieConsentsPersonalDataExportProvider;
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
