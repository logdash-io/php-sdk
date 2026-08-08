<?php

declare(strict_types=1);

namespace Logdash\Tests\Metrics;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Logdash\Metrics\Metrics;
use Logdash\Types\RequiredInitializationParams;
use PHPUnit\Framework\TestCase;

class MetricsTest extends TestCase
{
    public function testEachQueuedMetricIsSentAsItsOwnRequest(): void
    {
        $requests = [];
        $mock = new MockHandler([
            new Response(200),
            new Response(200),
            new Response(200),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($requests));
        $client = new Client(['handler' => $handlerStack]);

        $params = new RequiredInitializationParams(
            apiKey: 'test-key',
            host: 'https://test.logdash.io',
            verbose: false
        );

        $metrics = new Metrics($params, $client);

        $metrics->set('active_users', 150);
        $metrics->mutate('login_count', 1);
        $metrics->mutate('login_count', -1);
        $metrics->flush();

        $this->assertCount(3, $requests);

        foreach ($requests as $entry) {
            $this->assertSame('PUT', $entry['request']->getMethod());
            $this->assertSame('/metrics', $entry['request']->getUri()->getPath());
        }

        $bodies = array_map(
            fn (array $entry) => json_decode((string) $entry['request']->getBody(), true),
            $requests
        );

        $this->assertSame(['active_users', 'login_count', 'login_count'], array_column($bodies, 'name'));
        $this->assertSame(['set', 'change', 'change'], array_column($bodies, 'operation'));
    }

    public function testOneFailingMetricDoesNotBlockOthersInTheBatch(): void
    {
        $requests = [];
        $mock = new MockHandler([
            new Response(500),
            new Response(200),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($requests));
        $client = new Client(['handler' => $handlerStack]);

        $params = new RequiredInitializationParams(
            apiKey: 'test-key',
            host: 'https://test.logdash.io',
            verbose: false
        );

        $metrics = new Metrics($params, $client);

        $metrics->set('will_fail', 1);
        $metrics->set('will_succeed', 2);

        // Must not throw even though the first request fails.
        $metrics->flush();

        $this->assertCount(2, $requests);
    }
}
