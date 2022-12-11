<?php

declare(strict_types=1);

namespace App\Adapters;
use App\Core\Engine;
use Illuminate\Support\Facades\Redis;
use Exception;

class Queue 
{
    private const DELAYED_SORTED_SET = 'hefesto:queue:delayed';
    private const ENVIRONMENT_INFO_HASH_PREFIX = 'hefesto:queue:environmentinfo:';
    private const ENVIRONMENT_SET_PREFIX = 'hefesto:queue:environment:';
    private const ENGINE_HASH_PREFIX = 'hefesto:queue:engine:';

    private string $org;
    private string $env;

    public function setEnvironment(string $org, string $env) : void 
    {
        $this->org = $org;
        $this->env = $env;
    }

    public function push(Engine $engine, int $delay) : void
    {
        $state = $engine->state();
        
        $this->setEnvironment(
            $state->memory()->get('hefesto-org'),
            $state->memory()->get('hefesto-org')
        );

        $id = $state->id();
        $serializedEngine = serialize($engine);
        $score = (int)microtime(true) + $delay;

        $result = Redis::transaction(function ($redis) use ($id,$engine,$score) {
            $redis->hset($this->engineHashKey($id), 'engine', $engine);
            $redis->zadd(self::DELAYED_SORTED_SET, $score, $id);
            $redis->sadd($this->environmentSetKey(), $id);
            $redis->hincrby($this->environmentInfoHashKey(),'requests',1);
        });

        if (!is_array($result) || count($result) !== 4 
                || $result[0] !== 1 //validate hset
                || $result[1] !== 1  //validate zadd
                || $result[2] !== 1  //validate sadd
                || !is_int($result[3]) //validate hincrby
        ) {
            throw new Exception('error adding job', 500);
        }
    }

    private function environmentInfoHashKey() : string 
    {
        return self::ENVIRONMENT_INFO_HASH_PREFIX.$this->org.$this->env;
    }

    private function environmentSetKey() : string 
    {
        return self::ENVIRONMENT_SET_PREFIX.$this->org.$this->env;
    }

    private function engineHashKey(string $id) : string 
    {
        return self::ENGINE_HASH_PREFIX.$id;
    }
}