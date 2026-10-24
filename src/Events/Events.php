<?php

declare(strict_types=1);

namespace Logdash\Events;

use Logdash\Logger\InternalLogger;
use Logdash\Queue\RequestQueue;
use Logdash\Types\RequiredInitializationParams;

class Events
{
    private const NAME = '/^[a-z][a-z0-9_]{0,63}$/D';
    // Belong to the website tracker, so the API rejects them
    private const WEBSITE_EVENTS = ['pageview', 'pageleave', 'browser_error'];

    private RequestQueue $queue;

    public function __construct(
        private readonly RequiredInitializationParams $params,
        ?RequestQueue $queue = null
    ) {
        $this->queue = $queue ?? new RequestQueue($params);
    }

    /**
     * @param array<string, string|int|float|bool> $props
     */
    public function track(string $name, array $props, string|int|null $userId): void
    {
        if (preg_match(self::NAME, $name) !== 1 || in_array($name, self::WEBSITE_EVENTS, true)) {
            InternalLogger::getInternalLogger()->warn(
                "Dropping event \"{$name}\": its name must match [a-z][a-z0-9_]{0,63}",
                'and not be pageview, pageleave or browser_error'
            );
            return;
        }

        if (is_string($userId) && str_contains($userId, '@')) {
            InternalLogger::getInternalLogger()->warn(
                "Dropping event \"{$name}\": its userId must be an internal ID, never an email address"
            );
            return;
        }

        $event = [
            'name' => $name,
            ...($props === [] ? [] : ['props' => $props]),
            ...($userId === null || $userId === '' ? [] : ['userId' => $userId]),
            ...($this->params->app === null ? [] : ['app' => $this->params->app]),
        ];

        if (empty($this->params->apiKey)) {
            // Like logs without an API key: printed locally only
            InternalLogger::getInternalLogger()->log(
                'Event',
                (string) json_encode($event, JSON_PARTIAL_OUTPUT_ON_ERROR)
            );
            return;
        }

        if ($this->params->verbose) {
            InternalLogger::getInternalLogger()->verbose("Tracking event {$name}");
        }

        // The API stores an event once per id, so a retry must send the same one
        $this->queue->event(['id' => self::uuidV4()] + $event);
    }

    private static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
