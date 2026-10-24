# Logdash PHP SDK

[![Packagist Version](https://img.shields.io/packagist/v/logdash/php-sdk)](https://packagist.org/packages/logdash/php-sdk)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.1+-blue.svg)](https://php.net)
[![Tests](https://github.com/logdash-io/php-sdk/workflows/Tests/badge.svg)](https://github.com/logdash-io/php-sdk/actions)

> **Official PHP SDK for [Logdash.io](https://logdash.io/) - Zero-configuration observability platform designed for developers working on side projects and prototypes.**

## Why Logdash?

Most observability solutions feel overwhelming for small projects and prototypes. Logdash provides **instant logging and real-time metrics** without complex configurations. Just add the SDK and start monitoring your application immediately.

## Key Features

- **🚀 Zero Configuration**: Start logging and tracking metrics in seconds
- **📊 Real-time Dashboard**: Cloud-hosted interface with live data updates
- **📝 Structured Logging**: Multiple log levels with rich context support
- **📈 Custom Metrics**: Track counters, gauges, and business metrics effortlessly
- **⚡ Asynchronous**: Non-blocking operations with automatic resource management
- **🛡️ Production Ready**: Built with enterprise-grade patterns and error handling
- **🔧 Framework Agnostic**: Works with Laravel, Symfony, or standalone PHP apps
- **🐘 PHP 8.1+ Compatible**: Supports PHP 8.1, 8.2, 8.3, and all newer versions

## Pre-requisites

Setup your free project in less than 2 minutes at [logdash.io](https://logdash.io/)

## Installation

### Composer

```bash
composer require logdash/php-sdk
```

## Quick Start

### Basic Logging (Local Only)

```php
<?php

require_once 'vendor/autoload.php';

use Logdash\Logdash;

// Create Logdash instance without API key for local logging only
$logdash = Logdash::create();
$logger = $logdash->logger();

// Log different levels
$logger->error('This is an error message');
$logger->warn('This is a warning message');
$logger->info('This is an info message');
$logger->debug('This is a debug message');
```

### Cloud Logging & Metrics

```php
<?php

require_once 'vendor/autoload.php';

use Logdash\Logdash;

// Create Logdash instance with API key for cloud sync
$logdash = Logdash::create([
    'apiKey' => 'your-api-key-here',
    'host' => 'https://api.logdash.io', // Optional, defaults to this
    'verbose' => true // Optional, for debugging
]);

$logger = $logdash->logger();
$metrics = $logdash->metrics();

// Logging with cloud sync
$logger->error('Application error occurred');
$logger->info('User logged in successfully');

// Custom metrics
$metrics->set('active_users', 150);
$metrics->mutate('login_count', 1); // Increment by 1
$metrics->mutate('error_count', -1); // Decrement by 1
```

### Apps

A domain has one API key, the domain key, shared by all apps of that domain, for example a desktop app, an API and a landing page.
Set `app` to name this one, so the dashboard can filter logs and metrics by app:

```php
$logdash = Logdash::create([
    'apiKey' => 'your-domain-key',
    'app' => 'api'
]);
```

Every log, metric and event sent by this instance carries the app.
Older service keys ignore `app`.

### Framework Integration

#### Laravel

```php
// In your AppServiceProvider or custom service provider
use Logdash\Logdash;

public function register()
{
    $this->app->singleton('logdash', function ($app) {
        return Logdash::create([
            'apiKey' => config('services.logdash.api_key'),
            'verbose' => config('app.debug')
        ]);
    });
}

// Usage in controllers/services
public function someMethod()
{
    $logdash = app('logdash');
    $logdash->logger()->info('Operation completed');
    $logdash->metrics()->set('operation_count', 42);
}
```

#### Symfony

```php
// In your services.yaml
services:
    logdash:
        class: Logdash\Logdash
        factory: ['Logdash\Logdash', 'create']
        arguments:
            - apiKey: '%env(LOGDASH_API_KEY)%'
              verbose: '%kernel.debug%'

// Usage in controllers/services
public function someAction(Logdash $logdash)
{
    $logdash->logger()->info('Action executed');
    $logdash->metrics()->mutate('action_count', 1);
}
```

## API Reference

### Logdash

#### `Logdash::create(array $params = []): Logdash`

Creates a new Logdash instance.

**Parameters:**

- `apiKey` (string, optional): Your Logdash API key. If not provided, logs and events are only printed locally
- `host` (string, optional): Logdash API host. Defaults to `https://api.logdash.io`
- `verbose` (bool, optional): Enable verbose internal logging. Defaults to `false`
- `app` (string, optional): Name of this app within its domain, sent with every log, metric and event. See [Apps](#apps)

### Logger

The logger provides methods for different log levels:

- `error(...$data)`: Log error level messages
- `warn(...$data)`: Log warning level messages
- `info(...$data)`: Log info level messages
- `log(...$data)`: Alias for info level
- `http(...$data)`: Log HTTP-related messages
- `verbose(...$data)`: Log verbose messages
- `debug(...$data)`: Log debug messages
- `silly(...$data)`: Log silly level messages

### Metrics

#### `set(string $name, float $value): void`

Set a metric to a specific value.

```php
$metrics->set('cpu_usage', 45.2);
$metrics->set('memory_usage', 1024);
```

#### `mutate(string $name, float $value): void`

Change a metric by the specified amount (can be positive or negative).

```php
$metrics->mutate('request_count', 1);    // Increment
$metrics->mutate('error_count', -1);     // Decrement
$metrics->mutate('temperature', 2.5);    // Increase by 2.5
```

### Events

#### `track(string $name, array $props = [], string|int|null $userId = null): void`

Track an event, such as an agent run or a signup.

```php
$logdash->track('agent_run', [
    'agent' => 'support',
    'steps' => 4,
    'success' => true,
], $user->id);
```

Events show up in the domain's Analytics as goals, with a daily trend.
Pass the user ID for signed-in users, so one person counts once across the website and apps.
Without it, Logdash counts a visitor of that app that changes every day.
Never put personal data in props.

**Parameters:**

- `name` (string): The event name, matching `[a-z][a-z0-9_]{0,63}`, except `pageview`, `pageleave` and `browser_error`, which belong to the website tracker. An invalid name drops the event with a warning
- `props` (array, optional): Up to 10 properties with string, number or boolean values
- `userId` (string|int, optional): The signed-in user's internal ID, never an email address. An ID that contains `@` drops the event with a warning

## Log Levels

The SDK supports the following log levels (in order of severity):

1. **ERROR**: Error conditions
2. **WARN**: Warning conditions
3. **INFO**: Informational messages
4. **HTTP**: HTTP request/response logs
5. **VERBOSE**: Verbose informational messages
6. **DEBUG**: Debug-level messages
7. **SILLY**: Very detailed debug information

## Configuration

### Environment Variables

You can use environment variables for configuration:

```bash
LOGDASH_API_KEY=your-api-key-here
LOGDASH_HOST=https://api.logdash.io
LOGDASH_VERBOSE=true
```

```php
$logdash = Logdash::create([
    'apiKey' => $_ENV['LOGDASH_API_KEY'] ?? '',
    'host' => $_ENV['LOGDASH_HOST'] ?? 'https://api.logdash.io',
    'verbose' => filter_var($_ENV['LOGDASH_VERBOSE'] ?? false, FILTER_VALIDATE_BOOLEAN)
]);
```

## Error Handling

The SDK is designed to be non-blocking and fail silently in production. If there are issues with the API connection:

- Local logging will continue to work
- API errors are logged internally when verbose mode is enabled
- Your application will not be affected by Logdash connectivity issues

## Requirements

- PHP 8.1 or higher
- Guzzle HTTP client (automatically installed via Composer)

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run static analysis
composer phpstan

# Check code style
composer cs

# Fix code style
composer cbf
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE.md) file for details.

## Support

- 📧 Email: [support@logdash.io](mailto:support@logdash.io)
- 🌐 Website: [logdash.io](https://logdash.io)
- 📚 Documentation: [docs.logdash.io](https://docs.logdash.io)
- 🐛 Issues: [GitHub Issues](https://github.com/logdash-io/php-sdk/issues)
