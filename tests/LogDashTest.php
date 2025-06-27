<?php

declare(strict_types=1);

namespace LogDash\Tests;

use LogDash\LogDash;
use LogDash\LogLevel;
use PHPUnit\Framework\TestCase;

class LogDashTest extends TestCase
{
    public function testCreateWithoutApiKey(): void
    {
        $logdash = LogDash::create();
        
        $this->assertInstanceOf(LogDash::class, $logdash);
        $this->assertNotNull($logdash->logger());
        $this->assertNotNull($logdash->metrics());
    }

    public function testCreateWithApiKey(): void
    {
        $logdash = LogDash::create([
            'apiKey' => 'test-api-key',
            'host' => 'https://test.logdash.io',
            'verbose' => true
        ]);
        
        $this->assertInstanceOf(LogDash::class, $logdash);
        $this->assertNotNull($logdash->logger());
        $this->assertNotNull($logdash->metrics());
    }

    public function testLoggerMethods(): void
    {
        $logdash = LogDash::create();
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
        $logdash = LogDash::create();
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
}
