<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\PersonalData;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Generator;
use Nowo\CookieConsentBundle\Entity\CookieConsentLog;

use function array_chunk;
use function count;
use function implode;
use function is_scalar;
use function sprintf;

/**
 * Reads the consent log rows of a subject in id order, batch by batch, on the connection of the entity manager that maps
 * {@see CookieConsentLog} (1.29). SQL only pre-filters (`=` / `IN` on the IP and key, time bounds one day wider);
 * every row is then re-checked with {@see CookieConsentPersonalDataSubject::matches()} in PHP.
 *
 * @internal built per call by the personal-data ports; holds no state across calls
 */
final readonly class CookieConsentLogRows
{
    /** Windows per pre-filter query (each adds three parameters). */
    private const WINDOWS_PER_QUERY = 50;

    /** Keys per pre-filter query. */
    private const KEYS_PER_QUERY = 500;

    /**
     * @param array{id: string, ip: string, key: string, name: string, value: string, timestamp: string, revision: string} $columns
     */
    private function __construct(
        public Connection $connection,
        private AbstractPlatform $platform,
        private string $table,
        private array $columns,
    ) {
    }

    /**
     * The log table of the manager that maps {@see CookieConsentLog}, or null when none maps it or the table does not
     * exist (nothing to erase or export).
     */
    public static function forRegistry(ManagerRegistry $managerRegistry): ?self
    {
        $manager = $managerRegistry->getManagerForClass(CookieConsentLog::class);
        if (!$manager instanceof EntityManagerInterface) {
            return null;
        }

        $connection = $manager->getConnection();
        $metadata   = $manager->getClassMetadata(CookieConsentLog::class);
        if (!$connection->createSchemaManager()->tablesExist([$metadata->getTableName()])) {
            return null;
        }

        $platform = $connection->getDatabasePlatform();
        $quote    = $manager->getConfiguration()->getQuoteStrategy();
        $columns  = [];
        foreach (['id' => 'id', 'ip' => 'ipAddress', 'key' => 'cookieConsentKey', 'name' => 'cookieName', 'value' => 'cookieValue', 'timestamp' => 'timestamp', 'revision' => 'consentRevision'] as $alias => $field) {
            $columns[$alias] = $quote->getColumnName($field, $metadata, $platform);
        }

        return new self($connection, $platform, $quote->getTableName($metadata, $platform), $columns);
    }

    /**
     * Yields the subject's rows in batches of at most `$batchSize` (a row matching several criteria may come twice).
     *
     * @param int<1, max> $batchSize
     *
     * @return Generator<int, list<array{id: int, cookie_name: string, allowed: bool, consent_revision: int|null, recorded_at: DateTimeImmutable}>>
     */
    public function matchingBatches(CookieConsentPersonalDataSubject $subject, int $batchSize): Generator
    {
        foreach (array_chunk($subject->consentKeys(), self::KEYS_PER_QUERY) as $keys) {
            yield from $this->scan($subject, $this->columns['key'] . ' IN (:keys)', ['keys' => $keys], ['keys' => ArrayParameterType::STRING], $batchSize);
        }

        // The SQL bounds are one day wider than the windows: the PHP re-check decides, so a time zone mismatch between
        // how the row was written and how the bounds are bound can only widen the pre-filter, never narrow the match.
        $slack = new DateInterval('P1D');
        foreach (array_chunk($subject->ipWindows(), self::WINDOWS_PER_QUERY) as $windows) {
            $where  = [];
            $params = [];
            $types  = [];
            foreach ($windows as $i => $window) {
                $where[]            = sprintf('(%s = :ip%d AND %s >= :from%d AND %s <= :to%d)', $this->columns['ip'], $i, $this->columns['timestamp'], $i, $this->columns['timestamp'], $i);
                $params['ip' . $i]   = $window->ip;
                $params['from' . $i] = $window->from->sub($slack);
                $params['to' . $i]   = $window->to->add($slack);
                $types['ip' . $i]    = ParameterType::STRING;
                $types['from' . $i]  = Types::DATETIME_IMMUTABLE;
                $types['to' . $i]    = Types::DATETIME_IMMUTABLE;
            }

            yield from $this->scan($subject, implode(' OR ', $where), $params, $types, $batchSize);
        }
    }

    /**
     * Deletes rows by primary key and returns how many were deleted.
     *
     * @param list<int> $ids
     */
    public function deleteIds(array $ids): int
    {
        return (int) $this->connection->executeStatement(
            sprintf('DELETE FROM %s WHERE %s IN (:ids)', $this->table, $this->columns['id']),
            ['ids' => $ids],
            ['ids' => ArrayParameterType::INTEGER],
        );
    }

    /**
     * Keyset scan of the rows matching the pre-filter, re-checked in PHP.
     *
     * @param array<string, mixed> $params
     * @param array<string, ArrayParameterType|ParameterType|string> $types
     * @param int<1, max> $batchSize
     *
     * @return Generator<int, list<array{id: int, cookie_name: string, allowed: bool, consent_revision: int|null, recorded_at: DateTimeImmutable}>>
     */
    private function scan(CookieConsentPersonalDataSubject $subject, string $where, array $params, array $types, int $batchSize): Generator
    {
        $c   = $this->columns;
        $sql = $this->platform->modifyLimitQuery(sprintf(
            'SELECT %s AS id, %s AS ip, %s AS consent_key, %s AS cookie_name, %s AS cookie_value, %s AS recorded_at, %s AS consent_revision FROM %s WHERE %s > :after AND (%s) ORDER BY %s ASC',
            $c['id'], $c['ip'], $c['key'], $c['name'], $c['value'], $c['timestamp'], $c['revision'], $this->table, $c['id'], $where, $c['id'],
        ), $batchSize);
        $dateType = Type::getType(Types::DATETIME_IMMUTABLE);
        $boolType = Type::getType(Types::BOOLEAN);
        $after    = 0;

        do {
            $rows    = $this->connection->fetchAllAssociative($sql, ['after' => $after] + $params, ['after' => ParameterType::INTEGER] + $types);
            $matched = [];
            foreach ($rows as $row) {
                $after = (int) $row['id'];
                $at    = $dateType->convertToPHPValue($row['recorded_at'], $this->platform);
                if (!$at instanceof DateTimeInterface || !$subject->matches(self::string($row['consent_key']), self::string($row['ip']), $at)) {
                    continue;
                }

                $matched[] = [
                    'id'               => $after,
                    'cookie_name'      => self::string($row['cookie_name']),
                    'allowed'          => (bool) $boolType->convertToPHPValue($row['cookie_value'], $this->platform),
                    'consent_revision' => $row['consent_revision'] === null ? null : (int) $row['consent_revision'],
                    'recorded_at'      => DateTimeImmutable::createFromInterface($at),
                ];
            }

            if ($matched !== []) {
                yield $matched;
            }
        } while (count($rows) === $batchSize);
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
