<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\DependencyInjection;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nowo\CookieConsentBundle\DependencyInjection\TablePrefixListener;
use Nowo\CookieConsentBundle\Entity\CookieConsentLog;
use PHPUnit\Framework\TestCase;
use stdClass;

final class TablePrefixListenerTest extends TestCase
{
    public function testAppliesPrefixToConfiguredEntity(): void
    {
        $metadata = new ClassMetadata(CookieConsentLog::class);
        $metadata->setPrimaryTable(['name' => 'nowo_cookie_consent_log']);

        $listener = new TablePrefixListener('app_');
        $listener->loadClassMetadata($this->createEventArgs($metadata));

        self::assertSame('app_nowo_cookie_consent_log', $metadata->getTableName());
    }

    public function testIgnoresOtherEntities(): void
    {
        $metadata = new ClassMetadata(stdClass::class);
        $metadata->setPrimaryTable(['name' => 'other_table']);

        $listener = new TablePrefixListener('app_');
        $listener->loadClassMetadata($this->createEventArgs($metadata));

        self::assertSame('other_table', $metadata->getTableName());
    }

    public function testSkipsWhenPrefixEmpty(): void
    {
        $metadata = new ClassMetadata(CookieConsentLog::class);
        $metadata->setPrimaryTable(['name' => 'nowo_cookie_consent_log']);

        $listener = new TablePrefixListener('');
        $listener->loadClassMetadata($this->createEventArgs($metadata));

        self::assertSame('nowo_cookie_consent_log', $metadata->getTableName());
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function createEventArgs(ClassMetadata $metadata): LoadClassMetadataEventArgs
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);

        return new LoadClassMetadataEventArgs($metadata, $entityManager);
    }
}
