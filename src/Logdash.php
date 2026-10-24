<?php

declare(strict_types=1);

namespace Logdash;

use Logdash\Events\Events;
use Logdash\Logger\Logger;
use Logdash\Metrics\BaseMetrics;
use Logdash\Queue\RequestQueue;
use Logdash\Types\InitializationParams;
use Logdash\Types\LogLevel;

use function Logdash\Metrics\createMetrics;
use function Logdash\Sync\createLogSync;

class Logdash
{
    private Logger $logger;
    private BaseMetrics $metrics;
    private Events $events;
    private RequestQueue $queue;

    private function __construct(
        Logger $logger,
        BaseMetrics $metrics,
        Events $events,
        RequestQueue $queue
    ) {
        $this->logger = $logger;
        $this->metrics = $metrics;
        $this->events = $events;
        $this->queue = $queue;
    }

    /**
     * @param array{apiKey?:string, host?:string, verbose?:bool, app?:string}|null $params
     */
    public static function create(?array $params = null): self
    {
        $initParams = new InitializationParams(
            apiKey: $params['apiKey'] ?? null,
            host: $params['host'] ?? 'https://api.logdash.io',
            verbose: $params['verbose'] ?? false,
            app: $params['app'] ?? null
        );

        $requiredParams = $initParams->toRequired();
        // One queue sends the logs, metrics and events of this instance together
        $queue = new RequestQueue($requiredParams);
        $logSync = createLogSync($requiredParams, $queue);
        $metrics = createMetrics($requiredParams, $queue);

        $logger = new Logger(
            logMethod: null,
            prefix: null,
            onLog: function (LogLevel $level, string $message) use ($logSync): void {
                $logSync->send($message, $level, date('c'));
            }
        );

        return new self($logger, $metrics, new Events($requiredParams, $queue), $queue);
    }

    public function logger(): Logger
    {
        return $this->logger;
    }

    public function metrics(): BaseMetrics
    {
        return $this->metrics;
    }

    /**
     * Tracks an event, such as an agent run or a signup, and never throws.
     *
     * @param string $name Matches [a-z][a-z0-9_]{0,63}, any other name drops the event with a warning
     * @param array<string, string|int|float|bool> $props Up to 10 properties, never personal data
     * @param string|int|null $userId The signed-in user's internal ID, never an email address
     */
    public function track(string $name, array $props = [], string|int|null $userId = null): void
    {
        $this->events->track($name, $props, $userId);
    }

    /**
     * Sends the logs, metrics and events buffered so far and waits for the API, never throws.
     * A PHP request or script sends them when it ends, so call this where the process lives on, such as after a job.
     */
    public function flush(): void
    {
        $this->queue->flush();
    }
}
