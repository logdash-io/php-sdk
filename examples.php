<?php

/**
 * Logdash PHP SDK Examples
 * 
 * This file demonstrates various usage patterns of the Logdash PHP SDK.
 * Run this file with: php examples.php
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Logdash\Logdash;

echo "🚀 Logdash PHP SDK Examples\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// Example 1: Basic Local Logging
echo "📝 Example 1: Local Logging (No API Key)\n";
echo "-" . str_repeat("-", 40) . "\n";

$logdash = Logdash::create();
$logger = $logdash->logger();

$logger->error('Application encountered an error');
$logger->warn('This is a warning message');
$logger->info('User logged in successfully');
$logger->debug('Debugging information');
$logger->verbose('Verbose logging message');
$logger->silly('Very detailed debug info');
$logger->http('HTTP request completed');

echo "\n";

// Example 2: Cloud Logging with API Key
echo "☁️  Example 2: Cloud Logging (With API Key)\n";
echo "-" . str_repeat("-", 40) . "\n";

$cloudLogdash = Logdash::create([
    'apiKey' => 'your-api-key-here',
    'host' => 'https://api.logdash.io',
    'verbose' => true // Enable verbose mode for debugging
]);

$cloudLogger = $cloudLogdash->logger();
$metrics = $cloudLogdash->metrics();

$cloudLogger->info('This message will be sent to Logdash cloud');
$cloudLogger->error('Critical error - needs immediate attention');

// Metrics examples
$metrics->set('active_users', 150);
$metrics->set('cpu_usage', 75.5);
$metrics->mutate('login_count', 1); // Increment
$metrics->mutate('error_count', -1); // Decrement

echo "\n";

// Example 3: Complex Data Logging
echo "📊 Example 3: Complex Data Logging\n";
echo "-" . str_repeat("-", 40) . "\n";

$data = [
    'user_id' => 12345,
    'action' => 'purchase',
    'amount' => 99.99,
    'items' => ['item1', 'item2', 'item3']
];

$logger->info('User action:', json_encode($data));
$logger->error('Database connection failed', 'Connection timeout after 30s');

echo "\n";

// Example 4: Environment-based Configuration
echo "🔧 Example 4: Environment Configuration\n";
echo "-" . str_repeat("-", 40) . "\n";

// Simulate environment variables
$_ENV['LOGDASH_API_KEY'] = 'env-api-key';
$_ENV['LOGDASH_VERBOSE'] = 'true';

$envLogdash = Logdash::create([
    'apiKey' => $_ENV['LOGDASH_API_KEY'] ?? '',
    'verbose' => filter_var($_ENV['LOGDASH_VERBOSE'] ?? false, FILTER_VALIDATE_BOOLEAN)
]);

$envLogger = $envLogdash->logger();
$envLogger->info('Using environment configuration');

echo "\n";

// Example 5: Error Handling
echo "⚠️  Example 5: Error Handling\n";
echo "-" . str_repeat("-", 40) . "\n";

try {
    // Simulate some operation that might fail
    throw new Exception('Something went wrong!');
} catch (Exception $e) {
    $logger->error('Exception caught:', $e->getMessage());
    $logger->debug('Stack trace:', $e->getTraceAsString());
}

echo "\n";

echo "✅ Examples completed!\n";
echo "\nNext steps:\n";
echo "1. Get your API key from https://logdash.io\n";
echo "2. Replace 'your-api-key-here' with your actual API key\n";
echo "3. Run this script again to see cloud synchronization in action\n";
