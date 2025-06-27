# Quick Setup Guide

This guide will help you get started with the LogDash PHP SDK in under 5 minutes.

## Prerequisites

- PHP 8.1 or higher
- Composer

## Installation

1. **Install the package via Composer:**
   ```bash
   composer require logdash/php-sdk
   ```

2. **Create your first LogDash instance:**
   ```php
   <?php
   require_once 'vendor/autoload.php';
   
   use LogDash\LogDash;
   
   // For local logging only
   $logdash = LogDash::create();
   $logger = $logdash->logger();
   
   $logger->info('Hello, LogDash!');
   ```

3. **Add cloud synchronization (optional):**
   - Get your API key from [logdash.io](https://logdash.io)
   - Update your code:
   ```php
   $logdash = LogDash::create([
       'apiKey' => 'your-api-key-here'
   ]);
   ```

## Framework Integration

### Laravel

Add to your `AppServiceProvider`:

```php
use LogDash\LogDash;

public function register()
{
    $this->app->singleton('logdash', function () {
        return LogDash::create([
            'apiKey' => config('services.logdash.api_key'),
            'verbose' => config('app.debug')
        ]);
    });
}
```

Add to `config/services.php`:
```php
'logdash' => [
    'api_key' => env('LOGDASH_API_KEY'),
],
```

Usage in controllers:
```php
public function store(Request $request)
{
    $logdash = app('logdash');
    $logdash->logger()->info('Creating new record');
    $logdash->metrics()->mutate('records_created', 1);
    
    // Your logic here...
}
```

### Symfony

Add to `config/services.yaml`:
```yaml
services:
    logdash:
        class: LogDash\LogDash
        factory: ['LogDash\LogDash', 'create']
        arguments:
            - apiKey: '%env(LOGDASH_API_KEY)%'
              verbose: '%kernel.debug%'
```

Usage in controllers:
```php
use LogDash\LogDash;

class MyController extends AbstractController
{
    public function index(LogDash $logdash): Response
    {
        $logdash->logger()->info('Controller action executed');
        $logdash->metrics()->set('page_views', 42);
        
        return $this->render('template.html.twig');
    }
}
```

## Environment Variables

Create a `.env` file:
```bash
LOGDASH_API_KEY=your-api-key-here
LOGDASH_HOST=https://api.logdash.io
LOGDASH_VERBOSE=false
```

## Testing

Run the examples:
```bash
php examples.php
```

Run the test script:
```bash
php test.php
```

## Next Steps

1. Explore the [examples.php](examples.php) file for more usage patterns
2. Check out the [full documentation](README.md)
3. Visit [logdash.io](https://logdash.io) to set up your project dashboard

## Troubleshooting

- **No output when logging**: Make sure you're calling the logger methods correctly
- **API errors**: Check your API key and network connectivity
- **Composer autoload issues**: Run `composer dump-autoload`

For more help, visit [docs.logdash.io](https://docs.logdash.io) or contact support.
