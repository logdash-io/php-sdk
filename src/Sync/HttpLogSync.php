<?php

declare(strict_types=1);

namespace Logdash\Sync;

use Logdash\Queue\RequestQueue;
use Logdash\Types\LogLevel;
use Logdash\Types\RequiredInitializationParams;

class HttpLogSync implements LogSync
{
    // The API keeps the first 4096 characters of a message
    private const MAX_MESSAGE_CHARACTERS = 4096;

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
            'message' => self::cut($message),
            'level' => $level->value,
            'createdAt' => $createdAt,
            'sequenceNumber' => $this->sequenceNumber++,
            ...($this->params->app === null ? [] : ['app' => $this->params->app]),
        ]);
    }

    /**
     * The first 4096 characters of $message, without mbstring, which PHP may lack
     */
    private static function cut(string $message): string
    {
        if (strlen($message) <= self::MAX_MESSAGE_CHARACTERS) {
            return $message;
        }

        // A character is a byte and the UTF-8 continuation bytes after it, so bad UTF-8 stays as it is for
        // json_encode to replace
        $length = strlen($message);
        $cut = 0;

        for ($characters = 0; $characters < self::MAX_MESSAGE_CHARACTERS && $cut < $length; $characters++) {
            do {
                $cut++;
            } while ($cut < $length && (ord($message[$cut]) & 0xC0) === 0x80);
        }

        return substr($message, 0, $cut);
    }
}
