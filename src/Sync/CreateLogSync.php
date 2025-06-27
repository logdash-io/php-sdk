<?php

declare(strict_types=1);

namespace LogDash\Sync;

use LogDash\Types\RequiredInitializationParams;

function createLogSync(RequiredInitializationParams $params): LogSync
{
    if (empty($params->apiKey)) {
        \LogDash\Logger\getInternalLogger()->log(
            'Api key was not provided in the InitializationParams when calling LogDash::create(), using only local logger.'
        );
        return new NoopLogSync();
    }

    return new HttpLogSync($params);
}
