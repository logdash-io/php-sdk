<?php

declare(strict_types=1);

namespace Logdash\Metrics;

use Logdash\Queue\RequestQueue;
use Logdash\Types\RequiredInitializationParams;

function createMetrics(RequiredInitializationParams $params, ?RequestQueue $queue = null): BaseMetrics
{
    if (empty($params->apiKey)) {
        return new NoopMetrics();
    }

    return new Metrics($params, $queue);
}
