<?php

declare(strict_types=1);

namespace App\Core;

class FilesystemMapRepository implements MapRepositoryInterface
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * @return array<string, mixed>
     */
    public function load(string $key): array
    {
        $path = $this->basePath . 'Maps/' . $key . '.json';

        if (!file_exists($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }
}