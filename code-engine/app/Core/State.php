<?php

declare(strict_types=1);

namespace App\Core;

class State
{
    private bool $isDirectiveDebug = false;
    private bool $isQueued = false;
    private string $id;
    private ?Memory $apiMemory = null;
    private ?array $maps = null;
    private Alias $alias;
    private array $debug = [];

    public function __construct(
        private readonly Message $message,
        array $config,
        private readonly Groups $groups = new Groups(),
        private readonly Memory $memory = new ExecutionTimeMemory(),
        private readonly MapRepositoryInterface $mapRepository = new FilesystemMapRepository(''),
    ) {
        $this->id = $config['id'] ?? uniqid('', true);
        $this->apiMemory = $config['apiMemory'] ?? null;

        $this->memory->set('hefesto-org', $config['organization']);
        $this->memory->set('hefesto-env', $config['environment']);
        $this->memory->set('hefesto-api', $config['keyApi']);
        $this->memory->set('hefesto-localhost', $config['localhost']);
        $this->memory->set('hefesto-pathcode', $config['codePath']);
        $this->memory->set('hefesto-pathstorage', $config['storagePath']);
        $this->memory->set('hefesto-definitionpath', $config['definitionPath']);
        $this->memory->set('hefesto-definitionverb', $config['definitionVerb']);

        $apiMaps = $this->apiMemory?->get('api-maps');
        $this->maps = $apiMaps !== null ? (array) $apiMaps : null;

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
            $this->apiMemory?->set('api-maps', $this->maps);
        }

        return $this->maps[$key];
    }

    public function alias(mixed $key): mixed
    {
        return $this->alias->find($key);
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
        $this->debug[] = $log;
    }

    public function resetDebug(): void
    {
        $this->debug = [];
    }
}