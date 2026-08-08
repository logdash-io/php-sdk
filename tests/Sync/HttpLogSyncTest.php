<?php

declare(strict_types=1);

namespace Logdash\Tests\Sync;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Logdash\Sync\HttpLogSync;
use Logdash\Types\LogLevel;
use Logdash\Types\RequiredInitializationParams;
use PHPUnit\Framework\TestCase;

class HttpLogSyncTest extends TestCase
{
    public function testMultipleLogsAreSentAsASingleBatchRequest(): void
    {
        $requests = [];
        $mock = new MockHandler([
            new Response(200),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(\GuzzleHttp\Middleware::history($requests));
        $client = new Client(['handler' => $handlerStack]);

        $params = new RequiredInitializationParams(
            apiKey: 'test-key',
            host: 'https://test.logdash.io',
            verbose: false
        );

        $logSync = new HttpLogSync($params, $client);

        $logSync->send('first', LogLevel::INFO, '2026-01-01T00:00:00+00:00');
        $logSync->send('second', LogLevel::ERROR, '2026-01-01T00:00:01+00:00');
        $logSync->flush();

        $this->assertCount(1, $requests, 'Two log() calls should result in a single HTTP request');

        $request = $requests[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/logs/batch', $request->getUri()->getPath());
        $this->assertSame('test-key', $request->getHeaderLine('project-api-key'));

        $body = json_decode((string) $request->getBody(), true);
        $this->assertCount(2, $body['logs']);
        $this->assertSame('first', $body['logs'][0]['message']);
        $this->assertSame('info', $body['logs'][0]['level']);
        $this->assertSame(0, $body['logs'][0]['sequenceNumber']);
        $this->assertSame('second', $body['logs'][1]['message']);
        $this->assertSame('error', $body['logs'][1]['level']);
        $this->assertSame(1, $body['logs'][1]['sequenceNumber']);
    }

    public function testFlushingWithNoBufferedLogsSendsNoRequest(): void
    {
        $requests = [];
        $mock = new MockHandler([]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(\GuzzleHttp\Middleware::history($requests));
        $client = new Client(['handler' => $handlerStack]);

        $params = new RequiredInitializationParams(
            apiKey: 'test-key',
            host: 'https://test.logdash.io',
            verbose: false
        );

        $logSync = new HttpLogSync($params, $client);
        $logSync->flush();

        $this->assertCount(0, $requests);
    }

    public function testServerErrorDoesNotThrow(): void
    {
        $mock = new MockHandler([
            new Response(500),
            new Response(500),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $params = new RequiredInitializationParams(
            apiKey: 'test-key',
            host: 'https://test.logdash.io',
            verbose: false
        );

        $logSync = new HttpLogSync($params, $client);
        $logSync->send('will fail', LogLevel::ERROR, '2026-01-01T00:00:00+00:00');

        // Should swallow the failure (after its internal retry) instead of
        // throwing into the caller's application code.
        $logSync->flush();

        $this->assertTrue(true);
    }
}
