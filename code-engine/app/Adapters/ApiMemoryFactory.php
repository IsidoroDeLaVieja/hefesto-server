<?php 

namespace App\Adapters;

class ApiMemoryFactory {

    public function make(string $org,string $env, string $key) : CachedFileMemory
    {
        return new CachedFileMemory(
            'api-memory/'.$org.'-'.$env.'-'.$key
        );
    }
}