<?php

declare(strict_types=1);

namespace Tests\Integration\Adapters;

use Tests\TestCase;
use App\Adapters\PredisQueue;
use App\Core\Engine;
use App\Core\EngineDispatcher;
use App\Core\DefaultDirectiveFactory;
use Predis\Client;
use SplDoublyLinkedList;
use Tests\Fixture\StateFixture;
use Exception;

class PredisQueueTest extends TestCase
{
    private Client $client;
    private PredisQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $host = env('REDIS_HOST', 'redis');
        $port = env('REDIS_PORT', '6379');

        $this->client = new Client("tcp://{$host}:{$port}");
        $this->queue = new PredisQueue($this->client);

        $this->flushRedis();
    }

    protected function tearDown(): void
    {
        $this->flushRedis();
        parent::tearDown();
    }

    // ----------------------------------------------------------------
    //  push()
    // ----------------------------------------------------------------

    public function test_push_stores_engine_and_adds_to_sets(): void
    {
        $engine = $this->createEngine('job-001', 'acme', 'live');
        $delay = 60;

        $this->queue->push($engine, $delay);

        $this->assertTrue(
            (bool) $this->client->hexists('hefesto:queue:engine', 'job-001'),
            'Engine hash must contain the job id'
        );
        $this->assertSame(
            1,
            $this->client->sismember('hefesto:queue:waiting', 'job-001')
        );
        $this->assertNotNull(
            $this->client->zscore('hefesto:queue:delayed', 'job-001')
        );
        $this->assertSame(
            1,
            $this->client->sismember('hefesto:queue:environment:acmelive', 'job-001')
        );
        $this->assertSame(
            '1',
            $this->client->hget('hefesto:queue:environmentinfo:acmelive', 'requests')
        );
    }

    public function test_push_throws_exception_when_transaction_invalid(): void
    {
        $engine = $this->createEngine('job-002', 'acme', 'live');
        $delay = 0;

        // Pre-populate the hash so hset returns 0 (no new field created)
        $this->client->hset('hefesto:queue:engine', 'job-002', 'dummy');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('error adding job');

        $this->queue->push($engine, $delay);
    }

    // ----------------------------------------------------------------
    //  next()
    // ----------------------------------------------------------------

    public function test_next_returns_null_when_no_ready_jobs(): void
    {
        $this->assertNull($this->queue->next());
    }

    public function test_next_returns_engine_when_scheduled_time_reached(): void
    {
        $engine = $this->createEngine('job-003', 'acme', 'live');
        $this->queue->push($engine, 0);

        $retrieved = $this->queue->next();

        $this->assertNotNull($retrieved);
        $this->assertSame('job-003', $retrieved->state()->id());

        // Job moved from waiting to processing
        $this->assertSame(0, $this->client->sismember('hefesto:queue:waiting', 'job-003'));
        $this->assertSame(1, $this->client->sismember('hefesto:queue:processing', 'job-003'));

        // Delayed set entry removed
        $this->assertNull($this->client->zscore('hefesto:queue:delayed', 'job-003'));
    }

    public function test_next_throws_when_engine_hash_missing(): void
    {
        // Manually insert into delayed set + waiting set, but no hash entry
        $this->client->zadd('hefesto:queue:delayed', 0, 'orphan-job');
        $this->client->sadd('hefesto:queue:waiting', 'orphan-job');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('engine not found');

        $this->queue->next();
    }

    // ----------------------------------------------------------------
    //  fail()
    // ----------------------------------------------------------------

    public function test_fail_moves_job_from_processing_to_failed(): void
    {
        $this->givenJobInProcessing('job-004', 'acme', 'live');

        $this->queue->fail('job-004');

        $this->assertSame(0, $this->client->sismember('hefesto:queue:processing', 'job-004'));
        $this->assertSame(1, $this->client->sismember('hefesto:queue:failed', 'job-004'));
    }

    public function test_fail_throws_when_job_not_in_processing(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('error moving');

        $this->queue->fail('nonexistent');
    }

    // ----------------------------------------------------------------
    //  success()
    // ----------------------------------------------------------------

    public function test_success_cleans_job_and_increments_success(): void
    {
        $this->givenJobInProcessing('job-005', 'acme', 'live');

        $this->queue->success('job-005', 'acme', 'live');

        $this->assertSame(0, $this->client->sismember('hefesto:queue:processing', 'job-005'));
        $this->assertFalse((bool) $this->client->hexists('hefesto:queue:engine', 'job-005'));
        $this->assertSame(0, $this->client->sismember('hefesto:queue:environment:acmelive', 'job-005'));
        $this->assertSame('1', $this->client->hget('hefesto:queue:environmentinfo:acmelive', 'success'));
    }

    public function test_success_throws_when_transaction_invalid(): void
    {
        $this->givenJobInProcessing('job-006', 'acme', 'live');

        // Remove the env set key so srem returns 0
        $this->client->del('hefesto:queue:environment:acmelive');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('error marking success');

        $this->queue->success('job-006', 'acme', 'live');
    }

    // ----------------------------------------------------------------
    //  requeueFailed()
    // ----------------------------------------------------------------

    public function test_requeueFailed_moves_all_failed_jobs_back_to_waiting(): void
    {
        $this->givenJobInFailed('job-007', 'acme', 'live');
        $this->givenJobInFailed('job-008', 'acme', 'live');

        $count = $this->queue->requeueFailed('acme', 'live');

        $this->assertSame(2, $count);
        $this->assertSame(1, $this->client->sismember('hefesto:queue:waiting', 'job-007'));
        $this->assertSame(1, $this->client->sismember('hefesto:queue:waiting', 'job-008'));
        $this->assertSame(0, $this->client->sismember('hefesto:queue:failed', 'job-007'));
        $this->assertSame(0, $this->client->sismember('hefesto:queue:failed', 'job-008'));
    }

    // ----------------------------------------------------------------
    //  flush()
    // ----------------------------------------------------------------

    public function test_flush_clears_failed_jobs_and_metrics(): void
    {
        // Set up some metrics
        $this->client->hset('hefesto:queue:environmentinfo:acmelive', 'requests', '10');
        $this->client->hset('hefesto:queue:environmentinfo:acmelive', 'success', '7');
        $this->givenJobInFailed('job-009', 'acme', 'live');
        $this->givenJobInFailed('job-010', 'acme', 'live');

        $count = $this->queue->flush('acme', 'live');

        $this->assertSame(2, $count);
        $this->assertNull($this->client->hget('hefesto:queue:environmentinfo:acmelive', 'requests'));
        $this->assertNull($this->client->hget('hefesto:queue:environmentinfo:acmelive', 'success'));
        $this->assertSame(0, $this->client->sismember('hefesto:queue:failed', 'job-009'));
        $this->assertSame(0, $this->client->sismember('hefesto:queue:environment:acmelive', 'job-010'));
    }

    // ----------------------------------------------------------------
    //  count()
    // ----------------------------------------------------------------

    public function test_count_returns_accurate_statistics(): void
    {
        // Push 2 jobs
        $this->queue->push($this->createEngine('job-011', 'acme', 'live'), 0);
        $this->queue->push($this->createEngine('job-012', 'acme', 'live'), 3600);

        // Move one to processing via next()
        $this->queue->next();

        // Fail one later
        $this->givenJobInFailed('job-013', 'acme', 'live');

        $stats = $this->queue->count('acme', 'live');

        $this->assertSame(2, $stats['requests']);
        $this->assertSame(1, $stats['waiting']);       // job-012 still waiting
        $this->assertSame(1, $stats['processing']);    // job-011 processing
        $this->assertSame(1, $stats['failed']);        // job-013 failed
        $this->assertSame(0, $stats['success']);       // none completed
    }

    // ----------------------------------------------------------------
    //  Environment sanitization
    // ----------------------------------------------------------------

    public function test_org_and_env_are_sanitized_to_alpha_numeric(): void
    {
        $engine = $this->createEngine('job-020', 'my-org!@#', 'prod$%^');
        $this->queue->push($engine, 0);

        // Special chars stripped: org becomes 'myorg', env becomes 'prod'
        $this->assertSame(
            1,
            $this->client->sismember('hefesto:queue:environment:myorgprod', 'job-020')
        );
        $this->assertSame(
            '1',
            $this->client->hget('hefesto:queue:environmentinfo:myorgprod', 'requests')
        );
    }

    // ----------------------------------------------------------------
    //  Environment isolation
    // ----------------------------------------------------------------

    public function test_org_and_env_are_isolated(): void
    {
        $engineA = $this->createEngine('job-030', 'acme', 'live');
        $engineB = $this->createEngine('job-031', 'other', 'dev');

        $this->queue->push($engineA, 0);
        $this->queue->push($engineB, 0);

        $statsA = $this->queue->count('acme', 'live');
        $statsB = $this->queue->count('other', 'dev');

        $this->assertSame(1, $statsA['requests']);
        $this->assertSame(1, $statsA['waiting']);
        $this->assertSame(0, $statsA['success']);

        $this->assertSame(1, $statsB['requests']);
        $this->assertSame(1, $statsB['waiting']);
        $this->assertSame(0, $statsB['success']);
    }

    // ----------------------------------------------------------------
    //  Helpers
    // ----------------------------------------------------------------

    private function createEngine(string $id, string $org, string $env): Engine
    {
        $state = StateFixture::get([
            'id' => $id,
        ]);

        $state->memory()->set('hefesto-org', $org);
        $state->memory()->set('hefesto-env', $env);

        $directives = new SplDoublyLinkedList();

        return new Engine(
            $state,
            $directives,
            $this->createStub(EngineDispatcher::class),
            new DefaultDirectiveFactory()
        );
    }

    /**
     * Insert a job directly into processing set + engine hash.
     */
    private function givenJobInProcessing(string $id, string $org, string $env): void
    {
        $engine = $this->createEngine($id, $org, $env);
        $this->client->hset('hefesto:queue:engine', $id, serialize($engine));
        $this->client->sadd('hefesto:queue:processing', $id);
        $this->client->sadd('hefesto:queue:environment:' . $org . $env, $id);
    }

    /**
     * Insert a job directly into failed set + engine hash + environment set.
     */
    private function givenJobInFailed(string $id, string $org, string $env): void
    {
        $engine = $this->createEngine($id, $org, $env);
        $this->client->hset('hefesto:queue:engine', $id, serialize($engine));
        $this->client->sadd('hefesto:queue:failed', $id);
        $this->client->sadd('hefesto:queue:environment:' . $org . $env, $id);
    }

    /**
     * Remove all queue-related keys from Redis.
     */
    private function flushRedis(): void
    {
        $keys = $this->client->keys('hefesto:queue:*');
        if (!empty($keys)) {
            $this->client->del($keys);
        }
    }
}