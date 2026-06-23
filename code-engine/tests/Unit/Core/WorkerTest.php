<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Worker;
use App\Core\Queue;
use App\Core\Engine;
use App\Core\State;
use App\Core\Message;
use App\Core\ExecutionTimeMemory;

class WorkerTest extends TestCase
{
    private Queue $queue;
    private WorkerUnderTest $worker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queue = $this->createMock(Queue::class);
        $this->worker = new WorkerUnderTest($this->queue);
    }

    // ====================================================================
    // loop()
    // ====================================================================

    public function testLoopExitsWhenMaxJobsReached(): void
    {
        $engines = $this->generateEngines(10, 200);

        $this->queue->expects($this->exactly(10))
            ->method('next')
            ->willReturnOnConsecutiveCalls(...$engines);

        $this->queue->expects($this->exactly(10))
            ->method('success')
            ->with($this->isType('string'), 'my-org', 'prod');

        $this->queue->expects($this->never())
            ->method('fail');

        try {
            $this->worker->loop();
            $this->fail('Expected StopLoopException was not thrown');
        } catch (StopLoopException) {
            // Expected
        }

        $this->assertSame(10, $this->worker->countJobs());
        $this->assertSame(0, $this->worker->countDelays());
        $this->assertTrue($this->worker->wasShutdown());
    }

    public function testLoopDelaysWhenNoEngineAvailable(): void
    {
        $this->queue->expects($this->exactly(12))
            ->method('next')
            ->willReturnOnConsecutiveCalls(
                null,
                null,
                $this->createEngine(200),
                $this->createEngine(200),
                $this->createEngine(200),
                $this->createEngine(200),
                $this->createEngine(200),
                $this->createEngine(200),
                $this->createEngine(200),
                $this->createEngine(200),
                $this->createEngine(200),
                $this->createEngine(200),
            );

        $this->queue->expects($this->exactly(10))
            ->method('success');

        $this->queue->expects($this->never())
            ->method('fail');

        try {
            $this->worker->loop();
            $this->fail('Expected StopLoopException was not thrown');
        } catch (StopLoopException) {
            // Expected
        }

        $this->assertSame(10, $this->worker->countJobs());
        $this->assertSame(2, $this->worker->countDelays());
        $this->assertTrue($this->worker->wasShutdown());
    }

    public function testLoopCallsFailWhenStatusIs500OrAbove(): void
    {
        $engines = [];
        for ($i = 0; $i < 5; $i++) {
            $engines[] = $this->createEngine(500);
        }
        for ($i = 0; $i < 5; $i++) {
            $engines[] = $this->createEngine(200);
        }

        $this->queue->expects($this->exactly(10))
            ->method('next')
            ->willReturnOnConsecutiveCalls(...$engines);

        $this->queue->expects($this->exactly(5))
            ->method('fail')
            ->with($this->isType('string'));

        $this->queue->expects($this->exactly(5))
            ->method('success')
            ->with($this->isType('string'), 'my-org', 'prod');

        try {
            $this->worker->loop();
            $this->fail('Expected StopLoopException was not thrown');
        } catch (StopLoopException) {
            // Expected
        }

        $this->assertSame(10, $this->worker->countJobs());
        $this->assertTrue($this->worker->wasShutdown());
    }

    public function testLoopHandlesHigherThan500StatusCodes(): void
    {
        $engines = [
            $this->createEngine(503),
            $this->createEngine(502),
            $this->createEngine(500),
            $this->createEngine(200),
            $this->createEngine(200),
            $this->createEngine(200),
            $this->createEngine(200),
            $this->createEngine(200),
            $this->createEngine(200),
            $this->createEngine(200),
        ];

        $this->queue->expects($this->exactly(10))
            ->method('next')
            ->willReturnOnConsecutiveCalls(...$engines);

        $this->queue->expects($this->exactly(3))
            ->method('fail');

        $this->queue->expects($this->exactly(7))
            ->method('success');

        try {
            $this->worker->loop();
            $this->fail('Expected StopLoopException was not thrown');
        } catch (StopLoopException) {
            // Expected
        }

        $this->assertSame(10, $this->worker->countJobs());
        $this->assertTrue($this->worker->wasShutdown());
    }

    // ====================================================================
    // Helpers
    // ====================================================================

    private function createEngine(int $status): Engine
    {
        $message = new Message('POST', '/test', [], '{}', [], [], $status);

        $state = $this->createMock(State::class);
        $state->method('id')->willReturn(uniqid());

        $memory = new ExecutionTimeMemory();
        $memory->set('hefesto-org', 'my-org');
        $memory->set('hefesto-env', 'prod');

        $state->method('memory')->willReturn($memory);
        $state->method('message')->willReturn($message);

        $engine = $this->createMock(Engine::class);
        $engine->method('state')->willReturn($state);

        return $engine;
    }

    private function generateEngines(int $count, int $status): array
    {
        $engines = [];
        for ($i = 0; $i < $count; $i++) {
            $engines[] = $this->createEngine($status);
        }
        return $engines;
    }
}

/**
 * Test-safe Worker subclass that overrides delay() and shutdown().
 */
class WorkerUnderTest extends Worker
{
    private int $delayCount = 0;
    private bool $wasShutdown = false;

    public function countDelays(): int
    {
        return $this->delayCount;
    }

    public function countJobs(): int
    {
        $ref = new \ReflectionProperty(Worker::class, 'countJobs');
        $ref->setAccessible(true);
        return $ref->getValue($this);
    }

    public function wasShutdown(): bool
    {
        return $this->wasShutdown;
    }

    protected function delay(): void
    {
        $this->delayCount++;
    }

    protected function shutdown(): never
    {
        $this->wasShutdown = true;
        throw new StopLoopException();
    }
}

/**
 * Exception used by WorkerUnderTest to stop loop() cleanly.
 */
class StopLoopException extends \RuntimeException
{
}