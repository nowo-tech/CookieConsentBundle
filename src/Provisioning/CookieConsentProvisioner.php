<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Provisioning;

use Closure;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\CookieConsentBundle\Concurrency\RowVersionGuard;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Entity\CookieDefinitionTranslation;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\DoctrineTransactionRunner;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

use function in_array;
use function method_exists;
use function sprintf;
use function ucfirst;

/**
 * Default {@see CookieConsentProvisionerInterface} (1.36): writes through the bundle's own provisioning manager (a
 * transaction-doctrine `PrivateEntityManager` on the connection of the bundle's manager), inside
 * transaction-doctrine's runner, under {@see ProvisioningLock}.
 *
 * Stateless (REQ-RUNTIME-002): the manager is dropped (`reset()`) when every call returns, whatever the outcome, so no
 * entity survives a call in a worker or a long console command. Entities are compared and written through their own
 * setters: a value counts as changed when the entity's getter answers something else after the setter ran, so the
 * entity's normalization (trimmed names, de-duplicated route lists, sanitized icon) never reports a false change.
 */
final readonly class CookieConsentProvisioner implements CookieConsentProvisionerInterface
{
    /** Attempts of a call made outside a transaction (unique key or lock conflict); inside one: a single attempt. */
    public const MAX_ATTEMPTS = 3;

    /**
     * @param EntityManagerInterface $entityManager The bundle's own provisioning manager (never the application's: it is flushed, cleared and reset here)
     * @param LoggerInterface|null $logger Passed to the transaction runner (structured context only)
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Makes sure the enabled default profile exists with the settings and copies of `$spec` (see the interface).
     */
    public function ensureDefaultProfile(DefaultProfileSpec $spec): ProvisioningResult
    {
        return $this->write(fn (EntityManagerInterface $em): Closure => $this->applyProfile($em, $spec));
    }

    /**
     * Inserts or updates the cookie definitions of the enabled default profile, by name (see the interface).
     */
    public function upsertCookieDefinitions(CookieDefinitionSpecs $definitions): ProvisioningResult
    {
        return $this->write(fn (EntityManagerInterface $em): Closure => $this->applyDefinitions($em, $definitions));
    }

    /**
     * Runs one provisioning unit: lock, transaction, flush, commit; retried on a unique key or a lock conflict when the
     * call opened the outermost transaction.
     *
     * @param Closure(EntityManagerInterface): (Closure(): ProvisioningResult) $operation Applies the specification and answers the result builder (run after the commit, when ids exist)
     */
    private function write(Closure $operation): ProvisioningResult
    {
        try {
            $attempts = $this->entityManager->getConnection()->getTransactionNestingLevel() === 0 ? self::MAX_ATTEMPTS : 1;
            for ($attempt = 1;; ++$attempt) {
                try {
                    return $this->attempt($operation);
                } catch (Throwable $failure) {
                    $duplicate = $this->isUniqueViolation($failure);
                    if (!$duplicate && !$failure instanceof TransientStorageFailure) {
                        throw $failure;
                    }

                    if ($attempt >= $attempts) {
                        throw $duplicate ? CookieConsentProvisioningException::duplicateKey($failure) : $failure;
                    }

                    // A fresh unit of work for the next attempt (the failed one may have closed the manager).
                    $this->dropManager();
                }
            }
        } finally {
            $this->dropManager();
        }
    }

    /**
     * @param Closure(EntityManagerInterface): (Closure(): ProvisioningResult) $operation
     */
    private function attempt(Closure $operation): ProvisioningResult
    {
        $em         = $this->entityManager;
        $connection = $em->getConnection();
        $lock       = ProvisioningLock::acquireSession($connection);

        try {
            /** @var Closure(): ProvisioningResult $result */
            $result = DoctrineTransactionRunner::forEntityManager($em, null, null, 1, $this->logger)->run(static function () use ($em, $connection, $operation, $lock): Closure {
                ProvisioningLock::acquireTransaction($connection, $lock);

                return $operation($em);
            });
        } finally {
            ProvisioningLock::release($connection, $lock);
        }

        return $result();
    }

    /**
     * @return Closure(): ProvisioningResult
     */
    private function applyProfile(EntityManagerInterface $em, DefaultProfileSpec $spec): Closure
    {
        $config  = $this->findDefaultProfile($em);
        $created = !$config instanceof CookieConsentConfig;
        if ($created) {
            $config = (new CookieConsentConfig())->setEnabled(true)->setDefault(true);
            $em->persist($config);
        }

        $changed = false;
        foreach ($spec->settings() as $setting => $value) {
            $changed = $this->set($config, $setting, $value) || $changed;
        }

        // ZAH-2: the stored (or default) values the specification does not give must still fit its layout.
        foreach (['consentModal', 'preferencesModal'] as $modal) {
            if ($created || $spec->managesModalLayout($modal)) {
                $this->assertEffectiveLayout($config, $modal);
            }
        }

        foreach ($spec->translations() as $translationSpec) {
            $translation = $config->findTranslation($translationSpec->locale);
            if (!$translation instanceof CookieConsentConfigTranslation) {
                $translation = (new CookieConsentConfigTranslation())->setLocale($translationSpec->locale);
                $config->addTranslation($translation);
                $em->persist($translation);
                $changed = true;
            }

            foreach ($translationSpec->texts() as $field => $text) {
                $changed = $this->set($translation, $field, $text) || $changed;
            }
        }

        if ($created) {
            // "Only one default profile", as the admin save: the new row exists before the other defaults are cleared.
            $em->flush();
            $this->clearOtherDefaults($em, $config);
        }

        if (!$created && $changed) {
            // An admin page of this profile opened before this write is refused as stale (it never writes it back).
            RowVersionGuard::advance($em, $config, null);
        }

        return static fn (): ProvisioningResult => new ProvisioningResult(
            $created ? 1 : 0,
            !$created && $changed ? 1 : 0,
            !$created && !$changed ? 1 : 0,
            (int) $config->getId(),
        );
    }

    /**
     * @return Closure(): ProvisioningResult
     */
    private function applyDefinitions(EntityManagerInterface $em, CookieDefinitionSpecs $specs): Closure
    {
        $config = $this->findDefaultProfile($em);
        if (!$config instanceof CookieConsentConfig) {
            throw CookieConsentProvisioningException::noDefaultProfile();
        }

        // Every stored definition of the profile, matched in PHP byte for byte (never by the database collation).
        $stored = [];
        foreach ($config->getCookieDefinitions() as $definition) {
            $stored['n:' . $definition->getName()] = $definition;
        }

        $created = $updated = $unchanged = 0;
        foreach ($specs as $spec) {
            $definition = $stored['n:' . $spec->name] ?? null;
            $isNew      = !$definition instanceof CookieDefinition;
            if ($isNew) {
                $definition = (new CookieDefinition())->setName($spec->name);
                $config->addCookieDefinition($definition);
                $em->persist($definition);
            }

            $changed = false;
            foreach ($spec->fields() as $field => $value) {
                $changed = $this->set($definition, $field, $value) || $changed;
            }

            // As the admin page: a cookie of the required category is always pre-ticked.
            if ($definition->getCategory() === CookieDefinitionSpec::REQUIRED_CATEGORY) {
                if ($spec->allowedByDefault === false) {
                    throw CookieDefinitionSpec::requiredNotPreTicked($spec->name);
                }

                $changed = $this->set($definition, 'allowedByDefault', true) || $changed;
            }

            foreach ($spec->translations() as $translationSpec) {
                $translation = $definition->findTranslation($translationSpec->locale);
                if (!$translation instanceof CookieDefinitionTranslation) {
                    $translation = (new CookieDefinitionTranslation())->setLocale($translationSpec->locale);
                    $definition->addTranslation($translation);
                    $em->persist($translation);
                    $changed = true;
                }

                if ($translationSpec->provider !== null) {
                    $changed = $this->set($translation, 'provider', $translationSpec->provider) || $changed;
                }
                if ($translationSpec->purpose !== null) {
                    $changed = $this->set($translation, 'purpose', $translationSpec->purpose) || $changed;
                }
            }

            if ($isNew) {
                ++$created;
            } elseif ($changed) {
                RowVersionGuard::advance($em, $definition, null);
                ++$updated;
            } else {
                ++$unchanged;
            }
        }

        return static fn (): ProvisioningResult => new ProvisioningResult($created, $updated, $unchanged, (int) $config->getId());
    }

    /**
     * The enabled default profile (the lowest id if, against the rule, several are), read from the database.
     *
     * Every enabled profile is locked in id order (`SELECT ... FOR UPDATE`, as the admin "make default" save does), so a
     * concurrent admin save waits for this unit (or this unit for it) and the read sees the latest committed rows, also
     * under REPEATABLE READ on MySQL / MariaDB (a locking read).
     */
    private function findDefaultProfile(EntityManagerInterface $em): ?CookieConsentConfig
    {
        /** @var list<CookieConsentConfig> $enabled */
        $enabled = $em->createQueryBuilder()
            ->select('config')
            ->from(CookieConsentConfig::class, 'config')
            ->andWhere('config.enabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('config.id', 'ASC')
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getResult();

        foreach ($enabled as $config) {
            if ($config->isDefault()) {
                return $config;
            }
        }

        return null;
    }

    /**
     * Clears the default flag of every other enabled profile with one atomic UPDATE that advances their row version
     * (the admin save's rule, {@see CookieConsentConfigRepository::saveSettings()}).
     */
    private function clearOtherDefaults(EntityManagerInterface $em, CookieConsentConfig $keep): void
    {
        $em->createQuery('UPDATE ' . CookieConsentConfig::class . ' config SET config.default = :off, config.rowVersion = config.rowVersion + 1 WHERE config.default = :on AND config.enabled = :enabled AND config.id <> :keep')
            ->setParameter('off', false)
            ->setParameter('on', true)
            ->setParameter('enabled', true)
            ->setParameter('keep', $keep->getId())
            ->execute();
    }

    /**
     * The effective layout, variant and positions of a modal (given, stored or default) must be a combination the
     * admin form offers.
     *
     * @param 'consentModal'|'preferencesModal' $modal
     *
     * @throws InvalidProvisioningSpecException
     */
    private function assertEffectiveLayout(CookieConsentConfig $config, string $modal): void
    {
        $consent = $modal === 'consentModal';
        $layout  = $consent ? $config->getConsentModalLayout() : $config->getPreferencesModalLayout();
        $values  = [
            'Variant'   => [$consent ? $config->getConsentModalVariant() : $config->getPreferencesModalVariant(), CookieConsentConfig::CONSENT_MODAL_VARIANT_TYPES],
            'PositionY' => [$consent ? $config->getConsentModalPositionY() : $config->getPreferencesModalPositionY(), CookieConsentConfig::CONSENT_MODAL_POSITION_Y_TYPES],
            'PositionX' => [$consent ? $config->getConsentModalPositionX() : $config->getPreferencesModalPositionX(), CookieConsentConfig::CONSENT_MODAL_POSITION_X_TYPES],
        ];

        foreach ($values as $suffix => [$value, $choices]) {
            if ($value !== null && !in_array($value, $choices[$layout] ?? [], true)) {
                throw new InvalidProvisioningSpecException(sprintf('Default profile setting "%s%s" is "%s", which the "%s" layout does not offer (stored or default value): give a %s%s the layout offers%s.', $modal, $suffix, $value, $layout, $modal, $suffix, $suffix === 'PositionX' ? ' (or null)' : ''));
            }
        }
    }

    /**
     * Sets one value through the entity's setter and tells whether the stored value changed (getter before / after).
     *
     * @param bool|int|list<string>|string|null $value
     */
    private function set(object $entity, string $field, bool|int|string|array|null $value): bool
    {
        $getter = 'get' . ucfirst($field);
        if (!method_exists($entity, $getter)) {
            $getter = 'is' . ucfirst($field);
        }

        $before = $entity->{$getter}();
        $entity->{'set' . ucfirst($field)}($value);

        return $before !== $entity->{$getter}();
    }

    /**
     * Whether a unique key refused the write (anywhere in the exception chain).
     */
    private function isUniqueViolation(Throwable $failure): bool
    {
        for ($current = $failure; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof UniqueConstraintViolationException) {
                return true;
            }
        }

        return false;
    }

    /**
     * Drops the provisioning manager's unit of work (nothing survives a call or a failed attempt).
     */
    private function dropManager(): void
    {
        if ($this->entityManager instanceof ResetInterface) {
            $this->entityManager->reset();

            return;
        }

        if ($this->entityManager->isOpen()) {
            $this->entityManager->clear();
        }
    }
}
