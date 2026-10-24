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

// Answers with the status on the first line of LOGDASH_STATUSES_FILE, which it removes, or 200 when there is none
$statuses = file(getenv('LOGDASH_STATUSES_FILE'), FILE_IGNORE_NEW_LINES);
http_response_code((int) (array_shift($statuses) ?? 200));
file_put_contents(getenv('LOGDASH_STATUSES_FILE'), implode(PHP_EOL, $statuses));
