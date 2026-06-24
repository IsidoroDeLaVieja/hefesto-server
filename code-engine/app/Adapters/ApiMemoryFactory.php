<?php

declare(strict_types=1);

namespace App\Adapters;

class ApiMemoryFactory
{
    private const MEMORY_PATH_PREFIX = 'api-memory/';

    public function make(string $org, string $env, string $key): CachedFileMemory
    {
        $sanitizedKey = $this->sanitize($org, $env, $key);

        return new CachedFileMemory(self::MEMORY_PATH_PREFIX . $sanitizedKey);
    }

    private function sanitize(string $org, string $env, string $key): string
    {
        $parts = [$org, $env, $key];

        $parts = array_map(
            fn(string $part): string => str_replace('/', '-', $part),
            $parts
        );

        return implode('-', $parts);
    }
}