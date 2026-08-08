<?php

declare(strict_types=1);

namespace Logdash\Queue;

use Logdash\Logger\InternalLogger;
use Throwable;

/**
 * Buffers items and flushes them in batches instead of sending one HTTP
 * request per item. A batch is sent once it reaches $batchSize, once
 * $flushIntervalMs has elapsed since the last flush, or when the PHP
 * process/request ends (via a registered shutdown flush).
 */
final class RequestQueue
{
    /** @var list<mixed> */
    private array $items = [];

    private bool $shutdownRegistered = false;

    private float $lastFlushAt;

    /**
     * @param callable(list<mixed>): void $sendFn
     */
    public function __construct(
        private readonly mixed $sendFn,
        private readonly int $batchSize = 25,
        private readonly int $flushIntervalMs = 2000,
        private readonly int $maxAttempts = 1,
        private readonly int $retryDelayMs = 200,
        private readonly bool $verbose = false,
    ) {
        $this->lastFlushAt = microtime(true);
    }

    public function add(mixed $item): void
    {
        $this->items[] = $item;
        $this->registerShutdownFlush();

        $elapsedMs = (microtime(true) - $this->lastFlushAt) * 1000;

        if (count($this->items) >= $this->batchSize || $elapsedMs >= $this->flushIntervalMs) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        $this->lastFlushAt = microtime(true);

        if ($this->items === []) {
            return;
        }

        $batch = $this->items;
        $this->items = [];

        $this->sendWithRetry($batch);
    }

    /**
     * @param list<mixed> $batch
     */
    private function sendWithRetry(array $batch): void
    {
        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            try {
                ($this->sendFn)($batch);
                return;
            } catch (Throwable $e) {
                if ($attempt >= $this->maxAttempts) {
                    if ($this->verbose) {
                        InternalLogger::getInternalLogger()->verbose(
                            'Failed to flush batch of ' . count($batch) . " item(s) after {$attempt} attempt(s): "
                            . $e->getMessage()
                        );
                    }
                    return;
                }

                usleep($this->retryDelayMs * 1000);
            }
        }
    }

    private function registerShutdownFlush(): void
    {
        if ($this->shutdownRegistered) {
            return;
        }

        $this->shutdownRegistered = true;
        register_shutdown_function(function (): void {
            $this->flush();
        });
    }
}
