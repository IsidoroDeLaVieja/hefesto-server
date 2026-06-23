<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

use App\Core\ApiStorage;
use App\Core\Memory;
use DateTime;

class ApiStorageTest extends TestCase
{
    private $memory;
    private $apiStorage;

    protected function setUp(): void
    {
        $this->memory = $this->getMockBuilder(Memory::class)
            ->onlyMethods([
                'get',
                'set',
                'read',
            ])->getMock();

        $this->apiStorage = new ApiStorage($this->memory);
    }

    public function testSetNewApi(): void
    {
        $org = 'isidoro';
        $env = 'test';
        $keyApi = 'ping';
        $release = 'release34';
        $active = true;
        $public = true;

        // find() returns null (new API)
        $this->memory->expects($this->once())
            ->method('get')
            ->with("org-$org-env-$env-key-$keyApi")
            ->willReturn(null);

        $this->memory->expects($this->once())
            ->method('set')
            ->with(
                "org-$org-env-$env-key-$keyApi",
                $this->callback(function (array $data) use ($keyApi, $release, $active, $public) {
                    $this->assertSame($keyApi, $data['key']);
                    $this->assertSame($release, $data['release']);
                    $this->assertSame($active, $data['active']);
                    $this->assertSame($public, $data['public']);
                    $this->assertNotEmpty($data['created_at']);
                    $this->assertNotEmpty($data['updated_at']);
                    $this->assertSame([], $data['releases']);

                    return true;
                })
            );

        $result = $this->apiStorage->set($org, $env, $keyApi, $release, $active, $public);

        $this->assertSame([], $result);
    }

    public function testSetExistingApiWithDifferentRelease(): void
    {
        $org = 'isidoro';
        $env = 'test';
        $keyApi = 'ping';
        $release = 'release34';
        $active = true;
        $public = true;

        $existingApi = [
            'key' => $keyApi,
            'release' => 'one',
            'active' => false,
            'created_at' => '2020-01-01',
            'updated_at' => '2020-01-01',
            'releases' => ['two'],
            'public' => false,
        ];

        $this->memory->expects($this->once())
            ->method('get')
            ->with("org-$org-env-$env-key-$keyApi")
            ->willReturn($existingApi);

        $this->memory->expects($this->once())
            ->method('set')
            ->with(
                "org-$org-env-$env-key-$keyApi",
                $this->callback(function (array $data) use ($keyApi, $release, $active, $public, $existingApi) {
                    $this->assertSame($keyApi, $data['key']);
                    $this->assertSame($release, $data['release']);
                    $this->assertSame($active, $data['active']);
                    $this->assertSame($public, $data['public']);
                    $this->assertSame($existingApi['created_at'], $data['created_at']);
                    $this->assertNotEmpty($data['updated_at']);
                    $this->assertSame(['two', 'one'], $data['releases']);

                    return true;
                })
            );

        $result = $this->apiStorage->set($org, $env, $keyApi, $release, $active, $public);

        $this->assertSame([], $result);
    }

    public function testSetSameReleaseDoesNotAddReleaseHistory(): void
    {
        $org = 'isidoro';
        $env = 'test';
        $keyApi = 'ping';
        $release = 'release34';
        $active = true;
        $public = true;

        $existingApi = [
            'key' => $keyApi,
            'release' => 'release34',
            'active' => false,
            'created_at' => '2020-01-01',
            'updated_at' => '2020-01-01',
            'releases' => ['one'],
            'public' => false,
        ];

        $this->memory->expects($this->once())
            ->method('get')
            ->with("org-$org-env-$env-key-$keyApi")
            ->willReturn($existingApi);

        $this->memory->expects($this->once())
            ->method('set')
            ->with(
                "org-$org-env-$env-key-$keyApi",
                $this->callback(function (array $data) use ($existingApi) {
                    $this->assertSame('release34', $data['release']);
                    $this->assertSame($existingApi['created_at'], $data['created_at']);
                    // Same release: no release history is added
                    $this->assertSame([], $data['releases']);

                    return true;
                })
            );

        $result = $this->apiStorage->set($org, $env, $keyApi, $release, $active, $public);

        $this->assertSame([], $result);
    }

    public function testSetTrimsReleasesWhenExceedsMax(): void
    {
        $org = 'isidoro';
        $env = 'test';
        $keyApi = 'ping';
        $release = 'release_new';
        $active = true;
        $public = true;

        $oldReleases = ['v1', 'v2', 'v3', 'v4', 'v5', 'v6', 'v7', 'v8'];

        $existingApi = [
            'key' => $keyApi,
            'release' => 'release_old',
            'active' => false,
            'created_at' => '2020-01-01',
            'updated_at' => '2020-01-01',
            'releases' => $oldReleases,
            'public' => false,
        ];

        $this->memory->expects($this->once())
            ->method('get')
            ->with("org-$org-env-$env-key-$keyApi")
            ->willReturn($existingApi);

        // The current release ('release_old') will be added, making 9 total releases.
        // cleanReleases keeps only the last 7: ['v2','v3','v4','v5','v6','v7','v8','release_old'] -> last 7
        // So v1 and v2 should be removed.
        $this->memory->expects($this->once())
            ->method('set')
            ->with(
                "org-$org-env-$env-key-$keyApi",
                $this->callback(function (array $data) {
                    $this->assertCount(7, $data['releases']);

                    return true;
                })
            );

        $result = $this->apiStorage->set($org, $env, $keyApi, $release, $active, $public);

        // v1 and v2 should be returned as deleted
        $this->assertSame(['v1', 'v2'], $result);
    }

    public function testFindReturnsApi(): void
    {
        $org = 'isidoro';
        $env = 'test';
        $keyApi = 'ping';
        $expected = [
            'release' => 'release34',
            'active' => true,
            'created_at' => '2020-01-01',
        ];

        $this->memory->expects($this->once())
            ->method('get')
            ->with("org-$org-env-$env-key-$keyApi")
            ->willReturn($expected);

        $api = $this->apiStorage->find($org, $env, $keyApi);

        $this->assertSame($expected, $api);
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        $org = 'isidoro';
        $env = 'test';
        $keyApi = 'nonexistent';

        $this->memory->expects($this->once())
            ->method('get')
            ->with("org-$org-env-$env-key-$keyApi")
            ->willReturn(null);

        $api = $this->apiStorage->find($org, $env, $keyApi);

        $this->assertNull($api);
    }

    public function testFindAllFiltersByOrgAndEnv(): void
    {
        $org = 'isidoro';
        $env = 'test';

        $this->memory->expects($this->once())
            ->method('read')
            ->with()
            ->willReturn([
                "org-$org-env-otherenv-key-loquesea",
                "org-$org-env-$env-key-one",
                "org-$org-env-$env-key-two",
            ]);

        $apis = $this->apiStorage->findAll($org, $env);

        $this->assertSame(['one', 'two'], $apis);
    }

    public function testFindAllReturnsEmptyWhenNoMatch(): void
    {
        $org = 'isidoro';
        $env = 'test';

        $this->memory->expects($this->once())
            ->method('read')
            ->with()
            ->willReturn([
                "org-other-env-otherenv-key-loquesea",
            ]);

        $apis = $this->apiStorage->findAll($org, $env);

        $this->assertSame([], $apis);
    }
}