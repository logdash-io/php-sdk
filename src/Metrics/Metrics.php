<?php

declare(strict_types=1);

namespace Logdash\Metrics;

use Logdash\Logger\InternalLogger;
use Logdash\Queue\RequestQueue;
use Logdash\Types\RequiredInitializationParams;

class Metrics implements BaseMetrics
{
    private RequestQueue $queue;

    public function __construct(
        private readonly RequiredInitializationParams $params,
        ?RequestQueue $queue = null
    ) {
        $this->queue = $queue ?? new RequestQueue($params);
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

    private function enqueue(string $name, float $value, MetricOperation $operation): void
    {
        $this->queue->metric(
            ['name' => $name, 'value' => $value, 'operation' => $operation->value]
            + ($this->params->app === null ? [] : ['app' => $this->params->app])
        );
    }
}
