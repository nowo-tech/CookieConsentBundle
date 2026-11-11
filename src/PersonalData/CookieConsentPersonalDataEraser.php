<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\PersonalData;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function array_column;

/**
 * Default {@see CookieConsentPersonalDataEraserInterface} (1.29): DBAL deletes by primary key, at most
 * {@see self::BATCH_SIZE} rows per statement, on the connection of the entity manager that maps the consent log
 * (`doctrine.entity_manager`, else the default one). No entity manager is flushed or cleared.
 *
 * The subject's IP windows are used only with `$eraseByIpWindow` (`personal_data.ip_windows: erase`, off by default);
 * otherwise they are ignored and a subject without consent keys erases nothing (no event).
 */
final readonly class CookieConsentPersonalDataEraser implements CookieConsentPersonalDataEraserInterface
{
    /** Rows per DELETE (and per transaction when the eraser opens its own). */
    public const BATCH_SIZE = 500;

    public function __construct(
        private ManagerRegistry $managerRegistry,
        private ?EventDispatcherInterface $eventDispatcher = null,
        private ?ClockInterface $clock = null,
        private bool $eraseByIpWindow = false,
    ) {
    }

    public function erase(CookieConsentPersonalDataSubject $subject): CookieConsentErasureResult
    {
        // IP windows are opt-in (`personal_data.ip_windows: erase`): an anonymised IP is shared by every visitor of the
        // same /24 (/48), so by default only the consent keys are used.
        if (!$this->eraseByIpWindow) {
            $subject = $subject->withoutIpWindows();
            if (!$subject instanceof CookieConsentPersonalDataSubject) {
                return new CookieConsentErasureResult();
            }
        }

        $rows = CookieConsentLogRows::forRegistry($this->managerRegistry);
        if (!$rows instanceof CookieConsentLogRows) {
            return new CookieConsentErasureResult();
        }

        // Joined when the caller already opened a transaction (UserKit's eraser run); else one transaction per batch.
        $own     = !$rows->connection->isTransactionActive();
        $deleted = 0;
        foreach ($rows->matchingBatches($subject, self::BATCH_SIZE) as $batch) {
            $ids = array_column($batch, 'id');
            $deleted += $own
                ? (int) $rows->connection->transactional(static fn (Connection $connection): int => $rows->deleteIds($ids))
                : $rows->deleteIds($ids);
        }

        $result = new CookieConsentErasureResult($deleted);
        if ($own) {
            $subjectData = $subject->toArray();
            $this->eventDispatcher?->dispatch(new CookieConsentPersonalDataErasedEvent(
                $subjectData['consent_keys'],
                $subjectData['ip_windows'],
                $result->toArray(),
                $this->clock?->now() ?? new DateTimeImmutable(),
            ));
        }

        return $result;
    }
}
