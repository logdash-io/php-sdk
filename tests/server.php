<?php

declare(strict_types=1);

// Router for the PHP built-in server that LogdashTest starts: appends "METHOD /path body" for every request
file_put_contents(
    getenv('LOGDASH_REQUESTS_FILE'),
    "{$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']} " . file_get_contents('php://input') . PHP_EOL,
    FILE_APPEND
);
