<?php

declare(strict_types=1);

namespace Tests\Integration\Adapters;

use App\Adapters\Contracts\DeployDirectivesInterface;
use Illuminate\Support\Facades\Storage;

class DeployDirectivesTestDouble implements DeployDirectivesInterface
{
    public array $calls = [];

    public function execute(string $sourceFolder, string $targetFolder, string $release): void
    {
        $this->calls[] = ['execute', func_get_args()];

        // Create the target Directories on real disk so subsequent exec() commands work
        $realTarget = Storage::path($targetFolder);
        @mkdir($realTarget . '/Directives', 0755, true);
    }
}