<?php

declare(strict_types=1);

namespace Tests\Integration\Adapters;

use Tests\TestCase;
use App\Adapters\CachedFileMemory;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class CachedFileMemoryTest extends TestCase
{
    private CachedFileMemory $memory;

    protected function setUp(): void
    {
        parent::setUp();

        // Reemplazar la conexión Redis real por nuestra implementación en memoria
        $this->swapRedisConnection();

        // Fake de disco en memoria (Laravel Storage Fake)
        Storage::fake('local');
    }

    private function swapRedisConnection(): void
    {
        $manager = app('redis');
        $reflection = new \ReflectionClass($manager);
        $connectionsProp = $reflection->getProperty('connections');
        $connectionsProp->setAccessible(true);
        $connectionsProp->setValue($manager, ['default' => new InMemoryRedisConnection()]);
    }

    protected function tearDown(): void
    {
        Redis::connection()->flush();
        parent::tearDown();
    }

    // ----------------------------------------------------------------
    //  Tests
    // ----------------------------------------------------------------

    public function testGetNonExistentKeyReturnsNull(): void
    {
        $this->memory = new CachedFileMemory('test-ns');
        $this->assertNull($this->memory->get('NONEXISTENT'));
    }

    public function testSetAndGetStringValue(): void
    {
        $this->memory = new CachedFileMemory('test-ns');
        $this->memory->set('greeting', 'Hello World');

        $this->assertSame('Hello World', $this->memory->get('greeting'));
    }

    public function testSetAndGetArrayValue(): void
    {
        $this->memory = new CachedFileMemory('test-ns');
        $data = ['foo' => 1, 'bar' => 2];
        $this->memory->set('arr', $data);

        $this->assertSame($data, $this->memory->get('arr'));
    }

    public function testSetAndGetNullValue(): void
    {
        $this->memory = new CachedFileMemory('test-ns');
        $this->memory->set('null-key', null);

        $this->assertNull($this->memory->get('null-key'));
    }

    public function testGetReturnsCachedValueFromRedisWithoutReadingDisk(): void
    {
        $this->memory = new CachedFileMemory('test-ns');

        // Escribir directamente en Redis (simula que ya hay caché previa)
        $redisKey = 'test-ns/cached-key';
        Redis::set($redisKey, serialize('cached-value'));

        // Al hacer get, debería devolver el valor de Redis sin tocar disco
        $this->assertSame('cached-value', $this->memory->get('cached-key'));
    }

    public function testSetPersistsToBothDiskAndRedis(): void
    {
        $this->memory = new CachedFileMemory('test-ns');

        $this->memory->set('greeting', 'Hello World');

        // Comprobar que está en Redis
        $redisKey = 'test-ns/greeting';
        $this->assertSame(serialize('Hello World'), Redis::get($redisKey));

        // Comprobar que está en disco (Storage)
        $this->assertTrue(Storage::exists($redisKey));
        $this->assertSame(serialize('Hello World'), Storage::get($redisKey));
    }

    public function testReadReturnsListOfFilesWhenDirectoryExists(): void
    {
        // Crear archivos en disco para simular directorio
        Storage::put('test-ns/file-a', serialize('a'));
        Storage::put('test-ns/file-b', serialize('b'));
        Storage::put('test-ns/file-c', serialize('c'));

        $this->memory = new CachedFileMemory('test-ns');
        $files = $this->memory->read();

        $this->assertCount(3, $files);
        $this->assertContains('test-ns/file-a', $files);
        $this->assertContains('test-ns/file-b', $files);
        $this->assertContains('test-ns/file-c', $files);
    }

    public function testReadReturnsEmptyArrayWhenDirectoryDoesNotExist(): void
    {
        $this->memory = new CachedFileMemory('nonexistent-ns');
        $this->assertSame([], $this->memory->read());
    }

    public function testDifferentNamespacesAreIsolated(): void
    {
        $memoryA = new CachedFileMemory('ns-a');
        $memoryB = new CachedFileMemory('ns-b');

        $memoryA->set('key', 'value-a');
        $memoryB->set('key', 'value-b');

        $this->assertSame('value-a', $memoryA->get('key'));
        $this->assertSame('value-b', $memoryB->get('key'));
    }

    public function testSetOverwritesExistingValue(): void
    {
        $this->memory = new CachedFileMemory('test-ns');

        $this->memory->set('key', 'first');
        $this->assertSame('first', $this->memory->get('key'));

        $this->memory->set('key', 'second');
        $this->assertSame('second', $this->memory->get('key'));
    }

    public function testGetFallsBackToDiskWhenRedisIsEmpty(): void
    {
        $this->memory = new CachedFileMemory('test-ns');

        // Escribir solo en disco (simula que Redis perdió la caché)
        $redisKey = 'test-ns/disk-key';
        Storage::put($redisKey, serialize('disk-value'));

        // Al hacer get debe leer de disco y luego cachear en Redis
        $this->assertSame('disk-value', $this->memory->get('disk-key'));

        // Verificar que ahora también está en Redis
        $this->assertSame(serialize('disk-value'), Redis::get($redisKey));
    }

    public function testGetReturnsNullWhenValueIsNotOnDiskOrRedis(): void
    {
        $this->memory = new CachedFileMemory('test-ns');
        // Asegurar que la clave no existe en ningún lado
        $redisKey = 'test-ns/missing';

        $this->assertFalse(Storage::exists($redisKey));

        // Storage::get() fallará (Throwable) → se cachea serialize(null) en Redis
        $this->assertNull($this->memory->get('missing'));

        // Y el resultado null se cachea en Redis para evitar futuros accesos a disco
        $this->assertNull(Redis::get($redisKey));
    }
}