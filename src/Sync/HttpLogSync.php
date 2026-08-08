<?php

declare(strict_types=1);

namespace Logdash\Sync;

use GuzzleHttp\Client;
use Logdash\Queue\RequestQueue;
use Logdash\Types\LogLevel;
use Logdash\Types\RequiredInitializationParams;

class HttpLogSync implements LogSync
{
    private const BATCH_SIZE = 25;
    private const FLUSH_INTERVAL_MS = 2000;

    private int $sequenceNumber = 0;
    private Client $httpClient;
    private RequestQueue $queue;

    public function __construct(
        private readonly RequiredInitializationParams $params,
        ?Client $httpClient = null
    ) {
        $this->httpClient = $httpClient ?? new Client([
            'connect_timeout' => 2,
            'timeout' => 5,
        ]);

        $this->queue = new RequestQueue(
            sendFn: function (array $logs): void {
                $this->sendBatch($logs);
            },
            batchSize: self::BATCH_SIZE,
            flushIntervalMs: self::FLUSH_INTERVAL_MS,
            maxAttempts: 2,
            verbose: $this->params->verbose,
        );
    }

    public function send(string $message, LogLevel $level, string $createdAt): void
    {
        $this->queue->add([
            'message' => $message,
            'level' => $level->value,
            'createdAt' => $createdAt,
            'sequenceNumber' => $this->sequenceNumber++,
        ]);
    }

    /**
     * Sends any buffered logs immediately instead of waiting for the batch
     * size/interval or process shutdown. Useful before a long-running
     * worker goes idle.
     */
    public function flush(): void
    {
        $this->queue->flush();
    }

    /**
     * @param list<array{message: string, level: string, createdAt: string, sequenceNumber: int}> $logs
     */
    private function sendBatch(array $logs): void
    {
        $this->httpClient->post($this->params->host . '/logs/batch', [
            'headers' => [
                'Content-Type' => 'application/json',
                'project-api-key' => $this->params->apiKey,
            ],
            'json' => [
                'logs' => $logs,
            ],
        ]);
    }
}
