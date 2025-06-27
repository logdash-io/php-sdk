<?php

declare(strict_types=1);

namespace LogDash\Sync;

use LogDash\LogLevel;

interface LogSync
{
    public function send(string $message, LogLevel $level, string $createdAt): void;
}
