<?php

declare(strict_types=1);

namespace LogDash;

use LogDash\Logger\Logger;
use LogDash\Metrics\BaseMetrics;
use LogDash\Types\InitializationParams;
use LogDash\Types\RequiredInitializationParams;

require_once __DIR__ . '/Metrics/CreateMetrics.php';
require_once __DIR__ . '/Sync/CreateLogSync.php';

use function LogDash\Metrics\createMetrics;
use function LogDash\Sync\createLogSync;

class LogDash
{
    private Logger $logger;
    private BaseMetrics $metrics;

    private function __construct(
        Logger $logger,
        BaseMetrics $metrics
    ) {
        $this->logger = $logger;
        $this->metrics = $metrics;
    }

    public static function create(?array $params = null): self
    {
        $initParams = new InitializationParams(
            apiKey: $params['apiKey'] ?? null,
            host: $params['host'] ?? 'https://api.logdash.io',
            verbose: $params['verbose'] ?? false
        );

        $requiredParams = $initParams->toRequired();
        $logSync = createLogSync($requiredParams);
        $metrics = createMetrics($requiredParams);

        $logger = new Logger(
            logMethod: null,
            prefix: null,
            onLog: function (LogLevel $level, string $message) use ($logSync): void {
                $logSync->send($message, $level, date('c'));
            }
        );

        return new self($logger, $metrics);
    }

    public function logger(): Logger
    {
        return $this->logger;
    }

    public function metrics(): BaseMetrics
    {
        return $this->metrics;
    }
}
