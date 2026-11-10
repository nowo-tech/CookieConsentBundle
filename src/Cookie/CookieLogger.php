<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Cookie;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\CookieConsentBundle\Config\ResolvedCookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentLog;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use function filter_var;
use function inet_ntop;
use function inet_pton;
use function str_contains;
use function strlen;

use const FILTER_VALIDATE_IP;

/**
 * Persists anonymized cookie consent choices to the database.
 *
 * The request and the entity manager are resolved on every {@see self::log()} call: the
 * service is shared and may outlive a single request (FrankenPHP worker mode). Rows are written with DBAL
 * (no entity manager is flushed or reset), so pending host changes are never committed by a consent.
 *
 * @final since 1.19, will be final in 2.0 (not an extension point; decorate or replace the service instead)
 */
class CookieLogger implements CookieLoggerInterface
{
    /** Request attribute: the profile of the page the visitor answered on (1.25, audit JR-L3). */
    public const ANSWERED_CONFIG_ATTRIBUTE = 'nowo_cookie_consent_answered_config';

    /** @var list<CookieConsentLog> Rows queued by the current {@see self::log()} call */
    private array $pendingLogs = [];

    /**
     * Creates a new cookie consent logger.
     *
     * @param EntityManagerInterface $entityManager The Doctrine entity manager (fallback when no registry is given)
     * @param RequestStack $requestStack The HTTP request stack
     * @param ClockInterface $clock Clock used for consent timestamps
     * @param LoggerInterface|null $logger Optional PSR logger (never receives raw IP)
     * @param ManagerRegistry|null $managerRegistry Used to obtain the manager of {@see CookieConsentLog} by class
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly ClockInterface $clock,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?ManagerRegistry $managerRegistry = null,
    ) {
    }

    /**
     * Logs consent category choices for the current request.
     *
     * The rows are written with DBAL INSERTs in one transaction on the connection of the entity manager that maps
     * {@see CookieConsentLog} (`doctrine.entity_manager`, else the default one). No entity manager is flushed, so
     * pending changes of the host's unit of work are never committed by a consent POST, and none is reset.
     *
     * @param array<string, bool|string> $categories The submitted category values
     * @param string $key The anonymous consent key
     */
    public function log(array $categories, string $key): void
    {
        $request = $this->requestStack->getMainRequest();

        if (!$request instanceof Request) {
            throw new RuntimeException('No request found');
        }

        $ip               = $this->anonymizeIp($request->getClientIp());
        $loggedCategories = [];

        try {
            foreach ($categories as $category => $value) {
                if ($category === 'required') {
                    continue;
                }

                $loggedCategories[] = (string) $category;
                $boolValue          = $value === true || $value === 'true';
                $this->persistCookieConsentLog((string) $category, $boolValue, $ip, $key);
            }

            $this->insertPendingLogs($this->getEntityManager());
        } finally {
            $this->pendingLogs = [];
        }

        $this->logger?->info('Cookie consent choices persisted.', [
            'consent_key' => $key,
            'categories'  => $loggedCategories,
        ]);
    }

    /**
     * Builds the log row of one category and queues it for {@see self::log()} (it is not persisted on any entity manager).
     */
    protected function persistCookieConsentLog(string $category, bool $value, string $ip, string $key): void
    {
        $cookieConsentLog = (new CookieConsentLog())
            ->setIpAddress($ip)
            ->setCookieConsentKey($key)
            ->setCookieName($category)
            ->setCookieValue($value)
            ->setTimestamp($this->clock->now())
            ->setConsentRevision($this->resolveConsentRevision());

        // @igor-ignore - Not shared worker service state.
        $this->pendingLogs[] = $cookieConsentLog;
    }

    /**
     * Inserts the queued rows in one transaction, bypassing the unit of work (joins an open host transaction).
     */
    private function insertPendingLogs(EntityManagerInterface $entityManager): void
    {
        if ($this->pendingLogs === []) {
            return;
        }

        $connection    = $entityManager->getConnection();
        $platform      = $connection->getDatabasePlatform();
        $quoteStrategy = $entityManager->getConfiguration()->getQuoteStrategy();
        $metadata      = $entityManager->getClassMetadata(CookieConsentLog::class);
        $table         = $quoteStrategy->getTableName($metadata, $platform);
        $rows          = [];

        foreach ($this->pendingLogs as $cookieConsentLog) {
            $data  = [];
            $types = [];

            foreach ($metadata->getFieldNames() as $field) {
                if ($metadata->isIdentifier($field)) {
                    continue;
                }

                $column         = $quoteStrategy->getColumnName($field, $metadata, $platform);
                $data[$column]  = $metadata->getFieldValue($cookieConsentLog, $field);
                $types[$column] = (string) $metadata->getTypeOfField($field);
            }

            // Sequence-style generators (pre-insert) need the id in the INSERT; identity columns fill it themselves.
            if (!$metadata->idGenerator->isPostInsertGenerator()) {
                $idField          = $metadata->getSingleIdentifierFieldName();
                $idColumn         = $quoteStrategy->getColumnName($idField, $metadata, $platform);
                $data[$idColumn]  = $metadata->idGenerator->generateId($entityManager, $cookieConsentLog);
                $types[$idColumn] = (string) $metadata->getTypeOfField($idField);
            }

            $rows[] = [$data, $types];
        }

        $connection->transactional(static function (Connection $connection) use ($table, $rows): void {
            foreach ($rows as [$data, $types]) {
                $connection->insert($table, $data, $types);
            }
        });
    }

    /**
     * Returns the `revision` of the consent profile the visitor answered (1.25, JH-L1).
     *
     * Prefers the profile of the page the form was shown on ({@see self::ANSWERED_CONFIG_ATTRIBUTE}, set from the
     * form's `page_route`, audit JR-L3), then the profile resolved for the request that saved the consent.
     *
     * @return int|null The profile revision, or null without a database profile
     */
    private function resolveConsentRevision(): ?int
    {
        foreach ([self::ANSWERED_CONFIG_ATTRIBUTE, 'nowo_cookie_consent_config'] as $attribute) {
            foreach ([$this->requestStack->getMainRequest(), $this->requestStack->getCurrentRequest()] as $request) {
                $resolved = $request?->attributes->get($attribute);

                if ($resolved instanceof ResolvedCookieConsentConfig) {
                    return $resolved->getConfig()->getRevision();
                }
            }
        }

        return null;
    }

    /**
     * Returns the entity manager that maps {@see CookieConsentLog} (resolved by class), else the injected one.
     *
     * Only its connection and metadata are used, so a manager closed by the host is neither needed open nor reset.
     */
    private function getEntityManager(): EntityManagerInterface
    {
        $manager = $this->managerRegistry?->getManagerForClass(CookieConsentLog::class);

        return $manager instanceof EntityManagerInterface ? $manager : $this->entityManager;
    }

    /**
     * Anonymizes the client IP before it is stored: IPv4 masks the last octet with `x`,
     * IPv6 keeps only the first 48 bits (the rest is zeroed, same on every Symfony version).
     */
    protected function anonymizeIp(?string $ip): string
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return 'unknown';
        }

        if (!str_contains($ip, '.')) {
            return (string) inet_ntop(substr((string) inet_pton($ip), 0, 6) . str_repeat("\0", 10));
        }

        // IPv4 or IPv4-mapped IPv6 (e.g. ::ffff:192.0.2.1): mask the last octet.
        $lastDot = (int) strrpos($ip, '.') + 1;

        return substr($ip, 0, $lastDot) . str_repeat('x', strlen($ip) - $lastDot);
    }
}
