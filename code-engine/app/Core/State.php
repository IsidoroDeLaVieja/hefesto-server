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
    private $mapRepository;

    public function __construct(
        Message $message,
        array $config,
        ?Groups $groups = null,
        ?Memory $memory = null,
        ?MapRepositoryInterface $mapRepository = null
    ) {
        $this->groups = $groups ?? new Groups();
        $this->memory = $memory ?? new ExecutionTimeMemory();
        $this->mapRepository = $mapRepository ?? new FilesystemMapRepository(
            isset($config['codePath']) ? $config['codePath'] : ''
        );
        $this->message = $message;
        $this->isDirectiveDebug = false;
        $this->id = isset($config['id']) ? $config['id'] : uniqid('',true);
        $this->apiMemory = $config['apiMemory'] ?? null;
        $this->resetDebug();
        $apiMaps = $this->apiMemory ? $this->apiMemory->get('api-maps') : null;
        $this->maps = $apiMaps ? (array)$apiMaps : null;
        $this->isQueued = false;

        $this->memory->set('hefesto-org', $config['organization']);
        $this->memory->set('hefesto-env', $config['environment']);
        $this->memory->set('hefesto-api', $config['keyApi']);
        $this->memory->set('hefesto-localhost', $config['localhost']);
        $this->memory->set('hefesto-pathcode', $config['codePath']);
        $this->memory->set('hefesto-pathstorage', $config['storagePath']);
        $this->memory->set('hefesto-definitionpath', $config['definitionPath']);
        $this->memory->set('hefesto-definitionverb', $config['definitionVerb']);

        $this->alias = new Alias($this);
    }

    public function setAlias(Alias $alias): void
    {
        $this->alias = $alias;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function message(): Message
    {
        return $this->message;
    }

    public function groups(): Groups
    {
        return $this->groups;
    }

    public function memory(): Memory
    {
        return $this->memory;
    }

    public function map(string $key): Map
    {
        if (!isset($this->maps[$key])) {
            $storage = $this->mapRepository->load($key);
            $this->maps[$key] = new Map($storage);
            if ($this->apiMemory) {
                $this->apiMemory->set('api-maps', $this->maps);
            }
        }
        return $this->maps[$key];
    }

    public function alias($key)
    {
        return $this->alias ? $this->alias->find($key) : $key;
    }

    public function queue(): void
    {
        $this->isQueued = true;
    }

    public function isQueued(): bool
    {
        return $this->isQueued;
    }

    public function getDebug(): ?array
    {
        return $this->debug;
    }

    public function isDirectiveDebug(): bool
    {
        return $this->isDirectiveDebug;
    }

    public function enableDirectiveDebug(): void
    {
        $this->isDirectiveDebug = true;
    }

    public function addDebug(array $log): void
    {
        array_push($this->debug, $log);
    }

    public function resetDebug(): void
    {
        $this->debug = [];
    }
}