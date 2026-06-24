<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Core\Engine;
use App\Core\Queue;
use Predis\Client;
use RuntimeException;

class PredisQueue implements Queue
{
    private const DELAYED_SORTED_SET = 'hefesto:queue:delayed';
    private const WAITING_SET = 'hefesto:queue:waiting';
    private const PROCESSING_SET = 'hefesto:queue:processing';
    private const FAILED_SET = 'hefesto:queue:failed';
    private const ENGINE_HASH = 'hefesto:queue:engine';
    private const ENVIRONMENT_INFO_HASH_PREFIX = 'hefesto:queue:environmentinfo:';
    private const ENVIRONMENT_SET_PREFIX = 'hefesto:queue:environment:';

    private string $org = '';
    private string $env = '';

    public function __construct(
        private readonly Client $client,
    ) {}

    public function push(Engine $engine, int $delay): void
    {
        $state = $engine->state();

        $this->setEnvironment(
            $state->memory()->get('hefesto-org'),
            $state->memory()->get('hefesto-env'),
        );

        $id = $state->id();
        $serializedEngine = serialize($engine);
        $score = $this->now() + $delay;

        $result = $this->client->transaction(function ($redis) use ($id, $serializedEngine, $score): void {
            $redis->hset(self::ENGINE_HASH, $id, $serializedEngine);
            $redis->sadd(self::WAITING_SET, $id);
            $redis->zadd(self::DELAYED_SORTED_SET, $score, $id);
            $redis->sadd($this->environmentSetKey(), $id);
            $redis->hincrby($this->environmentInfoHashKey(), 'requests', 1);
        });

        if (!$this->isValidPushResult($result)) {
            throw new RuntimeException('error adding job');
        }
    }

    public function next(): ?Engine
    {
        $ids = $this->client->zrange(self::DELAYED_SORTED_SET, 0, $this->now(), 'BYSCORE', 'LIMIT', 0, 1);

        if (!is_array($ids) || !isset($ids[0])) {
            return null;
        }

        $id = $ids[0];
        $engine = $this->client->hget(self::ENGINE_HASH, $id);

        if (!$engine) {
            throw new RuntimeException('error processing job, ' . $id . ' engine not found');
        }

        $result = $this->client->transaction(function ($redis) use ($id): void {
            $redis->smove(self::WAITING_SET, self::PROCESSING_SET, $id);
            $redis->zrem(self::DELAYED_SORTED_SET, $id);
        });

        if (!$this->isValidNextResult($result)) {
            return null;
        }

        return unserialize($engine);
    }

    public function fail(string $id): void
    {
        $result = $this->client->smove(self::PROCESSING_SET, self::FAILED_SET, $id);

        if ($result !== 1) {
            throw new RuntimeException('error moving ' . $id . ' from processing to failed');
        }
    }

    public function success(string $id, string $org, string $env): void
    {
        $this->setEnvironment($org, $env);

        $result = $this->client->transaction(function ($redis) use ($id): void {
            $redis->srem(self::PROCESSING_SET, $id);
            $redis->hdel(self::ENGINE_HASH, $id);
            $redis->srem($this->environmentSetKey(), $id);
            $redis->hincrby($this->environmentInfoHashKey(), 'success', 1);
        });

        if (!$this->isValidSuccessResult($result)) {
            throw new RuntimeException('error marking success ' . $id);
        }
    }

    public function requeueFailed(string $org, string $env): int
    {
        $this->setEnvironment($org, $env);

        $score = $this->now();
        $ids = $this->client->sinter($this->environmentSetKey(), self::FAILED_SET);

        foreach ($ids as $id) {
            $this->client->transaction(function ($redis) use ($id, $score): void {
                $redis->smove(self::FAILED_SET, self::WAITING_SET, $id);
                $redis->zadd(self::DELAYED_SORTED_SET, $score, $id);
            });
        }

        return count($ids);
    }

    public function flush(string $org, string $env): int
    {
        $this->setEnvironment($org, $env);

        $this->client->hdel($this->environmentInfoHashKey(), 'requests');
        $this->client->hdel($this->environmentInfoHashKey(), 'success');

        $ids = $this->client->sinter($this->environmentSetKey(), self::FAILED_SET);

        foreach ($ids as $id) {
            $this->client->transaction(function ($redis) use ($id): void {
                $redis->srem($this->environmentSetKey(), $id);
                $redis->srem(self::FAILED_SET, $id);
                $redis->hdel(self::ENGINE_HASH, $id);
            });
        }

        return count($ids);
    }

    public function count(string $org, string $env): array
    {
        $this->setEnvironment($org, $env);

        return [
            'requests'  => (int) $this->client->hget($this->environmentInfoHashKey(), 'requests'),
            'waiting'   => $this->client->executeRaw(['sintercard', 2, $this->environmentSetKey(), self::WAITING_SET]),
            'processing'=> $this->client->executeRaw(['sintercard', 2, $this->environmentSetKey(), self::PROCESSING_SET]),
            'failed'    => $this->client->executeRaw(['sintercard', 2, $this->environmentSetKey(), self::FAILED_SET]),
            'success'   => (int) $this->client->hget($this->environmentInfoHashKey(), 'success'),
        ];
    }

    private function setEnvironment(string $org, string $env): void
    {
        $this->org = $this->sanitize($org);
        $this->env = $this->sanitize($env);
    }

    private function sanitize(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9]+/', '', $value);
    }

    private function now(): int
    {
        return (int) microtime(true);
    }

    private function environmentInfoHashKey(): string
    {
        return self::ENVIRONMENT_INFO_HASH_PREFIX . $this->org . $this->env;
    }

    private function environmentSetKey(): string
    {
        return self::ENVIRONMENT_SET_PREFIX . $this->org . $this->env;
    }

    private function isValidPushResult(mixed $result): bool
    {
        return is_array($result)
            && count($result) === 5
            && $result[0] === 1
            && $result[1] === 1
            && $result[2] === 1
            && $result[3] === 1
            && is_int($result[4]);
    }

    private function isValidNextResult(mixed $result): bool
    {
        return is_array($result)
            && count($result) === 2
            && $result[0] === 1;
    }

    private function isValidSuccessResult(mixed $result): bool
    {
        return is_array($result)
            && count($result) === 4
            && $result[0] === 1
            && $result[1] === 1
            && $result[2] === 1
            && is_int($result[3]);
    }
}
