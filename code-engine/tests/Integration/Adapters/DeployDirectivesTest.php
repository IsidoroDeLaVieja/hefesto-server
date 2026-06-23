<?php

declare(strict_types=1);

namespace Tests\Integration\Adapters;

use Tests\TestCase;
use App\Adapters\DeployDirectives;
use Illuminate\Support\Facades\Storage;
use Exception;
use PHPUnit\Framework\Attributes\Test;

class DeployDirectivesTest extends TestCase
{
    /** @var string Real temporary storage root */
    private string $tempStorageRoot;

    /** @var string Real local disk root for Storage::path() */
    private string $localDiskRoot;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a real temporary directory for storage
        $this->tempStorageRoot = sys_get_temp_dir() . '/deploydirectives-integration-' . uniqid();
        @mkdir($this->tempStorageRoot, 0755, true);
        @mkdir($this->tempStorageRoot . '/storage/app', 0755, true);

        // Configure the local disk root for Storage::path() resolution
        $this->localDiskRoot = $this->tempStorageRoot . '/storage/app';
        config(['filesystems.disks.local' => [
            'driver' => 'local',
            'root' => $this->localDiskRoot,
        ]]);

        // Set a test API namespace
        config(['app.API_NAMESPACE' => 'App\\Apis\\']);
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
     * Create a single valid directive file on disk.
     */
    private function createDirectiveOnDisk(string $sourceFolder, string $filename, string $bodyCode): void
    {
        $realSource = $this->localDiskRoot . '/' . $sourceFolder . '/Directives';
        @mkdir($realSource, 0755, true);
        $content = '<?php /*dlv-code-engine***/' . "\n" . $bodyCode;
        file_put_contents($realSource . '/' . $filename, $content);
    }

    // ----------------------------------------------------------------
    //  Happy Path
    // ----------------------------------------------------------------

    #[Test]
    public function testStaticExecuteCompilesSingleDirective(): void
    {
        $sourceFolder = 'sources/happy-path';
        $targetFolder = 'targets/happy-path';
        $release = 'ReleaseAbc';

        $this->createDirectiveOnDisk($sourceFolder, 'MyHandler.php', 'echo "hello";');

        DeployDirectives::staticExecute($sourceFolder, $targetFolder, $release);

        // Verify the compiled file was written
        $filePath = $targetFolder . '/Directives/MyHandler.php';
        $this->assertTrue(Storage::exists($filePath));

        $compiled = Storage::get($filePath);
        $this->assertStringContainsString('namespace App\\Apis\\' . $release . '\\Directives;', $compiled);
        $this->assertStringContainsString('class MyHandler extends Directive', $compiled);
        $this->assertStringContainsString('echo "hello";', $compiled);
        $this->assertStringContainsString('use App\\Core\\Directive;', $compiled);
        $this->assertStringContainsString('use App\\Core\\State;', $compiled);
        $this->assertStringContainsString('use Exception;', $compiled);

        // Verify the compiled file has valid PHP syntax
        $checkSyntax = trim(exec('php -l ' . Storage::path($filePath)));
        $this->assertStringStartsWith('No syntax errors detected', $checkSyntax);
    }

    #[Test]
    public function testStaticExecuteCompilesMultipleDirectives(): void
    {
        $sourceFolder = 'sources/multi';
        $targetFolder = 'targets/multi';
        $release = 'ReleaseMulti';

        $this->createDirectiveOnDisk($sourceFolder, 'Auth.php', '$state->set("auth", true);');
        $this->createDirectiveOnDisk($sourceFolder, 'Logger.php', '$state->set("log", "ok");');

        DeployDirectives::staticExecute($sourceFolder, $targetFolder, $release);

        $this->assertTrue(Storage::exists($targetFolder . '/Directives/Auth.php'));
        $this->assertTrue(Storage::exists($targetFolder . '/Directives/Logger.php'));

        // Verify syntax on both compiled files
        $checkSyntax1 = trim(exec('php -l ' . Storage::path($targetFolder . '/Directives/Auth.php')));
        $this->assertStringStartsWith('No syntax errors detected', $checkSyntax1);

        $checkSyntax2 = trim(exec('php -l ' . Storage::path($targetFolder . '/Directives/Logger.php')));
        $this->assertStringStartsWith('No syntax errors detected', $checkSyntax2);
    }

    #[Test]
    public function testStaticExecuteWithCodeContainingSpecialCharacters(): void
    {
        $sourceFolder = 'sources/special-chars';
        $targetFolder = 'targets/special-chars';
        $release = 'ReleaseSpecial';

        $code = <<<'PHP'
$result = $state->get("nested.key") ?? 'default';
$config['timeout'] = 30;
PHP;

        $this->createDirectiveOnDisk($sourceFolder, 'SpecialHandler.php', $code);

        DeployDirectives::staticExecute($sourceFolder, $targetFolder, $release);

        $filePath = $targetFolder . '/Directives/SpecialHandler.php';
        $this->assertTrue(Storage::exists($filePath));

        $compiled = Storage::get($filePath);
        $this->assertStringContainsString('$result = $state->get("nested.key")', $compiled);
        $this->assertStringContainsString('$config[\'timeout\'] = 30;', $compiled);

        $checkSyntax = trim(exec('php -l ' . Storage::path($filePath)));
        $this->assertStringStartsWith('No syntax errors detected', $checkSyntax);
    }

    // ----------------------------------------------------------------
    //  Instance execute delegates to staticExecute
    // ----------------------------------------------------------------

    #[Test]
    public function testInstanceExecuteDelegatesToStaticExecute(): void
    {
        $sourceFolder = 'sources/instance-test';
        $targetFolder = 'targets/instance-test';
        $release = 'ReleaseInstance';

        $this->createDirectiveOnDisk($sourceFolder, 'TestDirective.php', 'echo "ok";');

        $deployDirectives = new DeployDirectives();
        $deployDirectives->execute($sourceFolder, $targetFolder, $release);

        $filePath = $targetFolder . '/Directives/TestDirective.php';
        $this->assertTrue(Storage::exists($filePath));

        $compiled = Storage::get($filePath);
        $this->assertStringContainsString('namespace App\\Apis\\' . $release . '\\Directives;', $compiled);
    }

    // ----------------------------------------------------------------
    //  Validation Errors
    // ----------------------------------------------------------------

    #[Test]
    public function testThrowsExceptionWhenDirectivesFolderIsEmpty(): void
    {
        $sourceFolder = 'sources/empty';
        $targetFolder = 'targets/empty';
        // Create the folder without any files
        @mkdir($this->localDiskRoot . '/' . $sourceFolder . '/Directives', 0755, true);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('0 directives in your api');

        DeployDirectives::staticExecute($sourceFolder, $targetFolder, 'ReleaseEmpty');
    }

    #[Test]
    public function testThrowsExceptionWhenDirectivesFolderDoesNotExist(): void
    {
        $sourceFolder = 'sources/nonexistent';
        $targetFolder = 'targets/nonexistent';

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('0 directives in your api');

        DeployDirectives::staticExecute($sourceFolder, $targetFolder, 'ReleaseNonexistent');
    }

    #[Test]
    public function testThrowsExceptionWhenHeaderIsMissing(): void
    {
        $sourceFolder = 'sources/no-header';
        $targetFolder = 'targets/no-header';

        // Create a file without the required header
        $realSource = $this->localDiskRoot . '/' . $sourceFolder . '/Directives';
        @mkdir($realSource, 0755, true);
        file_put_contents($realSource . '/BadDirective.php', '<?php echo "no header";');

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('should have <?php /*dlv-code-engine***/');

        DeployDirectives::staticExecute($sourceFolder, $targetFolder, 'ReleaseNoHeader');
    }

    #[Test]
    public function testThrowsExceptionWhenFileHasNoPhpExtension(): void
    {
        $sourceFolder = 'sources/no-ext';
        $targetFolder = 'targets/no-ext';

        $realSource = $this->localDiskRoot . '/' . $sourceFolder . '/Directives';
        @mkdir($realSource, 0755, true);
        file_put_contents(
            $realSource . '/NoExt.txt',
            '<?php /*dlv-code-engine***/' . "\n" . 'echo "ok";'
        );

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('has no php extension');

        DeployDirectives::staticExecute($sourceFolder, $targetFolder, 'ReleaseNoExt');
    }

    #[Test]
    public function testThrowsExceptionWhenPhpSyntaxIsInvalid(): void
    {
        $sourceFolder = 'sources/bad-syntax';
        $targetFolder = 'targets/bad-syntax';

        $realSource = $this->localDiskRoot . '/' . $sourceFolder . '/Directives';
        @mkdir($realSource, 0755, true);
        file_put_contents(
            $realSource . '/BadSyntax.php',
            '<?php /*dlv-code-engine***/' . "\n" . 'echo "hello'
        );

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);

        DeployDirectives::staticExecute($sourceFolder, $targetFolder, 'ReleaseBadSyntax');
    }

    // ----------------------------------------------------------------
    //  Edge Cases — compiled output validation
    // ----------------------------------------------------------------

    #[Test]
    public function testCompiledFileIsRegularPhpClass(): void
    {
        $sourceFolder = 'sources/class-check';
        $targetFolder = 'targets/class-check';
        $release = 'ReleaseClassCheck';

        $this->createDirectiveOnDisk($sourceFolder, 'Validator.php', '// just a comment');

        DeployDirectives::staticExecute($sourceFolder, $targetFolder, $release);

        $filePath = $targetFolder . '/Directives/Validator.php';
        $compiled = Storage::get($filePath);

        // The compiled class should have the exact structure expected
        $this->assertStringContainsString('<?php /*dlv-code-engine***/', $compiled);
        $this->assertStringContainsString('declare(strict_types=1);', $compiled);
        $this->assertStringContainsString('namespace App\\Apis\\' . $release . '\\Directives;', $compiled);
        $this->assertStringContainsString('class Validator extends Directive', $compiled);
        $this->assertStringContainsString('protected function execute(State $state, array $config) : void', $compiled);
        $this->assertStringContainsString('// just a comment', $compiled);
    }
}