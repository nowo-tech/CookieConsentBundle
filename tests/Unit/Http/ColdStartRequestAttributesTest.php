<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Http;

use Nowo\CookieConsentBundle\Http\ColdStartRequestAttributes;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ColdStartRequestAttributesTest extends TestCase
{
    public function testDoesNotSkipWhenAttributesAreUnset(): void
    {
        $request = Request::create('/');

        self::assertFalse(ColdStartRequestAttributes::shouldSkipDatabaseAccess($request));
    }

    public function testSkipsWhenSiteBackupSchemaDoesNotExist(): void
    {
        $request = Request::create('/');
        $request->attributes->set(ColdStartRequestAttributes::SITE_BACKUP_SCHEMA_EXISTS, false);

        self::assertTrue(ColdStartRequestAttributes::shouldSkipDatabaseAccess($request));
    }

    public function testSkipsWhenCookieConsentSchemaReadyIsFalse(): void
    {
        $request = Request::create('/');
        $request->attributes->set(ColdStartRequestAttributes::COOKIE_CONSENT_SCHEMA_READY, false);

        self::assertTrue(ColdStartRequestAttributes::shouldSkipDatabaseAccess($request));
    }

    public function testDoesNotSkipWhenSchemaExistsIsTrue(): void
    {
        $request = Request::create('/');
        $request->attributes->set(ColdStartRequestAttributes::SITE_BACKUP_SCHEMA_EXISTS, true);

        self::assertFalse(ColdStartRequestAttributes::shouldSkipDatabaseAccess($request));
    }
}
