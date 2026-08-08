<?php

declare(strict_types=1);

namespace Logdash\Tests\Queue;

use Logdash\Queue\RequestQueue;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RequestQueueTest extends TestCase
{
    public function testDoesNotSendUntilBatchSizeIsReached(): void
    {
        /** @var list<list<mixed>> $sentBatches */
        $sentBatches = [];

        $queue = new RequestQueue(
            sendFn: function (array $batch) use (&$sentBatches): void {
                $sentBatches[] = $batch;
            },
            batchSize: 3,
            flushIntervalMs: 60_000,
        );

        $queue->add('a');
        $queue->add('b');

        $this->assertSame([], $sentBatches);

        $queue->add('c');

        $this->assertSame([['a', 'b', 'c']], $sentBatches);
    }

    public function testExplicitFlushSendsPartialBatch(): void
    {
        /** @var list<list<mixed>> $sentBatches */
        $sentBatches = [];

        $queue = new RequestQueue(
            sendFn: function (array $batch) use (&$sentBatches): void {
                $sentBatches[] = $batch;
            },
            batchSize: 100,
            flushIntervalMs: 60_000,
        );

        $queue->add('a');
        $queue->flush();

        $this->assertSame([['a']], $sentBatches);

        // Flushing an empty queue is a no-op.
        $queue->flush();
        $this->assertSame([['a']], $sentBatches);
    }

    public function testRetriesUpToMaxAttemptsThenGivesUpSilently(): void
    {
        $attempts = 0;

        $queue = new RequestQueue(
            sendFn: function () use (&$attempts): void {
                $attempts++;
                throw new RuntimeException('boom');
            },
            batchSize: 1,
            flushIntervalMs: 60_000,
            maxAttempts: 3,
            retryDelayMs: 0,
        );

        $queue->add('a');

        $this->assertSame(3, $attempts);
    }

    public function testSucceedsOnRetryAfterInitialFailure(): void
    {
        $attempts = 0;
        /** @var list<mixed> $delivered */
        $delivered = [];

        $queue = new RequestQueue(
            sendFn: function (array $batch) use (&$attempts, &$delivered): void {
                $attempts++;
                if ($attempts === 1) {
                    throw new RuntimeException('transient failure');
                }
                $delivered = $batch;
            },
            batchSize: 1,
            flushIntervalMs: 60_000,
            maxAttempts: 3,
            retryDelayMs: 0,
        );

        $queue->add('a');

        $this->assertSame(2, $attempts);
        $this->assertSame(['a'], $delivered);
    }
}
