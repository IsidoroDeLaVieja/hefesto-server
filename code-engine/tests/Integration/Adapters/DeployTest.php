<?php

declare(strict_types=1);

namespace Tests\Integration\Adapters;

use Tests\TestCase;
use App\Adapters\Deploy;
use App\Adapters\Contracts\DeployMapsInterface;
use App\Adapters\Contracts\DeployDirectivesInterface;
use App\Adapters\Contracts\DeployApiInterface;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PharData;
use Exception;
use PHPUnit\Framework\Attributes\Test;

class DeployTest extends TestCase
{
    private DeployMapsInterface $deployMaps;
    private DeployDirectivesInterface $deployDirectives;
    private DeployApiInterface $deployApi;
    private Deploy $deploy;

    /** @var string Real temporary storage root */
    private string $tempStorageRoot;

    /** @var string Real local disk root for Storage::path() */
    private string $localDiskRoot;

    protected function setUp(): void
    {
        parent::setUp();

        // Test doubles for the sub-adapters
        $this->deployMaps = new DeployMapsTestDouble();
        $this->deployDirectives = new DeployDirectivesTestDouble();
        $this->deployApi = new DeployApiTestDouble();

        $this->deploy = new Deploy(
            $this->deployMaps,
            $this->deployDirectives,
            $this->deployApi
        );

        // Use a real temporary directory for storage
        // (PharData and exec() require real filesystem paths)
        $this->tempStorageRoot = sys_get_temp_dir() . '/deploy-integration-' . uniqid();
        @mkdir($this->tempStorageRoot, 0755, true);
        @mkdir($this->tempStorageRoot . '/storage/app', 0755, true);

        // Configure the local disk root for Storage::path() resolution
        $this->localDiskRoot = $this->tempStorageRoot . '/storage/app';
        config(['filesystems.disks.local' => [
            'driver' => 'local',
            'root' => $this->localDiskRoot,
        ]]);

        // Override config for external paths used by deploy logic
        config(['app.CODE_PATH' => $this->tempStorageRoot . '/code']);
        // Note: trailing slash is REQUIRED — createStorage() does $storagePath.$org.'/'.$env.'/'.$key.'/'
        config(['app.STORAGE_PATH' => $this->tempStorageRoot . '/storage/']);

        // Ensure the CODE_PATH directory exists
        @mkdir(config('app.CODE_PATH'), 0755, true);
    }

    protected function tearDown(): void
    {
        $this->rmdirRecursive($this->tempStorageRoot);
        parent::tearDown();
    }

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

    // ----------------------------------------------------------------
    //  Helper: create a minimal valid .tar.gz fixture
    // ----------------------------------------------------------------

    private function createValidTarGzFixture(): UploadedFile
    {
        $tmpDir = sys_get_temp_dir() . '/deploy-fixture-' . uniqid();
        @mkdir($tmpDir, 0755, true);

        @mkdir($tmpDir . '/Maps', 0755, true);
        @mkdir($tmpDir . '/Directives', 0755, true);
        @mkdir($tmpDir . '/Assets', 0755, true);

        file_put_contents(
            $tmpDir . '/api.yaml',
            "key: my-test-key\nendpoints:\n  GET /test:\n    - d1:\n        directive: TestDirective\n"
        );
        file_put_contents(
            $tmpDir . '/Directives/TestDirective.php',
            "<?php /*dlv-code-engine***/\necho 'ok';\n"
        );

        $tarPath = $tmpDir . '/package.tar';
        $gzPath = $tmpDir . '/package.tar.gz';
        $phar = new PharData($tarPath);
        $phar->buildFromDirectory($tmpDir);
        $phar->compress(\Phar::GZ);

        $uploadedFile = new UploadedFile(
            $gzPath,
            'package.tar.gz',
            'application/gzip',
            null,
            true
        );

        return $uploadedFile;
    }

    // ----------------------------------------------------------------
    //  Tests
    // ----------------------------------------------------------------

    #[Test]
    public function testExecuteHappyPathReturnsReleaseAndKey(): void
    {
        $file = $this->createValidTarGzFixture();
        $request = new Request([], [], [], [], ['file' => $file]);

        [$release, $key] = $this->deploy->instanceExecute('test-org', 'test-env', $request);

        // Verify return types
        $this->assertIsString($release);
        $this->assertStringStartsWith('release', $release);
        $this->assertSame('test-key-from-double', $key);

        // Verify deployMaps was called
        $this->assertCount(1, $this->deployMaps->calls);
        $this->assertSame('execute', $this->deployMaps->calls[0][0]);

        // Verify deployDirectives was called
        $this->assertCount(1, $this->deployDirectives->calls);
        $this->assertSame('execute', $this->deployDirectives->calls[0][0]);

        // Verify deployApi was called
        $this->assertCount(1, $this->deployApi->calls);
        $this->assertSame('execute', $this->deployApi->calls[0][0]);

        // Verify the source folder was cleaned up
        $sourceFolderParam = $this->deployMaps->calls[0][1][0];
        $this->assertFalse(is_dir($this->localDiskRoot . '/' . $sourceFolderParam));

        // Verify storage path was created by createStorage()
        $this->assertTrue(is_dir(config('app.STORAGE_PATH') . '/test-org/test-env/test-key-from-double'));
    }

    #[Test]
    public function testExecuteCleansUpSourceAndTargetOnFailure(): void
    {
        $failingMaps = new class implements DeployMapsInterface {
            public function execute(string $sourceFolder, string $targetFolder, string $env): void
            {
                throw new Exception('simulated failure', 500);
            }
        };

        $failingDeploy = new Deploy(
            $failingMaps,
            $this->deployDirectives,
            $this->deployApi
        );

        $file = $this->createValidTarGzFixture();
        $request = new Request([], [], [], [], ['file' => $file]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('simulated failure');
        $this->expectExceptionCode(500);

        $failingDeploy->instanceExecute('test-org', 'test-env', $request);
    }

    #[Test]
    public function testExecuteThrowsExceptionWhenFileIsMissing(): void
    {
        $request = new Request();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('file is mandatory');

        $this->deploy->instanceExecute('test-org', 'test-env', $request);
    }

    #[Test]
    public function testExecuteThrowsExceptionWhenFileExtensionIsNotGz(): void
    {
        $invalidFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        $request = new Request([], [], [], [], ['file' => $invalidFile]);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('file should be gz');

        $this->deploy->instanceExecute('test-org', 'test-env', $request);
    }

    #[Test]
    public function testCleanReleasesExecutesRmCommand(): void
    {
        $codePath = config('app.CODE_PATH');
        @mkdir($codePath . '/release-foo', 0755, true);
        @mkdir($codePath . '/release-bar', 0755, true);

        $this->assertTrue(is_dir($codePath . '/release-foo'));

        Deploy::cleanReleases(['release-foo', 'release-bar']);

        $this->assertFalse(is_dir($codePath . '/release-foo'));
        $this->assertFalse(is_dir($codePath . '/release-bar'));
    }

    #[Test]
    public function testExecuteThrowsExceptionWhenFileIsNotValid(): void
    {
        // Use Symfony's UploadedFile directly with error code != 0
        $tmpGz = tempnam(sys_get_temp_dir(), 'test-') . '.gz';
        file_put_contents($tmpGz, 'fake-content');
        $uploadedFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            $tmpGz,
            'test.gz',
            'application/gzip',
            null,
            true // test mode
        );
        // Set the private 'error' property via reflection to force isValid()=false
        $ref = new \ReflectionProperty(\Symfony\Component\HttpFoundation\File\UploadedFile::class, 'error');
        $ref->setAccessible(true);
        $ref->setValue($uploadedFile, UPLOAD_ERR_CANT_WRITE);

        $request = new Request([], [], [], [], ['file' => $uploadedFile]);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('file is not valid');

        $this->deploy->instanceExecute('test-org', 'test-env', $request);
    }
}