<?php

declare(strict_types=1);

/*
 * Second process of the "only one default" race (CookieConsentConcurrencyDatabaseTest): makes profile <id> the default
 * with the bundle's own save unit, inside an outer transaction that keeps its locks for <holdMs> ms, then commits.
 * Prints "LOCKED" once the save ran (its row locks are held) and "DONE" after the commit.
 *
 * Usage: COOKIE_CONSENT_TEST_DATABASE_URL=... php save-default.php <id> <holdMs>
 */

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Tests\Integration\Database\CookieConsentConcurrencyDatabaseTest;
use Nowo\CookieConsentBundle\Tests\Support\PersonalData\ConsentLogDatabase;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$db         = ConsentLogDatabase::connect();
$em         = $db->entityManager;
$connection = $em->getConnection();
CookieConsentConcurrencyDatabaseTest::setLockTimeout($connection, 10);

$config = $em->find(CookieConsentConfig::class, (int) $argv[1]);
if (!$config instanceof CookieConsentConfig) {
    throw new RuntimeException('No profile ' . $argv[1]);
}

$connection->beginTransaction();
$config->setDefault(true);
(new CookieConsentConfigRepository($db))->saveSettings($config, $em);
echo "LOCKED\n";
usleep(1000 * (int) $argv[2]);
$connection->commit();
echo "DONE\n";
