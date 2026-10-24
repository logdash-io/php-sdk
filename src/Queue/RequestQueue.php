<?php

declare(strict_types=1);

namespace Logdash\Queue;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Pool;
use Logdash\Logger\InternalLogger;
use Logdash\Metrics\MetricOperation;
use Logdash\Types\RequiredInitializationParams;
use Throwable;

/**
 * Buffers logs, metrics and events and sends them together, concurrently: when 100 are buffered,
 * when one comes a second or more after the oldest, when the PHP request or script ends, and on flush()
 */
final class RequestQueue
{
    // POST /logs/batch takes up to 100 logs
    private const MAX_BUFFERED = 100;
    // ponytail: checked only when one more comes, so an idle worker keeps the rest until flush() or its exit;
    // sending them on time needs a timer, from pcntl or an event loop
    private const MAX_AGE_NANOSECONDS = 1_000_000_000;
    private const CONCURRENCY = 10;
    private const RETRY_DELAY_MICROSECONDS = 200_000;

    /** @var list<array<string, mixed>> */
    private array $logs = [];
    /** @var array<string, array{name: string, value: float, operation: string, app?: string}> By metric name */
    private array $metrics = [];
    /** @var list<array<string, mixed>> */
    private array $events = [];
    private ?int $oldestAt = null;
    private bool $flushesAtShutdown = false;
    private Client $httpClient;

    public function __construct(
        private readonly RequiredInitializationParams $params
    ) {
        $this->httpClient = new Client([
            // An unreachable API fails in 2 seconds instead of the whole timeout
            'connect_timeout' => 2,
            'timeout' => 5,
            'headers' => [
                'Content-Type' => 'application/json',
                'project-api-key' => $params->apiKey,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $log
     */
    public function log(array $log): void
    {
        $this->logs[] = $log;
        $this->buffered();
    }

    /**
     * @param array{name: string, value: float, operation: string, app?: string} $metric
     */
    public function metric(array $metric): void
    {
        // Merged the way the API merges the updates it buffers: a set replaces the update, a change adds to it.
        // As one request, the updates of a metric cannot arrive out of order.
        $update = $this->metrics[$metric['name']] ?? null;
        $this->metrics[$metric['name']] = $update === null || $metric['operation'] === MetricOperation::SET->value
            ? $metric
            : ['value' => $update['value'] + $metric['value']] + $update;
        $this->buffered();
    }

    /**
     * @param array<string, mixed> $event
     */
    public function event(array $event): void
    {
        $this->events[] = $event;
        $this->buffered();
    }

    /**
     * Sends everything buffered and waits for the API, never throws
     */
    public function flush(): void
    {
        // Logs and events that fail get one retry: the API stores an event once per id.
        // Metrics get none: a change the API applied before the connection broke would apply twice.
        $requests = [
            ...($this->logs === [] ? [] : [['POST', '/logs/batch', ['logs' => $this->logs], true]]),
            ...array_map(
                fn (array $metric): array => ['PUT', '/metrics', $metric, false],
                array_values($this->metrics)
            ),
            ...array_map(fn (array $event): array => ['POST', '/events', $event, true], $this->events),
        ];
        $this->logs = $this->metrics = $this->events = [];
        $this->oldestAt = null;

        $retries = array_filter($this->send($requests), fn (array $request): bool => $request[3]);

        if ($retries !== []) {
            usleep(self::RETRY_DELAY_MICROSECONDS);
            $this->send($retries);
        }
    }

    private function buffered(): void
    {
        if (!$this->flushesAtShutdown) {
            $this->flushesAtShutdown = true;
            register_shutdown_function(function (): void {
                // Reset first: a shutdown function after this one that logs registers this again, to run after it
                $this->flushesAtShutdown = false;
                $this->flush();
            });
        }

        $this->oldestAt ??= hrtime(true);

        if (
            count($this->logs) + count($this->metrics) + count($this->events) >= self::MAX_BUFFERED
            || hrtime(true) - $this->oldestAt >= self::MAX_AGE_NANOSECONDS
        ) {
            $this->flush();
        }
    }

    /**
     * Sends $requests at once
     *
     * @param array<int, array{string, string, array<string, mixed>, bool}> $requests The method, path and body
     *     of each, and whether it gets a retry
     * @return array<int, array{string, string, array<string, mixed>, bool}> Those that failed for a reason that
     *     can pass, such as a timeout or a 5xx
     */
    private function send(array $requests): array
    {
        $sends = [];

        foreach ($requests as $index => [$method, $path, $body]) {
            // Bad UTF-8 becomes U+FFFD, so that one log does not cost its whole batch
            $json = json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);

            if ($json === false) {
                // A metric or event prop of INF or NAN, which JSON has no number for
                $this->verbose("Failed to send {$method} {$path}: " . json_last_error_msg());
                continue;
            }

            $sends[$index] = fn () => $this->httpClient->requestAsync($method, $this->params->host . $path, [
                'body' => $json,
            ]);
        }

        $failed = [];

        try {
            (new Pool($this->httpClient, $sends, [
                'concurrency' => self::CONCURRENCY,
                'rejected' => function (Throwable $reason, int $index) use ($requests, &$failed): void {
                    $this->verbose('Failed to send: ' . $reason->getMessage());

                    // A 4xx, such as a rate limit, would answer a retry the same
                    if (!$reason instanceof ClientException) {
                        $failed[$index] = $requests[$index];
                    }
                },
            ]))->promise()->wait();
        } catch (Throwable $e) {
            // Such as a host that is no URL
            $this->verbose('Failed to send: ' . $e->getMessage());
        }

        return $failed;
    }

    private function verbose(string $message): void
    {
        if ($this->params->verbose) {
            InternalLogger::getInternalLogger()->verbose($message);
        }
    }
}
