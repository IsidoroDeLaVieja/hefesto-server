<?php

declare(strict_types=1);

namespace Tests\Fixture;

use App\Core\MapRepositoryInterface;

class InMemoryMapRepository implements MapRepositoryInterface
{
    private string $basePath;

    /** @var array<string, array<string, mixed>> */
    private static $preloaded = [];

    public function __construct(string $basePath = '')
    {
        $this->basePath = $basePath;
    }

    /**
     * Preload map data for a given key (used in tests).
     *
     * @param array<string, mixed> $data
     */
    public static function preload(string $key, array $data): void
    {
        self::$preloaded[$key] = $data;
    }

    public static function clear(): void
    {
        self::$preloaded = [];
    }

    /**
     * @return array<string, mixed>
     */
    public function load(string $key): array
    {
        // Return preloaded data if available
        if (isset(self::$preloaded[$key])) {
            return self::$preloaded[$key];
        }

        // Fallback: try to load from filesystem (for backward compat with existing tests)
        if ($this->basePath !== '') {
            $path = $this->basePath . 'Maps/' . $key . '.json';
            if (file_exists($path)) {
                $contents = file_get_contents($path);
                if ($contents !== false) {
                    $decoded = json_decode($contents, true);
                    return is_array($decoded) ? $decoded : [];
                }
            }
        }

        return [];
    }
}