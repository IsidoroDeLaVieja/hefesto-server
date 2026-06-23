<?php

declare(strict_types=1);

namespace Tests\Integration\Adapters;

use App\Adapters\Contracts\DeployMapsInterface;
use Illuminate\Support\Facades\Storage;

class DeployMapsTestDouble implements DeployMapsInterface
{
    public array $calls = [];

    public function execute(string $sourceFolder, string $targetFolder, string $env): void
    {
        $this->calls[] = ['execute', func_get_args()];

        // Create the target directories on real disk so subsequent exec() commands work
        $realTarget = Storage::path($targetFolder);
        @mkdir($realTarget . '/Maps', 0755, true);

        // Ensure Assets dir exists in source for deployAssets step
        $realSource = Storage::path($sourceFolder);
        @mkdir($realSource . '/Assets', 0755, true);
    }
}