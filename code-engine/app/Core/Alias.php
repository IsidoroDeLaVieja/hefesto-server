<?php

namespace App\Core;

class Alias 
{
    private $state;

    public function __construct(State $state) 
    {
        $this->state = $state;
    }

    public function find($key)
    {
        if ( !is_string($key) ) {
            return $key;
        }
        $firstTwoCharacters = substr($key,0,2);
        if ($firstTwoCharacters !== '$.') {
            return $key;
        }

        $keyParts = explode('.',$key);
        $stateMethod = $keyParts[1];//message, memory ,map or id
        if ($stateMethod === 'id') {
            return $this->state->id();
        }
        list($object, $action, $arguments, $keys) = $this->$stateMethod($keyParts);

        $value = call_user_func_array( [ $object, $action], $arguments);
        if (!$keys) {
            return $value;
        }
        return $this->recursiveValue($value, $keys);
    }

    private function recursiveValue(array $collection, array $keys) 
    {
        foreach ($keys as $key) {
            $collection = $collection[$key];
        }
        return $collection;
    }

    private function message(array $keyParts) : array 
    {
        $object = $this->state->message();
        $action = 'get'.ucfirst($keyParts[2]);
        $arguments = array_slice($keyParts, 3, 4);
        $keys = false;
        return [$object, $action, $arguments, $keys];
    }

    private function storage(array $keyParts) : array 
    {
        $object =  $this->state->storage();
        return $this->memory($keyParts,$object);
    }

    private function memory(array $keyParts, ?object $object = null) : array 
    {
        $object =  is_null($object) ? $this->state->memory(): $object;
        $action = 'get';
        $arguments = array_slice($keyParts, 2, 3);
        $keys = count($keyParts) > 3 
                    ? array_slice($keyParts, 3)
                    : false;
        return [$object, $action, $arguments, $keys];
    }

    private function map(array $keyParts) : array 
    {
        $object =  $this->state->map($keyParts[2]);
        
        $action = 'read';
        $arguments = [];
        $keys = false;
        if ( ! isset($keyParts[3]) ) {
            return [$object, $action, $arguments, $keys];
        }
        
        $action = 'get';
        $arguments = array_slice($keyParts, 3, 4);
        $keys = count($keyParts) > 4 
                ? array_slice($keyParts, 4)
                : false;
        return [$object, $action, $arguments, $keys];
    }
}