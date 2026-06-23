<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\VirtualHostStorage;
use App\Core\ExecutionTimeMemory;

class VirtualHostStorageTest extends TestCase
{
    private VirtualHostStorage $virtualHostStorage;

    protected function setUp(): void
    {
        $this->virtualHostStorage = new VirtualHostStorage(
            new ExecutionTimeMemory()
        );
    }

    public function testGetAdminReturnsNullForNonExistentHost(): void
    {
        $this->assertNull(
            $this->virtualHostStorage->getAdmin('nonexistent.com')
        );
    }

    public function testSetAndGetAdmin(): void
    {
        $host = 'delavieja.com';

        $this->virtualHostStorage->setAdmin($host);

        $this->assertSame(
            ['ORG' => $host, 'TYPE' => 'ADMIN'],
            $this->virtualHostStorage->getAdmin($host)
        );
    }

    public function testGetPublicReturnsNullForNonExistentHost(): void
    {
        $this->assertNull(
            $this->virtualHostStorage->getPublic('nonexistent.com')
        );
    }

    public function testSetAndGetPublic(): void
    {
        $host = 'delavieja.com';
        $env = 'dev';
        $path = '';
        $key = '333';

        $this->virtualHostStorage->setPublic($host, $key, $env, $path);

        $this->assertSame(
            [
                'ORG' => $host,
                'TYPE' => 'PUBLIC',
                'KEY' => $key,
                'ENV' => $env,
                'PATH' => $path,
            ],
            $this->virtualHostStorage->getPublic($host)
        );
    }

    public function testDeleteRemovesExistingHost(): void
    {
        $host = 'delavieja.com';

        $this->virtualHostStorage->setAdmin($host);
        $this->assertNotNull($this->virtualHostStorage->getAdmin($host));

        $this->virtualHostStorage->delete($host);

        $this->assertNull($this->virtualHostStorage->getAdmin($host));
    }

    public function testDeleteNonExistentHostDoesNotThrow(): void
    {
        $host = 'nonexistent.com';

        $this->virtualHostStorage->delete($host);

        $this->assertNull($this->virtualHostStorage->getAdmin($host));
    }

    public function testExceptionWhenSettingAdminOnExistingPublic(): void
    {
        $host = 'delavieja.com';

        $this->virtualHostStorage->setPublic($host, 'key', 'env', 'path');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("can't add host, it's public");

        $this->virtualHostStorage->setAdmin($host);
    }

    public function testExceptionWhenSettingPublicOnExistingAdmin(): void
    {
        $host = 'delavieja.com';

        $this->virtualHostStorage->setAdmin($host);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("can't add host, it's admin");

        $this->virtualHostStorage->setPublic($host, 'key', 'env', 'path');
    }

    public function testReadReturnsAllHostKeys(): void
    {
        $host1 = 'delavieja1.com';
        $host2 = 'delavieja2.com';

        $this->virtualHostStorage->setAdmin($host1);
        $this->virtualHostStorage->setPublic($host2, 'key', 'env', 'path');

        $this->assertSame(
            [$host1, $host2],
            $this->virtualHostStorage->read()
        );
    }

    public function testConstructorLoadsExistingDataFromMemory(): void
    {
        $memory = new ExecutionTimeMemory();
        $host = 'preloaded.com';

        $preloadedStorage = new VirtualHostStorage($memory);
        $preloadedStorage->setAdmin($host);

        $loadedStorage = new VirtualHostStorage($memory);

        $this->assertNotNull($loadedStorage->getAdmin($host));
        $this->assertSame(
            ['ORG' => $host, 'TYPE' => 'ADMIN'],
            $loadedStorage->getAdmin($host)
        );
    }
}