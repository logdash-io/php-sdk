<?php

declare(strict_types=1);

namespace Logdash\Metrics;

class NoopMetrics implements BaseMetrics
{
    public function set(string $key, float $value): void
    {
        // No-op implementation
    }

    public function mutate(string $key, float $value): void
    {
        // No-op implementation
    }
}
