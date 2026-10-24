<?php

declare(strict_types=1);

namespace Logdash\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
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
    private static string $statusesFile;

    public static function setUpBeforeClass(): void
    {
        // A local server that records every request the SDK sends to it, see tests/server.php
        self::$requestsFile = tempnam(sys_get_temp_dir(), 'logdash');
        self::$statusesFile = tempnam(sys_get_temp_dir(), 'logdash');
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        self::$host = "http://{$address}";
        self::$server = proc_open(
            [PHP_BINARY, '-S', $address, __DIR__ . '/server.php'],
            [['null'], ['null'], ['null']],
            $pipes,
            null,
            ['LOGDASH_REQUESTS_FILE' => self::$requestsFile, 'LOGDASH_STATUSES_FILE' => self::$statusesFile] + getenv()
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
        unlink(self::$statusesFile);
    }

    protected function setUp(): void
    {
        file_put_contents(self::$requestsFile, '');
        file_put_contents(self::$statusesFile, '');
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

    public function testInvalidUtf8NeitherThrowsNorCostsTheBatch(): void
    {
        $logdash = Logdash::create(['apiKey' => 'test-api-key', 'host' => self::$host]);

        $logdash->logger()->info('upload', ['file' => "caf\xe9.txt"]);
        $logdash->logger()->info("caf\xe9.txt");
        $logdash->logger()->info('done');
        $logdash->flush();

        $this->assertSame(
            ['upload {"file":"caf\\ufffd.txt"}', "caf\u{fffd}.txt", 'done'],
            array_column($this->sentLogs(), 'message')
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

    public function testNothingIsSentBeforeAFlush(): void
    {
        $logdash = Logdash::create(['apiKey' => 'test-api-key', 'host' => self::$host]);

        $logdash->logger()->info('Test info');
        $logdash->metrics()->set('test_metric', 42.5);
        $logdash->track('agent_run');

        $this->assertSame('', file_get_contents(self::$requestsFile));

        $logdash->flush();

        $this->assertCount(1, $this->sentLogs());
        $this->assertCount(1, $this->receivedRequests('PUT /metrics'));
        $this->assertCount(1, $this->receivedRequests('POST /events'));
    }

    public function testLogsGoInOneBatchInTheirOrder(): void
    {
        $logdash = Logdash::create(['apiKey' => 'test-api-key', 'host' => self::$host]);

        $logdash->logger()->info('first');
        $logdash->logger()->error('second');
        $logdash->logger()->warn('third');
        $logdash->flush();

        $batches = $this->receivedRequests('POST /logs/batch');

        $this->assertCount(1, $batches);
        $this->assertSame('test-api-key', $batches[0]['headers']['project-api-key']);
        $this->assertSame('application/json', $batches[0]['headers']['content-type']);
        $this->assertSame(
            [['first', 'info', 0], ['second', 'error', 1], ['third', 'warning', 2]],
            array_map(
                fn (array $log): array => [$log['message'], $log['level'], $log['sequenceNumber']],
                $batches[0]['body']['logs']
            )
        );
    }

    public function testAScriptSendsWhatItBufferedWhenItEnds(): void
    {
        $this->printedBy(sprintf(
            '$logdash = Logdash\Logdash::create(%s);
            for ($i = 0; $i <= 100; $i++) {
                $logdash->logger()->info("log {$i}");
            }
            $logdash->metrics()->mutate("test_counter", 1);
            $logdash->track("agent_run");',
            var_export(['apiKey' => 'test-api-key', 'host' => self::$host], true)
        ));

        // The first 100 fill a batch and go at once, the rest when the script ends
        $this->assertSame(
            [100, 1],
            array_map(fn (array $body): int => count($body['logs']), $this->receivedBodies('POST /logs/batch'))
        );
        $this->assertCount(1, $this->receivedRequests('PUT /metrics'));
        $this->assertCount(1, $this->receivedRequests('POST /events'));
    }

    public function testALogFromALaterShutdownFunctionIsSentToo(): void
    {
        $this->printedBy(sprintf(
            '$logger = Logdash\Logdash::create(%s)->logger();
            $logger->info("before");
            register_shutdown_function(fn () => $logger->error("from a shutdown function"));',
            var_export(['apiKey' => 'test-api-key', 'host' => self::$host], true)
        ));

        $this->assertSame(['before', 'from a shutdown function'], array_column($this->sentLogs(), 'message'));
    }

    public function testALogASecondAfterTheLastSendGoesAtOnce(): void
    {
        $logdash = Logdash::create(['apiKey' => 'test-api-key', 'host' => self::$host]);

        usleep(1_000_000);
        $logdash->logger()->info('after a second idle');

        // A worker that logs once in a while sends each log, rather than keeping it until the next one
        $this->assertSame(['after a second idle'], array_column($this->sentLogs(), 'message'));

        $logdash->logger()->info('right after');

        $this->assertSame(['after a second idle'], array_column($this->sentLogs(), 'message'));
    }

    public function testBatchesStayUnderTheApiBodyLimit(): void
    {
        $logdash = Logdash::create(['apiKey' => 'test-api-key', 'host' => self::$host]);

        // 4 bytes each, and the API keeps the first 4096 characters
        for ($i = 0; $i < 100; $i++) {
            $logdash->logger()->info(str_repeat("\u{1F600}", 5000));
        }
        $logdash->flush();

        $batches = $this->receivedBodies('POST /logs/batch');

        $this->assertGreaterThan(1, count($batches));
        $this->assertCount(100, $this->sentLogs());

        foreach ($batches as $batch) {
            $this->assertLessThan(2_000_000, strlen(json_encode($batch, JSON_UNESCAPED_UNICODE)));
        }

        foreach ($this->sentLogs() as $log) {
            $this->assertSame(str_repeat("\u{1F600}", 4096), $log['message']);
        }
    }

    public function testAMessageIsCutAt4096CharactersAndKeepsBadUtf8ForSubstitution(): void
    {
        $logdash = Logdash::create(['apiKey' => 'test-api-key', 'host' => self::$host]);

        $logdash->logger()->info(str_repeat('a', 5000));
        $logdash->logger()->info("\xe9" . str_repeat('é', 5000));
        $logdash->flush();

        $this->assertSame(
            [str_repeat('a', 4096), "\u{fffd}" . str_repeat('é', 4095)],
            array_column($this->sentLogs(), 'message')
        );
    }

    public function testAnHttpClientCanBeInjected(): void
    {
        $history = [];
        $handler = HandlerStack::create(new MockHandler([new Response(200)]));
        $handler->push(Middleware::history($history));
        $logdash = Logdash::create([
            'apiKey' => 'test-api-key',
            'host' => 'https://test.logdash.io',
            'httpClient' => new Client(['handler' => $handler]),
        ]);

        $logdash->logger()->info('Test info');
        $logdash->flush();

        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('https://test.logdash.io/logs/batch', (string) $request->getUri());
        $this->assertSame('test-api-key', $request->getHeaderLine('project-api-key'));
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
    }

    public function testAForkedChildDoesNotSendItsParentsBuffer(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('Needs pcntl');
        }

        $this->printedBy(sprintf(
            '$logdash = Logdash\Logdash::create(%s);
            $logdash->metrics()->mutate("jobs", 1);
            if (pcntl_fork() === 0) {
                $logdash->logger()->info("from the child");
                exit;
            }
            pcntl_wait($status);',
            var_export(['apiKey' => 'test-api-key', 'host' => self::$host], true)
        ));

        $this->assertSame(['from the child'], array_column($this->sentLogs(), 'message'));
        $this->assertCount(1, $this->receivedRequests('PUT /metrics'), 'The parent sends its mutate once');
    }

    public function testMetricUpdatesMergeTheWayTheApiMergesThem(): void
    {
        $logdash = Logdash::create(['apiKey' => 'test-api-key', 'host' => self::$host]);
        $metrics = $logdash->metrics();

        $metrics->set('users', 5);
        $metrics->mutate('users', 1);
        $metrics->mutate('users', 2);
        $metrics->mutate('logins', 1);
        $metrics->mutate('logins', 2);
        $metrics->mutate('errors', 1);
        $metrics->set('errors', 0);
        $logdash->flush();

        // One request per metric, so its updates cannot arrive out of order
        $this->assertEquals(
            [
                'users' => ['name' => 'users', 'value' => 8, 'operation' => 'set'],
                'logins' => ['name' => 'logins', 'value' => 3, 'operation' => 'change'],
                'errors' => ['name' => 'errors', 'value' => 0, 'operation' => 'set'],
            ],
            array_column($this->receivedBodies('PUT /metrics'), null, 'name')
        );
    }

    public function testAMetricOrEventThatJsonCannotCarryCostsNothingElse(): void
    {
        $logdash = Logdash::create(['apiKey' => 'test-api-key', 'host' => self::$host]);

        $logdash->logger()->info('Test info');
        $logdash->metrics()->set('broken', INF);
        $logdash->metrics()->mutate('test_counter', 1);
        $logdash->track('agent_run', ['ratio' => NAN]);
        $logdash->track('agent_run');
        $logdash->flush();

        $this->assertCount(1, $this->sentLogs());
        $this->assertSame(['test_counter'], array_column($this->receivedBodies('PUT /metrics'), 'name'));
        $this->assertCount(1, $this->receivedRequests('POST /events'));
    }

    #[DataProvider('failures')]
    public function testLogsAndEventsGetOneRetryForAFailureThatCanPassAndMetricsNone(int $status, int $attempts): void
    {
        file_put_contents(self::$statusesFile, implode(PHP_EOL, [$status, $status, $status]));
        $logdash = Logdash::create(['apiKey' => 'test-api-key', 'host' => self::$host]);

        $logdash->logger()->info('Test info');
        $logdash->metrics()->mutate('test_counter', 1);
        $logdash->track('agent_run');
        $logdash->flush();

        $events = $this->receivedBodies('POST /events');

        $this->assertCount($attempts, $this->receivedRequests('POST /logs/batch'));
        $this->assertCount(1, $this->receivedRequests('PUT /metrics'), 'A retried change could apply twice');
        $this->assertCount($attempts, $events);
        $this->assertCount(1, array_unique(array_column($events, 'id')), 'A retry sends the same event ID');
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function failures(): array
    {
        return [
            'server error' => [500, 2],
            'rate limit' => [429, 1],
        ];
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
        $logdash->flush();

        $this->assertSame([$sentApp, $sentApp], array_column($this->sentLogs(), 'app'));
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
        $logdash->flush();

        $logs = $this->sentLogs();
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
        $logdash->flush();

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
        $logdash->flush();

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
        $logdash->flush();

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

    #[DataProvider('unreachableHosts')]
    public function testFlushDoesNotThrowWhenTheApiIsUnreachable(string $host): void
    {
        $logdash = Logdash::create(['apiKey' => 'test-api-key', 'host' => $host]);

        $logdash->logger()->info('Test info');
        $logdash->metrics()->set('test_metric', 42.5);
        $logdash->track('agent_run');
        $logdash->flush();

        $this->assertTrue(true); // If we get here, no exceptions were thrown
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unreachableHosts(): array
    {
        return [
            'closed port' => ['http://127.0.0.1:1'],
            'no URL' => ['http://:80'],
        ];
    }

    /**
     * @return list<array<string, mixed>> The logs of every batch the local server received
     */
    private function sentLogs(): array
    {
        return array_merge(...array_column($this->receivedBodies('POST /logs/batch'), 'logs'));
    }

    /**
     * @return list<array<string, mixed>> JSON bodies the local server received for $request, such as "PUT /metrics"
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
