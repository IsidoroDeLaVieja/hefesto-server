<?php

declare(strict_types=1);

namespace Tests\Integration\Adapters;

use Tests\TestCase;
use App\Adapters\DeployMaps;
use Illuminate\Support\Facades\Storage;
use Exception;
use PHPUnit\Framework\Attributes\Test;

class DeployMapsTest extends TestCase
{
    /** @var string Real temporary storage root */
    private string $tempStorageRoot;

    /** @var string Real local disk root for Storage::path() */
    private string $localDiskRoot;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a real temporary directory for storage
        $this->tempStorageRoot = sys_get_temp_dir() . '/deploymaps-integration-' . uniqid();
        @mkdir($this->tempStorageRoot, 0755, true);
        @mkdir($this->tempStorageRoot . '/storage/app', 0755, true);

        // Configure the local disk root for Storage::path() resolution
        $this->localDiskRoot = $this->tempStorageRoot . '/storage/app';
        config(['filesystems.disks.local' => [
            'driver' => 'local',
            'root' => $this->localDiskRoot,
        ]]);
    }

    protected function tearDown(): void
    {
        $this->rmdirRecursive($this->tempStorageRoot);
        parent::tearDown();
    }

    // ----------------------------------------------------------------
    //  Helpers
    // ----------------------------------------------------------------

    private function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->rmdirRecursive($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Create a valid JSON map file on disk inside both Maps/ and optionally Maps/{env}/.
     */
    private function createMapOnDisk(string $sourceFolder, string $env, string $filename, array $mapData, bool $inEnv = false): void
    {
        $subDir = $inEnv ? '/Maps/' . $env : '/Maps';
        $realSource = $this->localDiskRoot . '/' . $sourceFolder . $subDir;
        @mkdir($realSource, 0755, true);
        file_put_contents($realSource . '/' . $filename, json_encode($mapData, JSON_PRETTY_PRINT));
    }

    /**
     * Create a file in a maps folder with arbitrary content (for invalid content tests).
     */
    private function createRawFileOnDisk(string $sourceFolder, string $env, string $filename, string $content, bool $inEnv = false): void
    {
        $subDir = $inEnv ? '/Maps/' . $env : '/Maps';
        $realSource = $this->localDiskRoot . '/' . $sourceFolder . $subDir;
        @mkdir($realSource, 0755, true);
        file_put_contents($realSource . '/' . $filename, $content);
    }

    // ----------------------------------------------------------------
    //  Happy Path
    // ----------------------------------------------------------------

    #[Test]
    public function testExecuteCopiesSingleMap(): void
    {
        $sourceFolder = 'sources/single-map';
        $targetFolder = 'targets/single-map';
        $env = 'production';

        $this->createMapOnDisk($sourceFolder, $env, 'routes.json', ['route' => '/home']);

        $deployMaps = new DeployMaps();
        $deployMaps->execute($sourceFolder, $targetFolder, $env);

        // Verify the file was copied to target
        $filePath = $targetFolder . '/Maps/routes.json';
        $this->assertTrue(Storage::exists($filePath));

        $copied = json_decode(Storage::get($filePath), true);
        $this->assertSame(['route' => '/home'], $copied);
    }

    #[Test]
    public function testExecuteCopiesMultipleMaps(): void
    {
        $sourceFolder = 'sources/multi-map';
        $targetFolder = 'targets/multi-map';
        $env = 'production';

        $this->createMapOnDisk($sourceFolder, $env, 'routes.json', ['route' => '/home']);
        $this->createMapOnDisk($sourceFolder, $env, 'validation.json', ['rules' => 'strict']);

        $deployMaps = new DeployMaps();
        $deployMaps->execute($sourceFolder, $targetFolder, $env);

        $this->assertTrue(Storage::exists($targetFolder . '/Maps/routes.json'));
        $this->assertTrue(Storage::exists($targetFolder . '/Maps/validation.json'));

        $routes = json_decode(Storage::get($targetFolder . '/Maps/routes.json'), true);
        $this->assertSame(['route' => '/home'], $routes);

        $validation = json_decode(Storage::get($targetFolder . '/Maps/validation.json'), true);
        $this->assertSame(['rules' => 'strict'], $validation);
    }

    #[Test]
    public function testExecuteMergesEnvSpecificMapsWithCommonMaps(): void
    {
        $sourceFolder = 'sources/env-merging';
        $targetFolder = 'targets/env-merging';
        $env = 'staging';

        // Common map (in Maps/)
        $this->createMapOnDisk($sourceFolder, $env, 'common.json', ['scope' => 'all']);

        // Env-specific map (in Maps/staging/)
        $this->createMapOnDisk($sourceFolder, $env, 'staging-only.json', ['env' => 'staging'], true);

        $deployMaps = new DeployMaps();
        $deployMaps->execute($sourceFolder, $targetFolder, $env);

        // Both files should be copied to target
        $this->assertTrue(Storage::exists($targetFolder . '/Maps/common.json'));
        $this->assertTrue(Storage::exists($targetFolder . '/Maps/staging-only.json'));

        $common = json_decode(Storage::get($targetFolder . '/Maps/common.json'), true);
        $this->assertSame(['scope' => 'all'], $common);

        $stagingOnly = json_decode(Storage::get($targetFolder . '/Maps/staging-only.json'), true);
        $this->assertSame(['env' => 'staging'], $stagingOnly);
    }

    // ----------------------------------------------------------------
    //  Instance execute
    // ----------------------------------------------------------------

    #[Test]
    public function testInstanceExecuteCopiesMaps(): void
    {
        $sourceFolder = 'sources/instance-test';
        $targetFolder = 'targets/instance-test';
        $env = 'testing';

        $this->createMapOnDisk($sourceFolder, $env, 'test.json', ['test' => true]);

        $deployMaps = new DeployMaps();
        $deployMaps->execute($sourceFolder, $targetFolder, $env);

        $filePath = $targetFolder . '/Maps/test.json';
        $this->assertTrue(Storage::exists($filePath));

        $copied = json_decode(Storage::get($filePath), true);
        $this->assertSame(['test' => true], $copied);
    }

    // ----------------------------------------------------------------
    //  Validation Errors
    // ----------------------------------------------------------------

    #[Test]
    public function testThrowsExceptionWhenFileIsNotJsonExtension(): void
    {
        $sourceFolder = 'sources/bad-extension';
        $targetFolder = 'targets/bad-extension';
        $env = 'production';

        // Create a file without .json extension inside Maps/
        $realSource = $this->localDiskRoot . '/' . $sourceFolder . '/Maps';
        @mkdir($realSource, 0755, true);
        file_put_contents($realSource . '/routes.txt', json_encode(['route' => '/home']));

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('your map routes.txt is not json');

        $deployMaps = new DeployMaps();
        $deployMaps->execute($sourceFolder, $targetFolder, $env);
    }

    #[Test]
    public function testThrowsExceptionWhenMapContentIsNotAnArray(): void
    {
        $sourceFolder = 'sources/bad-content';
        $targetFolder = 'targets/bad-content';
        $env = 'production';

        // Valid .json extension but content is a string, not an array
        $this->createRawFileOnDisk($sourceFolder, $env, 'malformed.json', '"just a string"');

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('your map malformed.json is badly formed');

        $deployMaps = new DeployMaps();
        $deployMaps->execute($sourceFolder, $targetFolder, $env);
    }

    #[Test]
    public function testThrowsExceptionWhenEnvMapIsNotJson(): void
    {
        $sourceFolder = 'sources/env-bad-ext';
        $targetFolder = 'targets/env-bad-ext';
        $env = 'staging';

        // Valid map in common Maps/
        $this->createMapOnDisk($sourceFolder, $env, 'common.json', ['ok' => true]);

        // Invalid extension in Maps/staging/
        $realSource = $this->localDiskRoot . '/' . $sourceFolder . '/Maps/' . $env;
        @mkdir($realSource, 0755, true);
        file_put_contents($realSource . '/bad-map.xml', '<map><item>value</item></map>');

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('your map bad-map.xml is not json');

        $deployMaps = new DeployMaps();
        $deployMaps->execute($sourceFolder, $targetFolder, $env);
    }

    // ----------------------------------------------------------------
    //  Edge Cases
    // ----------------------------------------------------------------

    #[Test]
    public function testExecuteWithEmptySourceDoesNotThrow(): void
    {
        $sourceFolder = 'sources/empty-source';
        $targetFolder = 'targets/empty-source';
        $env = 'production';

        // Create empty Maps/ and Maps/production/ directories
        @mkdir($this->localDiskRoot . '/' . $sourceFolder . '/Maps', 0755, true);
        @mkdir($this->localDiskRoot . '/' . $sourceFolder . '/Maps/' . $env, 0755, true);

        // Should not throw — empty file list copies nothing
        $deployMaps = new DeployMaps();
        $deployMaps->execute($sourceFolder, $targetFolder, $env);

        // No files should be in target
        $this->assertFalse(Storage::exists($targetFolder . '/Maps'));
    }

    #[Test]
    public function testExecuteWithOnlyEnvMapsCopiesThem(): void
    {
        $sourceFolder = 'sources/only-env';
        $targetFolder = 'targets/only-env';
        $env = 'development';

        // Only maps in Maps/development/, nothing in Maps/
        $this->createMapOnDisk($sourceFolder, $env, 'dev-only.json', ['env' => 'dev'], true);

        $deployMaps = new DeployMaps();
        $deployMaps->execute($sourceFolder, $targetFolder, $env);

        $filePath = $targetFolder . '/Maps/dev-only.json';
        $this->assertTrue(Storage::exists($filePath));

        $copied = json_decode(Storage::get($filePath), true);
        $this->assertSame(['env' => 'dev'], $copied);
    }
}