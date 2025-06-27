<?php

declare(strict_types=1);

namespace Logdash\Logger;

use Logdash\LogLevel;

class Logger
{
    private const LOG_LEVEL_COLORS = [
        LogLevel::ERROR->value => [231, 0, 11],
        LogLevel::WARN->value => [254, 154, 0],
        LogLevel::INFO->value => [21, 93, 252],
        LogLevel::HTTP->value => [0, 166, 166],
        LogLevel::VERBOSE->value => [0, 166, 0],
        LogLevel::DEBUG->value => [0, 166, 0],
        LogLevel::SILLY->value => [80, 80, 80],
    ];

    public function __construct(
        private readonly ?callable $logMethod = null,
        private readonly ?callable $prefix = null,
        private readonly ?callable $onLog = null
    ) {}

    public function error(mixed ...$data): void
    {
        $this->log(LogLevel::ERROR, implode(' ', $this->convertToStrings($data)));
    }

    public function warn(mixed ...$data): void
    {
        $this->log(LogLevel::WARN, implode(' ', $this->convertToStrings($data)));
    }

    public function info(mixed ...$data): void
    {
        $this->log(LogLevel::INFO, implode(' ', $this->convertToStrings($data)));
    }

    public function log(LogLevel|mixed ...$data): void
    {
        if (count($data) === 0) {
            return;
        }

        $level = LogLevel::INFO;
        $message = '';

        if ($data[0] instanceof LogLevel) {
            $level = array_shift($data);
            $message = implode(' ', $this->convertToStrings($data));
        } else {
            $message = implode(' ', $this->convertToStrings($data));
        }

        $this->internalLog($level, $message);
    }

    public function http(mixed ...$data): void
    {
        $this->log(LogLevel::HTTP, implode(' ', $this->convertToStrings($data)));
    }

    public function verbose(mixed ...$data): void
    {
        $this->log(LogLevel::VERBOSE, implode(' ', $this->convertToStrings($data)));
    }

    public function debug(mixed ...$data): void
    {
        $this->log(LogLevel::DEBUG, implode(' ', $this->convertToStrings($data)));
    }

    public function silly(mixed ...$data): void
    {
        $this->log(LogLevel::SILLY, implode(' ', $this->convertToStrings($data)));
    }

    private function internalLog(LogLevel $level, string $message): void
    {
        $color = self::LOG_LEVEL_COLORS[$level->value];

        $datePrefix = sprintf("\033[38;2;156;156;156m[%s]\033[0m", date('c'));
        $prefix = sprintf(
            "\033[38;2;%d;%d;%dm%s\033[0m",
            $color[0],
            $color[1],
            $color[2],
            $this->getPrefix($level)
        );
        $formattedMessage = "{$datePrefix} {$prefix}{$message}";

        $logMethod = $this->logMethod ?? function(string $msg): void {
            echo $msg . PHP_EOL;
        };

        $logMethod($formattedMessage);

        if ($this->onLog !== null) {
            ($this->onLog)($level, $message);
        }
    }

    private function getPrefix(LogLevel $level): string
    {
        if ($this->prefix !== null) {
            return ($this->prefix)($level);
        }

        return strtoupper($level->value) . ' ';
    }

    private function convertToStrings(array $data): array
    {
        return array_map(function ($item): string {
            if (is_string($item)) {
                return $item;
            }

            if (is_scalar($item)) {
                return (string) $item;
            }

            if (is_array($item) || is_object($item)) {
                return json_encode($item, JSON_THROW_ON_ERROR);
            }

            return var_export($item, true);
        }, $data);
    }
}
