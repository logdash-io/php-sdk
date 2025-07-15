<?php

declare(strict_types=1);

namespace Logdash\Logger;

use Logdash\Types\LogLevel;

class InternalLogger
{
    private static self $instance;
    private const LOG_LEVEL_COLORS = [
        LogLevel::ERROR->value => [231, 0, 11],
        LogLevel::WARN->value => [254, 154, 0],
        LogLevel::INFO->value => [21, 93, 252],
        LogLevel::HTTP->value => [0, 166, 166],
        LogLevel::VERBOSE->value => [0, 166, 0],
        LogLevel::DEBUG->value => [0, 166, 0],
        LogLevel::SILLY->value => [80, 80, 80],
    ];

    public function log(string ...$data): void
    {
        $this->internalLog(LogLevel::INFO, implode(' ', $data));
    }

    public function verbose(string ...$data): void
    {
        $this->internalLog(LogLevel::VERBOSE, implode(' ', $data));
    }

    private function internalLog(LogLevel $level, string $message): void
    {
        $color = self::LOG_LEVEL_COLORS[$level->value];
        $datePrefix = sprintf("\033[38;2;156;156;156m[%s]\033[0m", date('c'));
        $levelPrefix = sprintf(
            "\033[38;2;%d;%d;%dm%s\033[0m",
            $color[0],
            $color[1],
            $color[2],
            strtoupper($level->value) . ' '
        );

        file_put_contents(
            'php://stdout',
            "{$datePrefix} {$levelPrefix}{$message}" . PHP_EOL
        );
    }

    public static function getInternalLogger(): InternalLogger
    {
        return self::$instance ??= new self();
    }
}

// Create a global instance function
