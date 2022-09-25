<?php

namespace App\Adapters;
use App\Core\Memory;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Exception;
use Throwable;

class CachedFileMemory implements Memory {

    private $key;

    public function __construct(string $key) {
        $this->key = $key;
    }

    public function get(string $key) 
    {
        $key = $this->key.'/'.$key;
        $value = Redis::get($key);
        if ( $value ) {
            return unserialize($value);
        }
        try {
            $value = Storage::get($key);
        } catch ( Throwable $e ) {
            $value = serialize(null);
        }
        Redis::set($key, $value);
        if (is_null($value)) {
            return null;
        }
        return unserialize($value);
    }

    public function set(string $key,$value) : void
    {
        $key = $this->key.'/'.$key;
        $value = serialize($value);
        Storage::put($key,$value);
        Redis::set($key, $value);
    }

    public function read() : array
    {
        try {
            return Storage::files($this->key);
        } catch ( Throwable $e ) {
           return [];
        }
    }
}