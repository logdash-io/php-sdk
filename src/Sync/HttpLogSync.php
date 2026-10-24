<?php

declare(strict_types=1);

namespace Logdash\Sync;

use Logdash\Queue\RequestQueue;
use Logdash\Types\LogLevel;
use Logdash\Types\RequiredInitializationParams;

class HttpLogSync implements LogSync
{
    // The API keeps the first 4096 characters of a message, at most 16 KB of UTF-8.
    // Cut there, a batch of 100 logs stays under its 2 MB limit.
    private const MAX_MESSAGE_BYTES = 16_384;

    private int $sequenceNumber = 0;
    private RequestQueue $queue;

    public function __construct(
        private readonly RequiredInitializationParams $params,
        ?RequestQueue $queue = null
    ) {
        $this->queue = $queue ?? new RequestQueue($params);
    }

    public function send(string $message, LogLevel $level, string $createdAt): void
    {
        $this->queue->log([
            'message' => substr($message, 0, self::MAX_MESSAGE_BYTES),
            'level' => $level->value,
            'createdAt' => $createdAt,
            'sequenceNumber' => $this->sequenceNumber++,
            ...($this->params->app === null ? [] : ['app' => $this->params->app]),
        ]);
    }
}
