<?php

declare(strict_types=1);

namespace LogDash\Sync;

use LogDash\LogLevel;

class NoopLogSync implements LogSync
{
    public function send(string $message, LogLevel $level, string $createdAt): void
    {
        // No-op implementation
    }
}
