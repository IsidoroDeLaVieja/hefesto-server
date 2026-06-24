<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Adapters\Contracts\DeployMapsInterface;
use Exception;
use Illuminate\Support\Facades\Storage;

class DeployMaps implements DeployMapsInterface
{
    public function execute(
        string $sourceFolder,
        string $targetFolder,
        string $env
    ): void {
        $files = array_merge(
            Storage::files("{$sourceFolder}/Maps"),
            Storage::files("{$sourceFolder}/Maps/{$env}")
        );

        $this->validateList($files);
        $this->copyList($files, $targetFolder);
    }

    private function validateList(array $files): void
    {
        foreach ($files as $file) {
            $this->ensureIsJsonMap($file);
        }
    }

    private function ensureIsJsonMap(string $file): void
    {
        if (!str_ends_with($file, '.json')) {
            throw new Exception(
                'your map ' . basename($file) . ' is not json',
                400
            );
        }

        $content = Storage::get($file);

        if (!is_array(json_decode($content, true))) {
            throw new Exception(
                'your map ' . basename($file) . ' is badly formed',
                400
            );
        }
    }

    private function copyList(array $files, string $targetFolder): void
    {
        foreach ($files as $file) {
            Storage::copy($file, "{$targetFolder}/Maps/" . basename($file));
        }
    }
}