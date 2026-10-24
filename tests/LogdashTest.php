<?php

declare(strict_types=1);

namespace Logdash\Tests;

use Logdash\Logdash;
use Logdash\Types\LogLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LogdashTest extends TestCase
{
    /** @var resource */
    private static $server;
    private static string $host;
    private static string $requestsFile;

    public static function setUpBeforeClass(): void
    {
        // A local server that records every request the SDK sends to it, see tests/server.php
        self::$requestsFile = tempnam(sys_get_temp_dir(), 'logdash');
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        self::$host = "http://{$address}";
        self::$server = proc_open(
            [PHP_BINARY, '-S', $address, __DIR__ . '/server.php'],
            [['null'], ['null'], ['null']],
            $pipes,
            null,
            ['LOGDASH_REQUESTS_FILE' => self::$requestsFile] + getenv()
        );

        for ($attempt = 0; $attempt < 100 && @stream_socket_client("tcp://{$address}") === false; $attempt++) {
            usleep(50_000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        proc_terminate(self::$server);
        proc_close(self::$server);
        unlink(self::$requestsFile);
    }

    protected function setUp(): void
    {
        file_put_contents(self::$requestsFile, '');
    }

    public function testCreateWithoutApiKey(): void
    {
        $logdash = Logdash::create();

        $this->assertInstanceOf(Logdash::class, $logdash);
        $this->assertNotNull($logdash->logger());
        $this->assertNotNull($logdash->metrics());
    }

    public function testCreateWithApiKey(): void
    {
        $logdash = Logdash::create([
            'apiKey' => 'test-api-key',
            'host' => 'https://test.logdash.io',
            'verbose' => true
        ]);

        $this->assertInstanceOf(Logdash::class, $logdash);
        $this->assertNotNull($logdash->logger());
        $this->assertNotNull($logdash->metrics());
    }

    public function testLoggerMethods(): void
    {
        $logdash = Logdash::create();
        $logger = $logdash->logger();

        // Test that all log methods can be called without throwing exceptions
        $logger->error('Test error');
        $logger->warn('Test warning');
        $logger->info('Test info');
        $logger->debug('Test debug');
        $logger->verbose('Test verbose');
        $logger->silly('Test silly');
        $logger->http('Test http');

        $this->assertTrue(true); // If we get here, no exceptions were thrown
    }

    public function testMetricsMethods(): void
    {
        $logdash = Logdash::create();
        $metrics = $logdash->metrics();

        // Test that metric methods can be called without throwing exceptions
        $metrics->set('test_metric', 42.5);
        $metrics->mutate('test_counter', 1);
        $metrics->mutate('test_decrement', -1);

        $this->assertTrue(true); // If we get here, no exceptions were thrown
    }

    public function testLogLevelEnum(): void
    {
        $this->assertEquals('error', LogLevel::ERROR->value);
        $this->assertEquals('warning', LogLevel::WARN->value);
        $this->assertEquals('info', LogLevel::INFO->value);
        $this->assertEquals('http', LogLevel::HTTP->value);
        $this->assertEquals('verbose', LogLevel::VERBOSE->value);
        $this->assertEquals('debug', LogLevel::DEBUG->value);
        $this->assertEquals('silly', LogLevel::SILLY->value);
    }

    #[DataProvider('apps')]
    public function testLogsAndMetricsCarryApp(string $app, string $sentApp): void
    {
        $logdash = Logdash::create([
            'apiKey' => 'test-api-key',
            'host' => self::$host,
            'app' => $app
        ]);

        $logdash->logger()->info('Test info');
        $logdash->logger()->error('Test error');
        $logdash->metrics()->set('test_metric', 42.5);
        $logdash->metrics()->mutate('test_counter', 1);

        $this->assertSame([$sentApp, $sentApp], array_column($this->receivedBodies('POST /logs'), 'app'));
        $this->assertSame([$sentApp, $sentApp], array_column($this->receivedBodies('PUT /metrics'), 'app'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function apps(): array
    {
        return [
            'app' => ['api', 'api'],
            'padded app' => [' api ', 'api'],
        ];
    }

    /**
     * @param array{app?: string} $appParams
     */
    #[DataProvider('unsetApps')]
    public function testLogsAndMetricsOmitAppWhenUnset(array $appParams): void
    {
        $logdash = Logdash::create(['apiKey' => 'test-api-key', 'host' => self::$host] + $appParams);

        $logdash->logger()->info('Test info');
        $logdash->metrics()->set('test_metric', 42.5);

        $logs = $this->receivedBodies('POST /logs');
        $metrics = $this->receivedBodies('PUT /metrics');

        $this->assertCount(1, $logs);
        $this->assertArrayNotHasKey('app', $logs[0]);
        $this->assertCount(1, $metrics);
        $this->assertArrayNotHasKey('app', $metrics[0]);
    }

    /**
     * @return array<string, array{array{app?: string}}>
     */
    public static function unsetApps(): array
    {
        return [
            'no app' => [[]],
            'empty app' => [['app' => '']],
            'blank app' => [['app' => '  ']],
        ];
    }

    /**
     * @return list<array<string, mixed>> JSON bodies the local server received for $request, such as "POST /logs"
     */
    private function receivedBodies(string $request): array
    {
        $bodies = [];

        foreach (file(self::$requestsFile, FILE_IGNORE_NEW_LINES) as $line) {
            [$method, $path, $body] = explode(' ', $line, 3);

            if ("{$method} {$path}" === $request) {
                $bodies[] = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            }
        }

        return $bodies;
    }
}
