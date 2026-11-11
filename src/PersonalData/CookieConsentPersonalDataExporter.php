<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\PersonalData;

use DateTimeInterface;
use Doctrine\Persistence\ManagerRegistry;

use function array_values;
use function usort;

/**
 * Default {@see CookieConsentPersonalDataExporterInterface} (1.29): reads the subject's consent log rows on the
 * connection of the entity manager that maps the consent log. Read-only.
 */
final readonly class CookieConsentPersonalDataExporter implements CookieConsentPersonalDataExporterInterface
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
    ) {
    }

    public function export(CookieConsentPersonalDataSubject $subject): array
    {
        $rows = CookieConsentLogRows::forRegistry($this->managerRegistry);
        if (!$rows instanceof CookieConsentLogRows) {
            return ['decisions' => []];
        }

        $byId = [];
        foreach ($rows->matchingBatches($subject, CookieConsentPersonalDataEraser::BATCH_SIZE) as $batch) {
            foreach ($batch as $row) {
                $byId[$row['id']] = $row;
            }
        }

        $byId = array_values($byId);
        usort($byId, static fn (array $a, array $b): int => [$a['recorded_at'], $a['id']] <=> [$b['recorded_at'], $b['id']]);

        $decisions = [];
        foreach ($byId as $row) {
            $decisions[] = [
                'cookie_name'      => $row['cookie_name'],
                'allowed'          => $row['allowed'],
                'consent_revision' => $row['consent_revision'],
                'recorded_at'      => $row['recorded_at']->format(DateTimeInterface::ATOM),
            ];
        }

        return ['decisions' => $decisions];
    }
}
