<?php

declare(strict_types=1);

namespace Logdash\Sync;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Logdash\LogLevel;
use Logdash\Types\RequiredInitializationParams;

class HttpLogSync implements LogSync
{
    private int $sequenceNumber = 0;
    private Client $httpClient;

    public function __construct(
        private readonly RequiredInitializationParams $params
    ) {
        $this->httpClient = new Client();
    }

    /**
     * TODO:
     * - queue
     * - retry
     * - batching
     */
    public function send(string $message, LogLevel $level, string $createdAt): void
    {
        try {
            $this->httpClient->post($this->params->host . '/logs', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'project-api-key' => $this->params->apiKey,
                ],
                'json' => [
                    'message' => $message,
                    'level' => $level->value,
                    'createdAt' => $createdAt,
                    'sequenceNumber' => $this->sequenceNumber++,
                ],
                'timeout' => 5,
            ]);
        } catch (GuzzleException $e) {
            if ($this->params->verbose) {
                \Logdash\Logger\getInternalLogger()->verbose("Failed to send log: " . $e->getMessage());
            }
            // Fail silently in production
        }
    }
}
