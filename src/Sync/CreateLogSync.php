<?php

declare(strict_types=1);

namespace Logdash\Sync;

use Logdash\Queue\RequestQueue;
use Logdash\Types\RequiredInitializationParams;

function createLogSync(RequiredInitializationParams $params, ?RequestQueue $queue = null): LogSync
{
    if (empty($params->apiKey)) {
        \Logdash\Logger\InternalLogger::getInternalLogger()->log(
        // phpcs:ignore
            'Api key was not provided in the InitializationParams when calling Logdash::create(), using only local logger.'
        );
        return new NoopLogSync();
    }

    return new HttpLogSync($params, $queue);
}
