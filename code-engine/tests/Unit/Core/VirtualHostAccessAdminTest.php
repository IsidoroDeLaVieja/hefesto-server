<?php

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\VirtualHostStorage;
use App\Core\ExecutionTimeMemory;
use App\Core\VirtualHostAccessAdmin;

class VirtualHostAccessAdminTest extends TestCase
{
    private $virtualHostStorage;
    private $virtualHostAccessAdmin;

    protected function setUp() : void
    {
        $this->virtualHostStorage = new VirtualHostStorage(
            new ExecutionTimeMemory()
        );
        $this->virtualHostAccessAdmin = new VirtualHostAccessAdmin(
            $this->virtualHostStorage
        );
    }

    public function testGetInfo() : void
    {
        $hostAdmin = 'admin.delavieja.com';
        $host = 'public.delavieja.com';
        $key = '333';
        $env = 'pro';
        $path = '';

        $this->virtualHostStorage->setAdmin($hostAdmin);
        $this->virtualHostStorage->setPublic($host,$key,$env,$path);

        $info = $this->virtualHostAccessAdmin->get($hostAdmin,$host,'111',false);

        $this->assertSame( 
            ['ORG' => $host,'TYPE' => 'ADMIN','ENV' => $env ,'PATH' => $path],
            $info
        );
    }

    public function testExceptionIfAdminDoesNotExist() : void 
    {
        $hostAdmin = 'admin.delavieja.com';
        $host = 'public.delavieja.com';
        $key = '333';
        $env = 'pro';
        $path = '';

        $this->virtualHostStorage->setPublic($host,$key,$env,$path);

        $this->expectException(\Exception::class);

        $this->virtualHostAccessAdmin->get($hostAdmin,$host,$key,true);
    }

    public function testExceptionIfPublicDoesNotExist() : void 
    {
        $hostAdmin = 'admin.delavieja.com';
        $host = 'public.delavieja.com';
        $key = '333';

        $this->virtualHostStorage->setAdmin($hostAdmin);

        $this->expectException(\Exception::class);

        $this->virtualHostAccessAdmin->get($hostAdmin,$host,$key,true);
    }

    public function testExceptionIfKeyIsNotCorrect() : void 
    {
        $hostAdmin = 'admin.delavieja.com';
        $host = 'public.delavieja.com';
        $key = '333';
        $env = 'pro';
        $path = '';

        $this->virtualHostStorage->setAdmin($hostAdmin);
        $this->virtualHostStorage->setPublic($host,$key,$env,$path);

        $this->expectException(\Exception::class);

        $this->virtualHostAccessAdmin->get($hostAdmin,$host,'456',true);
    }
}