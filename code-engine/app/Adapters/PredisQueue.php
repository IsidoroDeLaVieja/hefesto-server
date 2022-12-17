<?php

declare(strict_types=1);

namespace App\Adapters;
use App\Core\Engine;
use App\Core\Queue;
use Predis\Client;
use Exception;
use DateTime;

class PredisQueue implements Queue
{
    private const DELAYED_SORTED_SET = 'hefesto:queue:delayed';
    private const WAITING_SET = 'hefesto:queue:waiting';
    private const PROCESSING_SET = 'hefesto:queue:processing';
    private const FAILED_SET = 'hefesto:queue:failed';
    private const ENGINE_HASH = 'hefesto:queue:engine';
    private const ENVIRONMENT_INFO_HASH_PREFIX = 'hefesto:queue:environmentinfo:';
    private const ENVIRONMENT_SET_PREFIX = 'hefesto:queue:environment:';

    private string $org;
    private string $env;

    private Client $client;

    public function __construct(Client $client) {
        $this->client = $client;
    }

    public function push(Engine $engine, int $delay) : void
    {
        $state = $engine->state();
        
        $this->setEnvironment(
            $state->memory()->get('hefesto-org'),
            $state->memory()->get('hefesto-env')
        );

        $id = $state->id();
        $serializedEngine = serialize($engine);
        $score = $this->now() + $delay;

        $result = $this->client->transaction(function ($redis) use ($id,$serializedEngine,$score) {
            $redis->hset(self::ENGINE_HASH, $id, $serializedEngine);
            $redis->sadd(self::WAITING_SET, $id);
            $redis->zadd(self::DELAYED_SORTED_SET, $score, $id);
            $redis->sadd($this->environmentSetKey(), $id);
            $redis->hincrby($this->environmentInfoHashKey(),'requests',1);
        });

        if (!is_array($result) || count($result) !== 5 
                || $result[0] !== 1 //validate hset
                || $result[1] !== 1  //validate sadd
                || $result[2] !== 1  //validate zadd
                || $result[3] !== 1  //validate sadd
                || !is_int($result[4]) //validate hincrby
        ) {
            throw new Exception('error adding job');
        }
    }

    public function next() : ?Engine 
    {
        $ids = $this->client->zrange( self::DELAYED_SORTED_SET , 0 , $this->now() , 'BYSCORE' , 'LIMIT' , 0 , 1 );
        if (!is_array($ids) || !isset($ids[0])) {
            return null;
        }
        $id = $ids[0];

        $engine = $this->client->hget(self::ENGINE_HASH,$id);
        if (!$engine) {
            throw new Exception('error processing job, '.$id.' engine not found');
        }
        
        $result = $this->client->transaction(function ($redis) use ($id) {
            $redis->smove(self::WAITING_SET, self::PROCESSING_SET,$id);
            $redis->zrem(self::DELAYED_SORTED_SET, $id);
        });

        if (!is_array($result) || count($result) !== 2
            || $result[0] !== 1 //validate smove
        ) {
            return null;
        }

        return unserialize($engine);
    }

    public function fail(string $id) : void
    {
        $result = $this->client->smove(self::PROCESSING_SET, self::FAILED_SET,$id);
        if ($result !== 1) {
            throw new Exception('error moving '.$id.' from processing to failed');
        }
    }

    public function success(string $id,string $org,string $env) : void
    {
        $this->setEnvironment(
            $org,
            $env
        );

        $result = $this->client->transaction(function ($redis) use ($id) {
            $redis->srem(self::PROCESSING_SET, $id);
            $redis->hdel(self::ENGINE_HASH,$id);
            $redis->srem($this->environmentSetKey(), $id);
            $redis->hincrby($this->environmentInfoHashKey(),'success',1);
        });

        if (!is_array($result) || count($result) !== 4
            || $result[0] !== 1 //validate srem
            || $result[1] !== 1 //validate hdel
            || $result[2] !== 1 //validate srem
            || !is_int($result[3]) //validate hincrby
        ) {
            throw new Exception('error marking success '.$id);
        }
    }

    private function setEnvironment(string $org, string $env) : void
    {
        $this->org = $this->onlyAlphaNumeric($org);
        $this->env = $this->onlyAlphaNumeric($env);
    }

    private function onlyAlphaNumeric(string $source) : string 
    {
        return preg_replace("/[^a-zA-Z0-9]+/", "", $source);
    }

    private function now() : int 
    {
        return (int)microtime(true);
    }

    private function environmentInfoHashKey() : string 
    {
        return self::ENVIRONMENT_INFO_HASH_PREFIX.$this->org.$this->env;
    }

    private function environmentSetKey() : string 
    {
        return self::ENVIRONMENT_SET_PREFIX.$this->org.$this->env;
    }
}