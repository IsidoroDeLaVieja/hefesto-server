<?php

namespace App\Core;
use Exception;

class Map {

    private $storage;
    
    public function __construct(string $path)
    {
        try {
            $this->storage = json_decode(file_get_contents($path),true);
        } catch (Exception $e) {
            $this->storage = [];
        }
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