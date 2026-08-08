<?php

declare(strict_types=1);

namespace Logdash\Metrics;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise\PromiseInterface;
use Logdash\Logger\InternalLogger;
use Logdash\Queue\RequestQueue;
use Logdash\Types\RequiredInitializationParams;
use Throwable;

class Metrics implements BaseMetrics
{
    private const BATCH_SIZE = 20;
    private const FLUSH_INTERVAL_MS = 1000;
    private const CONCURRENCY = 10;

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
            sendFn: function (array $metrics): void {
                $this->sendBatch($metrics);
            },
            batchSize: self::BATCH_SIZE,
            flushIntervalMs: self::FLUSH_INTERVAL_MS,
            // Metric updates (especially `mutate`) aren't idempotent, so a
            // failed item is never retried here - only dispatched
            // concurrently instead of one-by-one.
            maxAttempts: 1,
            verbose: $this->params->verbose,
        );
    }

    public function set(string $name, float $value): void
    {
        if ($this->params->verbose) {
            InternalLogger::getInternalLogger()->verbose("Setting metric {$name} to {$value}");
        }

        $this->enqueue($name, $value, MetricOperation::SET);
    }

    public function mutate(string $name, float $value): void
    {
        if ($this->params->verbose) {
            InternalLogger::getInternalLogger()->verbose("Mutating metric {$name} by {$value}");
        }

        $this->enqueue($name, $value, MetricOperation::CHANGE);
    }

    /**
     * Sends any buffered metric updates immediately instead of waiting for
     * the batch size/interval or process shutdown. Useful before a
     * long-running worker goes idle.
     */
    public function flush(): void
    {
        $this->queue->flush();
    }

    private function enqueue(string $name, float $value, MetricOperation $operation): void
    {
        $this->queue->add([
            'name' => $name,
            'value' => $value,
            'operation' => $operation->value,
        ]);
    }

    /**
     * @param list<array{name: string, value: float, operation: string}> $metrics
     */
    private function sendBatch(array $metrics): void
    {
        $requests = (function () use ($metrics) {
            foreach ($metrics as $metric) {
                yield fn (): PromiseInterface => $this->httpClient->putAsync($this->params->host . '/metrics', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'project-api-key' => $this->params->apiKey,
                    ],
                    'json' => $metric,
                ]);
            }
        })();

        $pool = new Pool($this->httpClient, $requests, [
            'concurrency' => self::CONCURRENCY,
            'rejected' => function (Throwable $reason): void {
                if ($this->params->verbose) {
                    InternalLogger::getInternalLogger()->verbose('Failed to send metric: ' . $reason->getMessage());
                }
                // Fail silently in production
            },
        ]);

        $pool->promise()->wait();
    }
}
