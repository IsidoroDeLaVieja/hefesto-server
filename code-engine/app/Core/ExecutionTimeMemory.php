<?php

declare(strict_types=1);

namespace App\Core;

class ExecutionTimeMemory implements Memory {

    private $storage = [];
    
    public function get(string $key)
    {
        $upperKey = strtoupper($key);
        if ( ! isset($this->storage[$upperKey]) ) {
            return null;
        }
        return $this->storage[$upperKey];
    }

    public function set(string $key,$value) : void
    {
        $this->storage[strtoupper($key)] = $value;
    }

    public function read() : array 
    {
        return $this->storage;
    }
}