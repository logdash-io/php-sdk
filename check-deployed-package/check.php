<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Logdash\Logdash;

echo "=== Logdash PHP SDK package check ===" . PHP_EOL;

$apiKey = $_ENV['LOGDASH_API_KEY'] ?? null;
$logsSeed = $_ENV['LOGS_SEED'] ?? 'default';
$metricsSeed = $_ENV['METRICS_SEED'] ?? '1';

echo "Using API Key: " . ($apiKey ? '[REDACTED]' : 'none') . PHP_EOL;
echo "Using Logs Seed: {$logsSeed}" . PHP_EOL;
echo "Using Metrics Seed: {$metricsSeed}" . PHP_EOL;

// Create Logdash instance
$logdash = Logdash::create($apiKey ? [
    'apiKey' => $apiKey,
    'verbose' => true
] : []);

$logger = $logdash->logger();
$metrics = $logdash->metrics();

// Log messages with seed appended
$logger->info("This is an info log {$logsSeed}");
$logger->error("This is an error log {$logsSeed}");
$logger->warn("This is a warning log {$logsSeed}");
$logger->debug("This is a debug log {$logsSeed}");
$logger->http("This is a http log {$logsSeed}");
$logger->silly("This is a silly log {$logsSeed}");
$logger->verbose("This is a verbose log {$logsSeed}");

// Set and mutate metrics with seed
$metricsSeedValue = (float) $metricsSeed;
$metrics->set('users', $metricsSeedValue);
$metrics->mutate('users', 1.0);

// Wait to ensure data is sent
echo "Waiting 5 seconds to ensure data is sent..." . PHP_EOL;
sleep(5);

echo "Demo completed!" . PHP_EOL;
