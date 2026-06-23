<?php

declare(strict_types=1);

namespace Tests\Integration\Adapters;

use App\Adapters\Contracts\DeployApiInterface;
use Illuminate\Support\Facades\Storage;

class DeployApiTestDouble implements DeployApiInterface
{
    public array $calls = [];

    public function execute(string $sourceFolder, string $targetFolder, string $org, string $env, string $release): string
    {
        $this->calls[] = ['execute', func_get_args()];

        // Create the .php marker file that deploy() checks for after moving the release
        $realTarget = Storage::path($targetFolder);
        file_put_contents($realTarget . '/' . $release . '.php', '<?php // test marker');

        return 'test-key-from-double';
    }
}