<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use LogDash\LogDash;

// Test basic local logging (no API key)
echo "=== Testing Local Logging ===\n";
$logdash = LogDash::create();
$logger = $logdash->logger();

$logger->error('This is an error message');
$logger->warn('This is a warning message');
$logger->info('This is an info message');
$logger->http('This is an http message');
$logger->verbose('This is a verbose message');
$logger->debug('This is a debug message');
$logger->silly('This is a silly message');

echo "\n=== Testing Cloud Sync (with API key) ===\n";
// Test with API key (cloud sync)
$syncLogdash = LogDash::create([
    'apiKey' => 'MY_API_KEY',
    'verbose' => true
]);

$syncLogger = $syncLogdash->logger();
$metrics = $syncLogdash->metrics();

$syncLogger->error('This is a SYNCED error message');
$syncLogger->info('This is a SYNCED info message');

// Test metrics
$metrics->set('test_metric', 42.5);
$metrics->mutate('counter_metric', 1);
$metrics->mutate('decrement_metric', -1);

echo "\n=== Test completed ===\n";
