<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Alias;
use Tests\Fixture\StateFixture;

class AliasTest extends TestCase
{
    // ====================================================================
    // message.*
    // ====================================================================

    public function testFindMessagePath(): void
    {
        $path = '/potato/89';

        $alias = $this->createAlias(['path' => $path]);

        $this->assertSame($path, $alias->find('$.message.path'));
    }

    public function testFindMessageStatus(): void
    {
        $status = 401;

        $alias = $this->createAlias(['status' => $status]);

        $this->assertSame($status, $alias->find('$.message.status'));
    }

    public function testFindMessageHeaders(): void
    {
        $headers = ['X-TEST' => 'testing', 'CONTENT-LENGTH' => '0'];

        $alias = $this->createAlias(['headers' => $headers]);

        $this->assertSame($headers, $alias->find('$.message.headers'));
    }

    public function testFindMessageQueryParam(): void
    {
        $key = 'user';
        $value = '56';
        $queryParams = [$key => $value];

        $alias = $this->createAlias(['queryParams' => $queryParams]);

        $this->assertSame($value, $alias->find('$.message.queryParam.' . $key));
    }

    // ====================================================================
    // id
    // ====================================================================

    public function testFindId(): void
    {
        $alias = $this->createAlias(['id' => 'custom-id-123']);

        $this->assertSame('custom-id-123', $alias->find('$.id'));
    }

    // ====================================================================
    // memory.*
    // ====================================================================

    public function testFindMemorySimpleValue(): void
    {
        $alias = $this->createAliasWithMemory('user', '56');

        $this->assertSame('56', $alias->find('$.memory.user'));
    }

    public function testFindMemoryNestedArray(): void
    {
        $value = [
            'name' => 'yoli',
            'phones' => [
                'main' => 123456,
                'others' => [654321, 123],
            ],
        ];

        $alias = $this->createAliasWithMemory('user', $value);

        $this->assertSame(123456, $alias->find('$.memory.user.phones.main'));
        $this->assertSame([654321, 123], $alias->find('$.memory.user.phones.others'));
        $this->assertSame(123, $alias->find('$.memory.user.phones.others.1'));
    }

    // ====================================================================
    // map.*
    // ====================================================================

    public function testFindMapRead(): void
    {
        $alias = $this->createAlias();

        $this->assertSame(
            ['name' => 'yolanda', 'age' => 32],
            $alias->find('$.map.map-fixture'),
        );
    }

    public function testFindMapGetValue(): void
    {
        $alias = $this->createAlias();

        $this->assertSame('yolanda', $alias->find('$.map.map-fixture.name'));
    }

    public function testFindMapNestedArray(): void
    {
        $alias = $this->createAlias();

        $this->assertSame(1989, $alias->find('$.map.map-recursive-fixture.age.number'));
        $this->assertSame('URSS', $alias->find('$.map.map-recursive-fixture.age.countries.0'));
        $this->assertSame(
            ['main' => 'Felipe'],
            $alias->find('$.map.map-recursive-fixture.age.president'),
        );
        $this->assertSame(
            'Felipe',
            $alias->find('$.map.map-recursive-fixture.age.president.main'),
        );
    }

    // ====================================================================
    // Edge cases: non-string keys
    // ====================================================================

    public function testFindNonStringKeyReturnsKeyAsIs(): void
    {
        $alias = $this->createAlias();

        $this->assertNull($alias->find(null));
        $this->assertSame(42, $alias->find(42));
        $this->assertSame(3.14, $alias->find(3.14));
        $this->assertFalse($alias->find(false));
        $this->assertSame([1, 2], $alias->find([1, 2]));
    }

    public function testFindEmptyStringKeyReturnsEmptyString(): void
    {
        $alias = $this->createAlias();

        $this->assertSame('', $alias->find(''));
    }

    public function testFindKeyWithoutDollarDotPrefixReturnsKeyAsIs(): void
    {
        $alias = $this->createAlias();

        $this->assertSame('$message.path', $alias->find('$message.path'));
        $this->assertSame('message.path', $alias->find('message.path'));
    }

    // ====================================================================
    // Helpers
    // ====================================================================

    private function createAlias(array $config = []): Alias
    {
        $config['codePath'] = __DIR__ . '/../../Fixture/';

        return new Alias(StateFixture::get($config));
    }

    private function createAliasWithMemory(string $key, mixed $value): Alias
    {
        $config = ['codePath' => __DIR__ . '/../../Fixture/'];
        $state = StateFixture::get($config);
        $state->memory()->set($key, $value);

        return new Alias($state);
    }
}