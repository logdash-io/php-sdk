<?php

declare(strict_types=1);

namespace Logdash\Metrics;

use Logdash\Types\RequiredInitializationParams;

function createMetrics(RequiredInitializationParams $params): BaseMetrics
{
    if (empty($params->apiKey)) {
        return new NoopMetrics();
    }

    return new Metrics($params);
}
