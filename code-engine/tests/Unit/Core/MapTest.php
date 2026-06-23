<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Map;

class MapTest extends TestCase 
{
    public function testGetReturnsValueForExistingStringKey() : void
    {
        $map = new Map(['name' => 'yolanda', 'age' => 32]);
        $this->assertSame('yolanda', $map->get('name'));
    }

    public function testGetReturnsValueForExistingIntegerKey() : void
    {
        $map = new Map(['name' => 'yolanda', 'age' => 32]);
        $this->assertSame(32, $map->get('age'));
    }

    public function testGetReturnsNullForNonExistingKey() : void
    {
        $map = new Map(['name' => 'yolanda', 'age' => 32]);
        $this->assertNull($map->get('adsfdsf'));
    }

    public function testReadReturnsFullStorage() : void
    {
        $storage = ['name' => 'yolanda', 'age' => 32];
        $map = new Map($storage);
        $this->assertSame($storage, $map->read());
    }

    public function testEmptyStorageReturnsNullOnGet() : void
    {
        $map = new Map([]);
        $this->assertNull($map->get('anything'));
    }

    public function testEmptyStorageReturnsEmptyArrayOnRead() : void
    {
        $map = new Map([]);
        $this->assertSame([], $map->read());
    }

    public function testGetReturnsNullWhenKeyHasNullValue() : void
    {
        $map = new Map(['foo' => null]);
        $this->assertNull($map->get('foo'));
    }

    public function testKeyExistsWithNullValueDoesNotFallbackToMissingKey() : void
    {
        $map = new Map(['foo' => null]);
        $this->assertTrue(array_key_exists('foo', $map->read()));
        $this->assertNull($map->get('foo'));
    }

    public function testGetWithNumericKey() : void
    {
        $map = new Map([0 => 'zero', 1 => 'one']);
        $this->assertSame('zero', $map->get('0'));
        $this->assertSame('one', $map->get('1'));
    }
}