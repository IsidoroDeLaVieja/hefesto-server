<?php

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

use App\Core\ApiStorage;
use App\Core\Memory;
use DateTime;

class ApiStorageTest extends TestCase
{
    private $memory;
    private $apiStorage;

    protected function setUp() : void
    {
        $this->memory = $this->getMockBuilder(Memory::class)
                ->onlyMethods([
                    'get',
                    'set',
                    'read'
                ])->getMock();

        $this->apiStorage = new ApiStorage($this->memory);
    }

    public function testSet() : void
    {
        $org = 'isidoro';
        $env = 'test';
        $keyApi = 'ping';
        $release = 'release34';
        $active = true;
        $public = true;

        $this->memory->expects($this->once())
            ->method('set')
            ->with(
                "org-$org-env-$env-key-$keyApi",
                [
                    'key' => $keyApi,
                    'release' => $release,
                    'active' => $active,
                    'created_at' => date(DateTime::ISO8601),
                    'updated_at' => date(DateTime::ISO8601),
                    'releases' => [],
                    'public' => $public
                ]
        );

        $this->apiStorage->set($org,$env,$keyApi,$release,$active,$public);
    }

    public function testSetOldVersion() : void
    {
        $org = 'isidoro';
        $env = 'test';
        $keyApi = 'ping';
        $release = 'release34';
        $active = true;
        $public = true;

        $this->memory->expects($this->once())
            ->method('get')
            ->with("org-$org-env-$env-key-$keyApi")
            ->willReturn([
                'key' => $keyApi,
                'release' => 'one',
                'active' => false,
                'created_at' => '2020-01-01',
                'updated_at' => '2020-01-01',
                'releases' => ['two'],
                'public' => false
            ]);

        $this->memory->expects($this->once())
            ->method('set')
            ->with(
                "org-$org-env-$env-key-$keyApi",
                [
                    'key' => $keyApi,
                    'release' => $release,
                    'active' => $active,
                    'created_at' => '2020-01-01',
                    'updated_at' => date(DateTime::ISO8601),
                    'releases' => ['two','one'],
                    'public' => $public
                ]
        );

        $this->apiStorage->set($org,$env,$keyApi,$release,$active,$public);
    }

    public function testFind() : void
    {
        $org = 'isidoro';
        $env = 'test';
        $keyApi = 'ping';
        $expected = [
            'release' => 'release34',
            'active' => true,
            'created_at' => '2020-01-01'
        ];

        $this->memory->expects($this->once())
            ->method('get')
            ->with("org-$org-env-$env-key-$keyApi")
            ->willReturn($expected);

        $api = $this->apiStorage->find($org,$env,$keyApi);

        $this->assertSame($expected,$api);
    }

    public function testFindAll() : void
    {
        $org = 'isidoro';
        $env = 'test';

        $this->memory->expects($this->once())
            ->method('read')
            ->with()
            ->willReturn([
                "org-$org-env-otherenv-key-loquesea",
                "org-$org-env-$env-key-one",
                "org-$org-env-$env-key-two"
            ]);

        $apis = $this->apiStorage->findAll($org,$env);

        $this->assertSame(['one','two'],$apis);
    }
}