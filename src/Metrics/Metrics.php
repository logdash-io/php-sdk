<?php

declare(strict_types=1);

namespace LogDash\Metrics;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use LogDash\Types\RequiredInitializationParams;

enum MetricOperation: string
{
    case SET = 'set';
    case CHANGE = 'change';
}

class Metrics implements BaseMetrics
{
    private Client $httpClient;

    public function __construct(
        private readonly RequiredInitializationParams $params
    ) {
        $this->httpClient = new Client();
    }

    public function set(string $name, float $value): void
    {
        if ($this->params->verbose) {
            \LogDash\Logger\getInternalLogger()->verbose("Setting metric {$name} to {$value}");
        }

        $this->sendMetric($name, $value, MetricOperation::SET);
    }

    public function mutate(string $name, float $value): void
    {
        if ($this->params->verbose) {
            \LogDash\Logger\getInternalLogger()->verbose("Mutating metric {$name} by {$value}");
        }

        $this->sendMetric($name, $value, MetricOperation::CHANGE);
    }

    private function sendMetric(string $name, float $value, MetricOperation $operation): void
    {
        try {
            $this->httpClient->put($this->params->host . '/metrics', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'project-api-key' => $this->params->apiKey,
                ],
                'json' => [
                    'name' => $name,
                    'value' => $value,
                    'operation' => $operation->value,
                ],
                'timeout' => 5,
            ]);
        } catch (GuzzleException $e) {
            if ($this->params->verbose) {
                \LogDash\Logger\getInternalLogger()->verbose("Failed to send metric: " . $e->getMessage());
            }
            // Fail silently in production
        }
    }
}
