<?php

declare(strict_types=1);

namespace Logdash\Metrics;

interface BaseMetrics
{
    public function set(string $key, float $value): void;

    public function mutate(string $key, float $value): void;
}
