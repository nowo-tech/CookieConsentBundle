<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Support;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Test double: keeps every log message.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $records = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = (string) $message;
    }
}
