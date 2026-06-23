<?php

declare(strict_types=1);

namespace Tests\Integration\Adapters;

use Tests\TestCase;
use App\Adapters\DeployApi;
use Illuminate\Support\Facades\Storage;
use Exception;
use PHPUnit\Framework\Attributes\Test;

class DeployApiTest extends TestCase
{
    /** @var string Real temporary storage root */
    private string $tempStorageRoot;

    /** @var string Real local disk root for Storage::path() */
    private string $localDiskRoot;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a real temporary directory for storage
        $this->tempStorageRoot = sys_get_temp_dir() . '/deployapi-integration-' . uniqid();
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

    private function createYamlOnDisk(string $sourceFolder, string $yamlContent): void
    {
        $realSource = $this->localDiskRoot . '/' . $sourceFolder;
        @mkdir($realSource, 0755, true);
        file_put_contents($realSource . '/api.yaml', $yamlContent);
    }

    // ----------------------------------------------------------------
    //  Happy Path
    // ----------------------------------------------------------------

    #[Test]
    public function testStaticExecuteCompilesAndReturnsKey(): void
    {
        $sourceFolder = 'sources/happy-path';
        $targetFolder = 'targets/happy-path';
        $org = 'test-org';
        $env = 'test-env';
        $release = 'ReleaseAbc';

        $this->createYamlOnDisk($sourceFolder, <<<'YAML'
key: my-api-key
before:
  auth:
    directive: AuthMiddleware
endpoints:
  GET /users:
    list-users:
      directive: ListUsersHandler
      groups:
        - admin
        - user
  POST /users:
    create-user:
      directive: CreateUserHandler
after:
  log:
    directive: LogMiddleware
YAML
        );

        $result = DeployApi::staticExecute(
            $sourceFolder,
            $targetFolder,
            $org,
            $env,
            $release
        );

        $this->assertSame('my-api-key', $result);

        // Verify the compiled file was written
        $filePath = $targetFolder . '/' . $release . '.php';
        $this->assertTrue(Storage::exists($filePath));

        $compiled = Storage::get($filePath);
        $this->assertStringContainsString('class ' . $release . ' implements Api', $compiled);
        $this->assertStringContainsString('namespace App\\Apis\\' . $release . ';', $compiled);
        $this->assertStringContainsString('use App\\Core\\Api;', $compiled);
        $this->assertStringContainsString('use SplDoublyLinkedList;', $compiled);

        // Verify the compiled file has valid PHP syntax
        $checkSyntax = trim(exec('php -l ' . Storage::path($filePath)));
        $this->assertStringStartsWith('No syntax errors detected', $checkSyntax);

        // Require the compiled file (it's on disk, not autoloaded)
        require_once Storage::path($filePath);

        // Verify the serialized actions contain the expected endpoints
        $className = 'App\\Apis\\' . $release . '\\' . $release;
        $instance = new $className();
        $actions = $instance->actions();
        $this->assertContains(['GET', '/users'], $actions);
        $this->assertContains(['POST', '/users'], $actions);

        // Verify directives are SplDoublyLinkedList with DirectiveRequest objects
        $directives = $instance->getDirectives('GET', '/users');
        $this->assertInstanceOf(\SplDoublyLinkedList::class, $directives);
        $this->assertCount(3, $directives); // before + endpoint + after

        $firstDirective = $directives->shift();
        $this->assertInstanceOf(\App\Core\DirectiveRequest::class, $firstDirective);
        $this->assertSame('auth', $firstDirective->id);
        $this->assertSame('App\\Apis\\' . $release . '\\Directives\\AuthMiddleware', $firstDirective->name);
        $this->assertNull($firstDirective->groups);
    }

    #[Test]
    public function testInstanceExecuteDelegatesToStaticExecute(): void
    {
        $sourceFolder = 'sources/instance-test';
        $targetFolder = 'targets/instance-test';

        $this->createYamlOnDisk($sourceFolder, <<<'YAML'
key: instance-key
endpoints:
  DELETE /sessions:
    d1:
      directive: SessionCleaner
YAML
        );

        $deployApi = new DeployApi();

        $result = $deployApi->execute(
            $sourceFolder,
            $targetFolder,
            'test-org',
            'test-env',
            'ReleaseInstance'
        );

        $this->assertSame('instance-key', $result);
    }

    // ----------------------------------------------------------------
    //  Validation Errors — api.yaml Structure
    // ----------------------------------------------------------------

    #[Test]
    public function testThrowsExceptionWhenApiYamlNotFound(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('api.yaml is not exist');

        DeployApi::staticExecute(
            'sources/nonexistent',
            'targets/irrelevant',
            'org',
            'env',
            'release'
        );
    }

    #[Test]
    public function testThrowsExceptionWhenYamlIsInvalid(): void
    {
        $sourceFolder = 'sources/invalid-yaml';
        $this->createYamlOnDisk($sourceFolder, "<<< INVALID\nkey: value\n[[[");

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('api.yaml is not correct');

        DeployApi::staticExecute(
            $sourceFolder,
            'targets/irrelevant',
            'org',
            'env',
            'release'
        );
    }

    // ----------------------------------------------------------------
    //  Validation — key field
    // ----------------------------------------------------------------

    #[Test]
    public function testThrowsExceptionWhenKeyIsMissing(): void
    {
        $sourceFolder = 'sources/no-key';
        $this->createYamlOnDisk($sourceFolder, "endpoints:\n  GET /:\n    d1:\n      directive: Test\n");

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('key not found in yaml');

        DeployApi::staticExecute(
            $sourceFolder,
            'targets/irrelevant',
            'org',
            'env',
            'release'
        );
    }

    #[Test]
    public function testThrowsExceptionWhenKeyIsNotString(): void
    {
        $sourceFolder = 'sources/key-not-string';
        $this->createYamlOnDisk($sourceFolder, "key: 123\nendpoints:\n  GET /:\n    d1:\n      directive: Test\n");

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('key should be string in yaml');

        DeployApi::staticExecute(
            $sourceFolder,
            'targets/irrelevant',
            'org',
            'env',
            'release'
        );
    }

    // ----------------------------------------------------------------
    //  Validation — endpoints field
    // ----------------------------------------------------------------

    #[Test]
    public function testThrowsExceptionWhenEndpointsIsMissing(): void
    {
        $sourceFolder = 'sources/no-endpoints';
        $this->createYamlOnDisk($sourceFolder, "key: my-key\n");

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('endpoints not found in yaml');

        DeployApi::staticExecute(
            $sourceFolder,
            'targets/irrelevant',
            'org',
            'env',
            'release'
        );
    }

    #[Test]
    public function testThrowsExceptionWhenEndpointsIsNotArray(): void
    {
        $sourceFolder = 'sources/endpoints-not-array';
        $this->createYamlOnDisk($sourceFolder, "key: my-key\nendpoints: \"not-an-array\"\n");

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('key should be string in yaml');

        DeployApi::staticExecute(
            $sourceFolder,
            'targets/irrelevant',
            'org',
            'env',
            'release'
        );
    }

    // ----------------------------------------------------------------
    //  Validation — verb and path
    // ----------------------------------------------------------------

    #[Test]
    public function testThrowsExceptionWhenVerbIsNotAllowed(): void
    {
        $sourceFolder = 'sources/bad-verb';
        $this->createYamlOnDisk($sourceFolder, "key: my-key\nendpoints:\n  INVALID /path:\n    d1:\n      directive: Test\n");

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('INVALID not allowed in yaml');

        DeployApi::staticExecute(
            $sourceFolder,
            'targets/irrelevant',
            'org',
            'env',
            'release'
        );
    }

    #[Test]
    public function testThrowsExceptionWhenPathDoesNotStartWithSlash(): void
    {
        $sourceFolder = 'sources/bad-path';
        $this->createYamlOnDisk($sourceFolder, "key: my-key\nendpoints:\n  GET users:\n    d1:\n      directive: Test\n");

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('users should start with / in yaml');

        DeployApi::staticExecute(
            $sourceFolder,
            'targets/irrelevant',
            'org',
            'env',
            'release'
        );
    }

    // ----------------------------------------------------------------
    //  Validation — directive structure
    // ----------------------------------------------------------------

    #[Test]
    public function testThrowsExceptionWhenDirectiveKeyIsNotString(): void
    {
        $sourceFolder = 'sources/directive-no-key';
        $this->createYamlOnDisk($sourceFolder, "key: my-key\nendpoints:\n  GET /path:\n    - directive: Test\n");

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('directive without key in yaml');

        DeployApi::staticExecute(
            $sourceFolder,
            'targets/irrelevant',
            'org',
            'env',
            'release'
        );
    }

    #[Test]
    public function testThrowsExceptionWhenDirectiveIsNotArray(): void
    {
        $sourceFolder = 'sources/directive-not-array';
        $this->createYamlOnDisk($sourceFolder, "key: my-key\nendpoints:\n  GET /path:\n    d1: \"just-a-string\"\n");

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('d1 is malformed');

        DeployApi::staticExecute(
            $sourceFolder,
            'targets/irrelevant',
            'org',
            'env',
            'release'
        );
    }

    #[Test]
    public function testThrowsExceptionWhenDirectiveMissingDirectiveTag(): void
    {
        $sourceFolder = 'sources/directive-no-tag';
        $this->createYamlOnDisk($sourceFolder, "key: my-key\nendpoints:\n  GET /path:\n    d1:\n      something: else\n");

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('d1 without directive tag');

        DeployApi::staticExecute(
            $sourceFolder,
            'targets/irrelevant',
            'org',
            'env',
            'release'
        );
    }

    #[Test]
    public function testThrowsExceptionWhenDirectiveTagIsNotString(): void
    {
        $sourceFolder = 'sources/directive-bad-tag';
        $this->createYamlOnDisk($sourceFolder, "key: my-key\nendpoints:\n  GET /path:\n    d1:\n      directive: 42\n");

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('d1 with malformed directive');

        DeployApi::staticExecute(
            $sourceFolder,
            'targets/irrelevant',
            'org',
            'env',
            'release'
        );
    }

    // ----------------------------------------------------------------
    //  Validation — before / after directives
    // ----------------------------------------------------------------

    #[Test]
    public function testThrowsExceptionWhenBeforeDirectiveIsInvalid(): void
    {
        $sourceFolder = 'sources/bad-before';
        $this->createYamlOnDisk($sourceFolder, "key: my-key\nbefore:\n  - not-a-string-key\nendpoints:\n  GET /:\n    d1:\n      directive: Test\n");

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('directive without key in yaml');

        DeployApi::staticExecute(
            $sourceFolder,
            'targets/irrelevant',
            'org',
            'env',
            'release'
        );
    }

    #[Test]
    public function testThrowsExceptionWhenAfterDirectiveIsInvalid(): void
    {
        // Integer key 0 fails the string key check first (not the array check)
        $sourceFolder = 'sources/bad-after';
        $this->createYamlOnDisk($sourceFolder, "key: my-key\nafter:\n  0:\n    directive: Test\nendpoints:\n  GET /:\n    d1:\n      directive: Test\n");

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('directive without key in yaml');

        DeployApi::staticExecute(
            $sourceFolder,
            'targets/irrelevant',
            'org',
            'env',
            'release'
        );
    }

    // ----------------------------------------------------------------
    //  Compilation edge cases
    // ----------------------------------------------------------------

    #[Test]
    public function testCompilesWithMinimalConfiguration(): void
    {
        $sourceFolder = 'sources/minimal';
        $targetFolder = 'targets/minimal';

        $this->createYamlOnDisk($sourceFolder, "key: minimal-key\nendpoints:\n  GET /health:\n    h:\n      directive: HealthCheck\n");

        $result = DeployApi::staticExecute(
            $sourceFolder,
            $targetFolder,
            'org',
            'env',
            'ReleaseMinimal'
        );

        $this->assertSame('minimal-key', $result);
        $this->assertTrue(Storage::exists($targetFolder . '/ReleaseMinimal.php'));
    }

    #[Test]
    public function testMultipleEndpointsWithDifferentVerbs(): void
    {
        $sourceFolder = 'sources/multi-verbs';
        $targetFolder = 'targets/multi-verbs';

        $yaml = "key: multi-verb-key\nendpoints:\n";
        $verbs = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEADER', 'ALL'];
        foreach ($verbs as $verb) {
            $yaml .= "  $verb /" . strtolower($verb) . "-path:\n    {$verb}Handler:\n      directive: {$verb}H\n";
        }

        $this->createYamlOnDisk($sourceFolder, $yaml);

        $result = DeployApi::staticExecute(
            $sourceFolder,
            $targetFolder,
            'org',
            'env',
            'ReleaseMulti'
        );

        $this->assertSame('multi-verb-key', $result);

        // Require the compiled file (it's on disk, not autoloaded)
        require_once Storage::path($targetFolder . '/ReleaseMulti.php');

        $className = 'App\\Apis\\ReleaseMulti\\ReleaseMulti';
        $instance = new $className();
        $actions = $instance->actions();

        foreach ($verbs as $verb) {
            $this->assertContains([$verb, '/' . strtolower($verb) . '-path'], $actions);
        }
    }

    #[Test]
    public function testDirectiveWithGroupsIsSerialized(): void
    {
        $sourceFolder = 'sources/with-groups';
        $targetFolder = 'targets/with-groups';

        $this->createYamlOnDisk($sourceFolder, <<<'YAML'
key: groups-key
endpoints:
  GET /admin:
    admin-check:
      directive: AdminCheck
      groups:
        - admin
        - superadmin
YAML
        );

        $release = 'ReleaseGroups';
        DeployApi::staticExecute(
            $sourceFolder,
            $targetFolder,
            'org',
            'env',
            $release
        );

        // Require the compiled file (it's on disk, not autoloaded)
        require_once Storage::path($targetFolder . '/' . $release . '.php');

        $className = 'App\\Apis\\' . $release . '\\' . $release;
        $instance = new $className();
        $directives = $instance->getDirectives('GET', '/admin');

        $this->assertCount(1, $directives);
        $directive = $directives->shift();
        $this->assertSame(['admin', 'superadmin'], $directive->groups);
    }
}