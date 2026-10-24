<?php

declare(strict_types=1);

// Sends logs and metrics with the logdash/php-sdk that the Dockerfile installs, then reads them back from Logdash
require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use Logdash\Logdash;

function fail(string $message): never
{
    fwrite(STDERR, "Error: {$message}" . PHP_EOL);
    exit(1);
}

// Such as a 401 from whoami for a personal API key that is wrong, or revoked
set_exception_handler(fn (Throwable $e) => fail($e->getMessage()));

$apiKey = getenv('LOGDASH_API_KEY') ?: fail('Set LOGDASH_API_KEY to the ingest key of the SDK smoke tests domain');
$personalApiKey = getenv('LOGDASH_PERSONAL_API_KEY') ?: fail(
    'Set LOGDASH_PERSONAL_API_KEY to an ldp_ key with logs:read and metrics:read, restricted to the SDK smoke tests domain'
);

// Each SDK sends as its own app on the shared smoke-test domain
$app = 'php-sdk';
$seed = bin2hex(random_bytes(8));
$users = random_int(1, 1_000_000);

echo "=== Logdash PHP SDK package check ===" . PHP_EOL;
echo "Sending a log of each level with seed {$seed}, setting users to {$users} and adding 1" . PHP_EOL;

$logdash = Logdash::create(['apiKey' => $apiKey, 'verbose' => true, 'app' => $app]);
$sent = [];

foreach (
    [
        'info' => 'info',
        'error' => 'error',
        'warn' => 'warning',
        'debug' => 'debug',
        'http' => 'http',
        'silly' => 'silly',
        'verbose' => 'verbose',
    ] as $method => $level
) {
    $message = "This is the {$level} log {$seed}";
    $logdash->logger()->{$method}($message);
    $sent[$message] = $level;
}

$logdash->metrics()->set('users', $users);
// Buffered together, a set and a mutate merge into one set, and the API would never get a change
$logdash->flush();
$logdash->metrics()->mutate('users', 1);
// The end of this script would send them too, but they must be there to read back
$logdash->flush();

$api = new Client([
    'base_uri' => 'https://api.logdash.io',
    'headers' => ['Authorization' => "Bearer {$personalApiKey}"],
    'timeout' => 10,
]);
$get = fn (string $path, array $query = []): array => json_decode(
    (string) $api->get($path, ['query' => $query])->getBody(),
    true,
    512,
    JSON_THROW_ON_ERROR
);

// The key reads only the smoke-test domain, which whoami names
$access = $get('/personal-api-keys/whoami')['access'];

if (($access['kind'] ?? null) !== 'clusters' || count($access['ids'] ?? []) !== 1) {
    fail('LOGDASH_PERSONAL_API_KEY must be restricted to the smoke-test domain, its access is ' . json_encode($access));
}

$domainId = $access['ids'][0];
echo "Reading them back from app {$app} on domain {$domainId}" . PHP_EOL;

// The API stores what it gets every second
for ($attempt = 1; $attempt <= 30; $attempt++) {
    sleep(2);
    $logs = $get("/clusters/{$domainId}/logs", ['apps' => $app, 'limit' => 100, 'searchString' => $seed]);
    $found = array_column($logs, 'level', 'message');
    $metrics = array_filter($get("/clusters/{$domainId}/metrics"), fn (array $m) => $m['app'] === $app);
    $value = array_column($metrics, 'value', 'name')['users'] ?? null;

    if ($found == $sent && $value == $users + 1) {
        echo "Found every log with its level, and users at {$value}" . PHP_EOL;
        exit(0);
    }
}

$missing = array_keys(array_diff_assoc($sent, $found));

fail('After a minute, Logdash ' . implode(' and ', array_filter([
    $missing === [] ? '' : 'lacks these logs at their level: ' . json_encode($missing),
    $value == $users + 1 ? '' : 'has users at ' . var_export($value, true) . ' instead of ' . ($users + 1),
])));
