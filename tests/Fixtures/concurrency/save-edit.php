<?php

declare(strict_types=1);

/*
 * Second process of the admin edit races (CookieConsentConcurrencyDatabaseTest): saves an edit of a profile or of a
 * cookie definition with the bundle's own save unit and the row version of its page, inside an outer transaction that
 * keeps the row lock for <holdMs> ms, then commits. Prints "LOCKED" once the save ran (the row is locked and advanced)
 * and "DONE" after the commit.
 *
 * Usage: COOKIE_CONSENT_TEST_DATABASE_URL=... php save-edit.php <profile|definition> <id> <token> <holdMs> <new name>
 */

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use Nowo\CookieConsentBundle\Tests\Integration\Database\CookieConsentConcurrencyDatabaseTest;
use Nowo\CookieConsentBundle\Tests\Support\PersonalData\ConsentLogDatabase;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$db         = ConsentLogDatabase::connect();
$em         = $db->entityManager;
$connection = $em->getConnection();
CookieConsentConcurrencyDatabaseTest::setLockTimeout($connection, 10);

[, $kind, $id, $token, $holdMs, $name] = $argv;

$connection->beginTransaction();
if ($kind === 'profile') {
    $entity = $em->find(CookieConsentConfig::class, (int) $id);
    if (!$entity instanceof CookieConsentConfig) {
        throw new RuntimeException('No profile ' . $id);
    }
    $entity->setName($name);
    (new CookieConsentConfigRepository($db))->saveSettings($entity, $em, (int) $token);
} else {
    $entity = $em->find(CookieDefinition::class, (int) $id);
    if (!$entity instanceof CookieDefinition) {
        throw new RuntimeException('No definition ' . $id);
    }
    $entity->setName($name);
    (new CookieDefinitionRepository($db))->saveEdit($entity, $em, (int) $token);
}
echo "LOCKED\n";
usleep(1000 * (int) $holdMs);
$connection->commit();
echo "DONE\n";
