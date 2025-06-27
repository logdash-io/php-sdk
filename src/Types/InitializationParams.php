<?php

declare(strict_types=1);

namespace Logdash\Types;

class InitializationParams
{
    public function __construct(
        public readonly ?string $apiKey = null,
        public readonly string $host = 'https://api.logdash.io',
        public readonly bool $verbose = false
    ) {}

    public function toRequired(): RequiredInitializationParams
    {
        return new RequiredInitializationParams(
            apiKey: $this->apiKey ?? '',
            host: $this->host,
            verbose: $this->verbose
        );
    }
}

class RequiredInitializationParams
{
    public function __construct(
        public readonly string $apiKey,
        public readonly string $host,
        public readonly bool $verbose
    ) {}
}
