<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\ExecutionTimeMemory;

class MemoryTest extends TestCase 
{
    private $memory;
    
    protected function setUp() : void
    {
        $this->memory = new ExecutionTimeMemory();
    }

    public function testGetAndSetAndRead() : void
    {
        $this->assertSame([],$this->memory->read());
        $this->assertNull($this->memory->get('ISIKEY'));
        $this->memory->set('ISIKEY','ISIVALUE');
        $this->assertSame('ISIVALUE',$this->memory->get('isikey'));
        $this->assertSame(['ISIKEY'=>'ISIVALUE'],$this->memory->read());
    }

    public function testGetAndSetArray() : void
    {
        $this->assertSame([],$this->memory->read());
        $this->assertNull($this->memory->get('ISIKEYARRAY'));
        $this->memory->set('ISIKEYARRAY',['uno'=>1]);
        $this->assertSame(['uno'=>1],$this->memory->get('isikeyarray'));
        $this->assertSame(['ISIKEYARRAY'=>['uno'=>1]],$this->memory->read());
    }
}