<?php

declare(strict_types=1);

/*
 * Second process of the provisioning races (ProvisionerConcurrencyDatabaseTest): waits until <startAt> (a
 * microtime(true) value) so both seeders start together, then runs ensureDefaultProfile() and
 * upsertCookieDefinitions() with the bundle's provisioner on its own connection, and prints the counts as JSON.
 *
 * Usage: COOKIE_CONSENT_TEST_DATABASE_URL=... php provision.php <startAt>
 */

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Provisioning\CookieConsentProvisioner;
use Nowo\CookieConsentBundle\Tests\Integration\Database\CookieConsentConcurrencyDatabaseTest;
use Nowo\CookieConsentBundle\Tests\Integration\Database\ProvisionerConcurrencyDatabaseTest;
use Nowo\CookieConsentBundle\Tests\Support\PersonalData\ConsentLogDatabase;
use Nowo\TransactionDoctrine\PrivateManager\PrivateEntityManager;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$db = ConsentLogDatabase::connect();
CookieConsentConcurrencyDatabaseTest::setLockTimeout($db->entityManager->getConnection(), 15);
$provisioner = new CookieConsentProvisioner(new PrivateEntityManager($db, 'default', CookieConsentConfig::class, 'child'));
$db->entityManager->getConnection()->fetchOne('SELECT 1');

$wait = (float) $argv[1] - microtime(true);
if ($wait > 0) {
    usleep((int) ($wait * 1e6));
}

echo json_encode(ProvisionerConcurrencyDatabaseTest::seed($provisioner)), "\n";
