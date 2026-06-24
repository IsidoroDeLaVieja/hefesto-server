<?php

declare(strict_types=1);

namespace App\Core;

class Map
{
    public function __construct(
        private readonly array $storage = []
    ) {}

    public function get(string $key): mixed
    {
        return $this->storage[$key] ?? null;
    }

    public function read(): array
    {
        return $this->storage;
    }
}