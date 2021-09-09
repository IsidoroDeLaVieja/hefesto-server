<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Map;

class MapTest extends TestCase 
{
    private $map;
    
    protected function setUp() : void
    {
        $this->map = new Map(__DIR__.'/../../Fixture/Maps/map-fixture.json');
    }

    public function testGet() : void
    {
        $this->assertNull($this->map->get('adsfdsf'));
        $this->assertSame('yolanda',$this->map->get('name'));
        $this->assertSame(32,$this->map->get('age'));
    }

    public function testRead() : void
    {
        $this->assertSame(['name' => 'yolanda', 'age' => 32],$this->map->read());
    }
}