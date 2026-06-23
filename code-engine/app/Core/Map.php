<?php

declare(strict_types=1);

namespace App\Core;

class Map {

    private $storage;
    
    public function __construct(array $storage = [])
    {
        $this->storage = $storage;
    }

    public function get(string $key) 
    {
        if ( ! isset($this->storage[$key]) ) {
            return null;
        }
        return $this->storage[$key];
    }

    public function read() 
    {
        return $this->storage;
    }
}