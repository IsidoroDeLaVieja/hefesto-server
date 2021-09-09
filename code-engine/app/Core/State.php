<?php

declare(strict_types=1);

namespace App\Core;

class State {

    private $id;
    private $message;
    private $memory;
    private $groups;
    private $isDirectiveDebug;
    private $debug;
    private $maps;
    private $alias;
    private $apiMemory;
    private $isQueued;

    public function __construct(
        Message $message,
        array $config
    ) {
        $this->message = $message;
        $this->groups = new Groups();
        $this->memory = new ExecutionTimeMemory();
        $this->isDirectiveDebug = false;
        $this->id = isset($config['id']) ? $config['id'] : uniqid('',true);
        $this->apiMemory = $config['apiMemory'];
        $this->alias = new Alias($this);
        $this->resetDebug();
        $apiMaps = $this->apiMemory->get('api-maps');
        $this->maps = $apiMaps ? (array)$apiMaps : null;
        $this->isQueued = false;
        
        $this->memory->set('hefesto-org',$config['organization']);
        $this->memory->set('hefesto-env',$config['environment']);
        $this->memory->set('hefesto-api',$config['keyApi']);
        $this->memory->set('hefesto-localhost',$config['localhost']);
        $this->memory->set('hefesto-pathcode',$config['codePath']);
        $this->memory->set('hefesto-pathstorage',$config['storagePath']);
        $this->memory->set('hefesto-definitionpath',$config['definitionPath']);
        $this->memory->set('hefesto-definitionverb',$config['definitionVerb']);
    }

    public function id() : string 
    {
        return $this->id;
    }

    public function message() : Message
    {
        return $this->message;
    }

    public function groups() : Groups 
    {
        return $this->groups;
    }

    public function memory() : Memory 
    {
        return $this->memory;
    }

    public function map(string $key) : Map
    {
        if ( !isset($this->maps[$key]) ) {
            $this->maps[$key] = new Map($this->memory->get('hefesto-pathcode').'Maps/'.$key.'.json');
            $this->apiMemory ? $this->apiMemory->set('api-maps',$this->maps) : null;
        }
        return $this->maps[$key];
    }

    public function alias($key)
    {
        return $this->alias->find($key);
    }

    public function queue() : void
    {
        $this->isQueued = true;
    }

    public function isQueued() : bool 
    {
        return $this->isQueued;
    }

    public function getDebug() : ?array 
    {
        return $this->debug;
    }

    public function isDirectiveDebug() : bool 
    {
        return $this->isDirectiveDebug;
    }

    public function enableDirectiveDebug() : void 
    {
        $this->isDirectiveDebug = true;
    }

    public function addDebug(array $log) : void 
    {
        array_push($this->debug,$log);
    }

    public function resetDebug() : void 
    {
        $this->debug = [];
    }

}
