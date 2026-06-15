<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\DependencyInjection;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;

/**
 * Applies a configurable table prefix to bundle entity metadata.
 */
class TablePrefixListener
{
    /**
     * Creates a new table prefix listener.
     *
     * @param string $tablePrefix The prefix to prepend to entity table names
     */
    public function __construct(
        private readonly string $tablePrefix,
    ) {
    }

    /**
     * Prefixes bundle entity table names when Doctrine loads class metadata.
     *
     * @param LoadClassMetadataEventArgs $event The Doctrine metadata load event
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        if ($this->tablePrefix === '') {
            return;
        }

        $metadata = $event->getClassMetadata();
        if (!str_starts_with($metadata->getName(), 'Nowo\\CookieConsentBundle\\Entity\\')) {
            return;
        }

        $metadata->setPrimaryTable([
            'name' => $this->tablePrefix . $metadata->getTableName(),
        ]);
    }
}
