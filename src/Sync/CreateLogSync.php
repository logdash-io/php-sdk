<?php

declare(strict_types=1);

namespace Logdash\Sync;

use Logdash\Types\RequiredInitializationParams;

function createLogSync(RequiredInitializationParams $params): LogSync
{
    if (empty($params->apiKey)) {
        \Logdash\Logger\getInternalLogger()->log(
            'Api key was not provided in the InitializationParams when calling Logdash::create(), using only local logger.'
        );
        return new NoopLogSync();
    }

    return new HttpLogSync($params);
}
