<?php

declare(strict_types=1);

namespace Logdash\Tests;

use Logdash\Logdash;
use Logdash\Types\LogLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LogdashTest extends TestCase
{
    private const UUID_V4 = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';

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

    public function testLoggingInvalidUtf8InAnArrayDoesNotThrow(): void
    {
        $logdash = Logdash::create(['apiKey' => 'test-api-key', 'host' => self::$host]);

        $logdash->logger()->info('upload', ['file' => "caf\xe9.txt"]);

        $this->assertSame(
            ['upload {"file":"caf\\ufffd.txt"}'],
            array_column($this->receivedBodies('POST /logs'), 'message')
        );
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
    public function testLogsMetricsAndEventsCarryApp(string $app, string $sentApp): void
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
        $logdash->track('agent_run');

        $this->assertSame([$sentApp, $sentApp], array_column($this->receivedBodies('POST /logs'), 'app'));
        $this->assertSame([$sentApp, $sentApp], array_column($this->receivedBodies('PUT /metrics'), 'app'));
        $this->assertSame([$sentApp], array_column($this->receivedBodies('POST /events'), 'app'));
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
    public function testLogsMetricsAndEventsOmitAppWhenUnset(array $appParams): void
    {
        $logdash = Logdash::create(['apiKey' => 'test-api-key', 'host' => self::$host] + $appParams);

        $logdash->logger()->info('Test info');
        $logdash->metrics()->set('test_metric', 42.5);
        $logdash->track('agent_run');

        $logs = $this->receivedBodies('POST /logs');
        $metrics = $this->receivedBodies('PUT /metrics');
        $events = $this->receivedBodies('POST /events');

        $this->assertCount(1, $logs);
        $this->assertArrayNotHasKey('app', $logs[0]);
        $this->assertCount(1, $metrics);
        $this->assertArrayNotHasKey('app', $metrics[0]);
        $this->assertCount(1, $events);
        $this->assertArrayNotHasKey('app', $events[0]);
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

    public function testTrackPostsTheEvent(): void
    {
        $logdash = Logdash::create(['apiKey' => 'test-api-key', 'host' => self::$host]);

        $logdash->track('agent_run', ['agent' => 'support', 'steps' => 4, 'success' => true]);

        $events = $this->receivedRequests('POST /events');

        $this->assertCount(1, $events);
        $this->assertSame('test-api-key', $events[0]['headers']['project-api-key']);
        $this->assertSame('application/json', $events[0]['headers']['content-type']);
        $this->assertMatchesRegularExpression(self::UUID_V4, $events[0]['body']['id']);
        $this->assertSame(
            ['name' => 'agent_run', 'props' => ['agent' => 'support', 'steps' => 4, 'success' => true]],
            array_diff_key($events[0]['body'], ['id' => true])
        );
    }

    public function testEveryEventGetsItsOwnId(): void
    {
        $logdash = Logdash::create(['apiKey' => 'test-api-key', 'host' => self::$host]);

        $logdash->track('agent_run');
        $logdash->track('agent_run');

        $ids = array_column($this->receivedBodies('POST /events'), 'id');

        $this->assertCount(2, array_unique($ids));
    }

    /**
     * @param array{userId?: string|int} $sentUserId
     */
    #[DataProvider('userIds')]
    public function testEventsCarryUserId(string|int|null $userId, array $sentUserId): void
    {
        $logdash = Logdash::create(['apiKey' => 'test-api-key', 'host' => self::$host]);

        $logdash->track('agent_run', [], $userId);

        $events = $this->receivedBodies('POST /events');

        $this->assertCount(1, $events);
        $this->assertSame(['name' => 'agent_run'] + $sentUserId, array_diff_key($events[0], ['id' => true]));
    }

    /**
     * @return array<string, array{string|int|null, array{userId?: string|int}}>
     */
    public static function userIds(): array
    {
        return [
            'string' => ['42', ['userId' => '42']],
            'number' => [42, ['userId' => 42]],
            'zero' => [0, ['userId' => 0]],
            'empty' => ['', []],
            'none' => [null, []],
        ];
    }

    #[DataProvider('invalidEvents')]
    public function testInvalidEventsAreDroppedWithAWarning(string $name, ?string $userId): void
    {
        $printed = $this->printedBy(sprintf(
            'Logdash\Logdash::create(%s)->track(%s, [], %s);',
            var_export(['apiKey' => 'test-api-key', 'host' => self::$host], true),
            var_export($name, true),
            var_export($userId, true)
        ));

        $this->assertStringContainsString('WARNING', $printed);
        $this->assertStringContainsString('Dropping event', $printed);
        $this->assertStringNotContainsString('alice@example.com', $printed);
        $this->assertSame([], $this->receivedRequests('POST /events'));
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function invalidEvents(): array
    {
        return [
            'uppercase and space' => ['Agent run', null],
            'empty' => ['', null],
            'leading digit' => ['9lives', null],
            'over 64 characters' => [str_repeat('a', 65), null],
            'trailing newline' => ["agent_run\n", null],
            'pageview' => ['pageview', null],
            'pageleave' => ['pageleave', null],
            'browser_error' => ['browser_error', null],
            'email as user ID' => ['agent_run', 'alice@example.com'],
        ];
    }

    public function testTrackWithoutApiKeyPrintsTheEventLocallyOnly(): void
    {
        $printed = $this->printedBy(sprintf(
            'Logdash\Logdash::create(%s)->track("agent_run", ["steps" => 4], 42);',
            var_export(['host' => self::$host], true)
        ));

        $this->assertStringContainsString('Event {"name":"agent_run","props":{"steps":4},"userId":42}', $printed);
        $this->assertSame([], $this->receivedRequests('POST /events'));
    }

    public function testTrackDoesNotThrowWhenTheApiIsUnreachable(): void
    {
        $logdash = Logdash::create(['apiKey' => 'test-api-key', 'host' => 'http://127.0.0.1:1']);

        $logdash->track('agent_run');

        $this->assertTrue(true); // If we get here, no exceptions were thrown
    }

    /**
     * @return list<array<string, mixed>> JSON bodies the local server received for $request, such as "POST /logs"
     */
    private function receivedBodies(string $request): array
    {
        return array_column($this->receivedRequests($request), 'body');
    }

    /**
     * @return list<array{request: string, headers: array<string, string>, body: array<string, mixed>}>
     *     Requests the local server received for $request, such as "POST /events", with lowercase header names
     */
    private function receivedRequests(string $request): array
    {
        $requests = [];

        foreach (file(self::$requestsFile, FILE_IGNORE_NEW_LINES) as $line) {
            $received = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

            if ($received['request'] === $request) {
                $requests[] = $received;
            }
        }

        return $requests;
    }

    /**
     * Runs $code in a new PHP process, like an app that uses the SDK
     *
     * @return string What the process printed
     */
    private function printedBy(string $code): string
    {
        $autoload = var_export(dirname(__DIR__) . '/vendor/autoload.php', true);
        $process = proc_open([PHP_BINARY, '-r', "require {$autoload}; {$code}"], [1 => ['pipe', 'w']], $pipes);
        $printed = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($process);

        return $printed;
    }
}
