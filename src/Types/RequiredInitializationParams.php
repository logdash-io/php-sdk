<?php

namespace Logdash\Types;

class RequiredInitializationParams
{
    public function __construct(
        public readonly string $apiKey,
        public readonly string $host,
        public readonly bool $verbose,
        public readonly ?string $app = null
    ) {
    }
}
