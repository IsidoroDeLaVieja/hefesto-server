<?php

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\VirtualHostStorage;
use App\Core\ExecutionTimeMemory;

class VirtualHostStorageTest extends TestCase
{
    private $virtualHostStorage;

    protected function setUp() : void
    {
        $this->virtualHostStorage = new VirtualHostStorage(
            new ExecutionTimeMemory()
        );
    }

    public function testAdmin() : void
    {
        $host = 'delavieja.com';
        
        $this->assertNull(
            $this->virtualHostStorage->getAdmin($host)
        );

        $this->virtualHostStorage->setAdmin($host);

        $this->assertSame( 
            ['ORG' => $host,'TYPE' => 'ADMIN'],
            $this->virtualHostStorage->getAdmin($host)
        );
    }

    public function testPublic() : void
    {
        $host = 'delavieja.com';
        $env = 'dev';
        $path = '';
        $key = '333';
        
        $this->assertNull(
            $this->virtualHostStorage->getPublic($host)
        );

        $this->virtualHostStorage->setPublic($host,$key,$env,$path);

        $this->assertSame( 
            [
                'ORG' => $host,
                'TYPE' => 'PUBLIC',
                'KEY' => $key,
                'ENV' => $env,
                'PATH' => $path
            ],
            $this->virtualHostStorage->getPublic($host)
        );
    }

    public function testDelete() : void 
    {
        $host = 'delavieja.com';
        
        $this->virtualHostStorage->setAdmin($host);
        $this->assertNotNull($this->virtualHostStorage->getAdmin($host));

        $this->virtualHostStorage->delete($host);
        $this->assertNull($this->virtualHostStorage->getAdmin($host));
    }

    public function testExceptionIfAdminIsPublic() : void 
    {
        $host = 'delavieja.com';
        $this->virtualHostStorage->setPublic($host,'key','env','path');

        $this->expectException(\Exception::class);

        $this->virtualHostStorage->setAdmin($host);
    }

    public function testExceptionIfPublicIsAdmin() : void 
    {
        $host = 'delavieja.com';
        $this->virtualHostStorage->setAdmin($host);

        $this->expectException(\Exception::class);

        $this->virtualHostStorage->setPublic($host,'key','env','path');
    }

    public function testRead() : void
    {
        $host1 = 'delavieja1.com';
        $host2 = 'delavieja2.com';

        $this->virtualHostStorage->setAdmin($host1);
        $this->virtualHostStorage->setPublic($host2,'key','env','path');

        $this->assertSame( 
            [$host1,$host2],
            $this->virtualHostStorage->read()
        );
    }
}