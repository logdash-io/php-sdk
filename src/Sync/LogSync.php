<?php

declare(strict_types=1);

namespace Logdash\Sync;

use Logdash\LogLevel;

interface LogSync
{
    public function send(string $message, LogLevel $level, string $createdAt): void;
}
