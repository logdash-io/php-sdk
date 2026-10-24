<?php

declare(strict_types=1);

// Router for the PHP built-in server that LogdashTest starts: appends every request as a line of JSON
file_put_contents(
    getenv('LOGDASH_REQUESTS_FILE'),
    json_encode([
        'request' => "{$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']}",
        'headers' => array_change_key_case(getallheaders()),
        'body' => json_decode(file_get_contents('php://input'), true),
    ]) . PHP_EOL,
    FILE_APPEND
);
