<?php

declare(strict_types=1);

namespace Logdash\Sync;

use Logdash\Types\LogLevel;

class NoopLogSync implements LogSync
{
    public function send(string $message, LogLevel $level, string $createdAt): void
    {
        // No-op implementation
    }
}
