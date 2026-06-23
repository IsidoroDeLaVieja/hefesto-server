<?php

declare(strict_types=1);

namespace Tests\Integration\Adapters;

use Tests\TestCase;
use App\Adapters\LaravelSenderEngineDispatcher;
use App\Core\Engine;
use App\Core\EngineDispatcher;
use App\Core\DirectiveFactory;
use App\Core\DefaultDirectiveFactory;
use Tests\Fixture\StateFixture;
use SplDoublyLinkedList;

class LaravelSenderEngineDispatcherTest extends TestCase
{
    private InMemoryQueue $queue;
    private LaravelSenderEngineDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queue = new InMemoryQueue();
        $this->dispatcher = new LaravelSenderEngineDispatcher($this->queue);
    }

    protected function tearDown(): void
    {
        $this->queue->reset();
        parent::tearDown();
    }

    // ----------------------------------------------------------------
    //  Tests
    // ----------------------------------------------------------------

    public function testExecutePushesEngineWithGivenDelay(): void
    {
        $engine = $this->createEngine();
        $delay = 60;

        $this->dispatcher->execute($engine, $delay);

        $pushed = $this->queue->getPushed();
        $this->assertCount(1, $pushed);
        $this->assertSame($engine, $pushed[0]['engine']);
        $this->assertSame(60, $pushed[0]['delay']);
    }

    public function testExecutePushesEngineWithZeroDelay(): void
    {
        $engine = $this->createEngine();
        $delay = 0;

        $this->dispatcher->execute($engine, $delay);

        $pushed = $this->queue->getPushed();
        $this->assertCount(1, $pushed);
        $this->assertSame($engine, $pushed[0]['engine']);
        $this->assertSame(0, $pushed[0]['delay']);
    }

    public function testExecutePushesEngineWithLargeDelay(): void
    {
        $engine = $this->createEngine();
        $delay = 86400; // 24 hours

        $this->dispatcher->execute($engine, $delay);

        $pushed = $this->queue->getPushed();
        $this->assertCount(1, $pushed);
        $this->assertSame($engine, $pushed[0]['engine']);
        $this->assertSame(86400, $pushed[0]['delay']);
    }

    public function testExecutePropagatesExceptionFromQueue(): void
    {
        $engine = $this->createEngine();

        // Replace queue with one that throws
        $failingQueue = new class implements \App\Core\Queue {
            public function push(\App\Core\Engine $engine, int $delay): void
            {
                throw new \RuntimeException('queue failure');
            }
            public function next(): ?Engine { return null; }
            public function fail(string $id): void {}
            public function success(string $id, string $org, string $env): void {}
            public function requeueFailed(string $org, string $env): int { return 0; }
            public function flush(string $org, string $env): int { return 0; }
            public function count(string $org, string $env): array { return []; }
        };

        $dispatcher = new LaravelSenderEngineDispatcher($failingQueue);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('queue failure');

        $dispatcher->execute($engine, 10);
    }

    public function testExecuteCanBeCalledMultipleTimes(): void
    {
        $engineA = $this->createEngine();
        $engineB = $this->createEngine();

        $this->dispatcher->execute($engineA, 10);
        $this->dispatcher->execute($engineB, 20);

        $pushed = $this->queue->getPushed();
        $this->assertCount(2, $pushed);
        $this->assertSame($engineA, $pushed[0]['engine']);
        $this->assertSame(10, $pushed[0]['delay']);
        $this->assertSame($engineB, $pushed[1]['engine']);
        $this->assertSame(20, $pushed[1]['delay']);
    }

    // ----------------------------------------------------------------
    //  Helpers
    // ----------------------------------------------------------------

    private function createEngine(): Engine
    {
        $state = StateFixture::get([]);
        $directives = new SplDoublyLinkedList();

        return new Engine(
            $state,
            $directives,
            $this->createMock(EngineDispatcher::class),
            new DefaultDirectiveFactory()
        );
    }
}