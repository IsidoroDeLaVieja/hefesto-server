<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Core\Memory;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CachedFileMemory implements Memory {

    private readonly string $prefix;

    public function __construct(string $key) {
        $this->prefix = $key;
    }

    public function get(string $key): mixed
    {
        $fullKey = $this->prefix.'/'.$key;

        $cached = Redis::get($fullKey);
        if ($cached !== false && $cached !== null) {
            return unserialize($cached, ['allowed_classes' => true]);
        }

        return $this->loadFromStorage($fullKey);
    }

    public function set(string $key, $value): void
    {
        $fullKey = $this->prefix.'/'.$key;
        $serialized = serialize($value);

        Storage::put($fullKey, $serialized);
        Redis::set($fullKey, $serialized);
    }

    public function read(): array
    {
        try {
            return Storage::files($this->prefix);
        } catch (Throwable $e) {
            return [];
        }
    }

    private function loadFromStorage(string $key): mixed
    {
        try {
            $value = Storage::get($key);
        } catch (Throwable $e) {
            $value = serialize(null);
        }

        if ($value !== null) {
            Redis::set($key, $value);
            return unserialize($value, ['allowed_classes' => true]);
        }

        return null;
    }
}