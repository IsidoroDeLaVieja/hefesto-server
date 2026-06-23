<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\ExecutionTimeMemory;

class MemoryTest extends TestCase
{
    private ExecutionTimeMemory $memory;

    protected function setUp(): void
    {
        $this->memory = new ExecutionTimeMemory();
    }

    public function testInitialStateIsEmpty(): void
    {
        $this->assertSame([], $this->memory->read());
    }

    public function testGetNonExistentKeyReturnsNull(): void
    {
        $this->assertNull($this->memory->get('NONEXISTENT'));
    }

    public function testSetAndGetStringValue(): void
    {
        $this->memory->set('GREETING', 'Hello World');
        $this->assertSame('Hello World', $this->memory->get('GREETING'));
    }

    public function testSetAndGetArrayValue(): void
    {
        $value = ['uno' => 1, 'dos' => 2];
        $this->memory->set('MYARRAY', $value);
        $this->assertSame($value, $this->memory->get('MYARRAY'));
    }

    public function testGetIsCaseInsensitive(): void
    {
        $this->memory->set('MYKEY', 'stored value');
        $this->assertSame('stored value', $this->memory->get('mykey'));
        $this->assertSame('stored value', $this->memory->get('MYKEY'));
        $this->assertSame('stored value', $this->memory->get('MyKey'));
    }

    public function testOverwriteExistingKey(): void
    {
        $this->memory->set('KEY', 'first');
        $this->memory->set('KEY', 'second');
        $this->assertSame('second', $this->memory->get('KEY'));
    }

    public function testMultipleKeysAreIndependent(): void
    {
        $this->memory->set('A', 'value-a');
        $this->memory->set('B', 'value-b');

        $this->assertSame('value-a', $this->memory->get('A'));
        $this->assertSame('value-b', $this->memory->get('B'));
    }

    public function testReadReturnsFullStorage(): void
    {
        $this->memory->set('FIRST', 'one');
        $this->memory->set('SECOND', 'two');

        $this->assertSame([
            'FIRST' => 'one',
            'SECOND' => 'two',
        ], $this->memory->read());
    }

    public function testCanStoreNullValue(): void
    {
        $this->memory->set('NULLKEY', null);
        $this->assertNull($this->memory->get('NULLKEY'));
    }
}