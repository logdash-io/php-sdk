<?php

declare(strict_types=1);

namespace Logdash\Types;

enum LogLevel: string
{
    case ERROR = 'error';
    case WARN = 'warning';
    case INFO = 'info';
    case HTTP = 'http';
    case VERBOSE = 'verbose';
    case DEBUG = 'debug';
    case SILLY = 'silly';
}
