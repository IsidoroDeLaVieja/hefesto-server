<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\State;
use App\Core\Message;
use App\Core\Groups;
use App\Core\Memory;
use App\Core\Alias;
use App\Core\Map;
use App\Core\MapRepositoryInterface;
use App\Core\ExecutionTimeMemory;

class StateTest extends TestCase
{
    private const DEFAULT_CONFIG = [
        'organization' => 'my-org',
        'environment'  => 'prod',
        'keyApi'       => 'api-key-123',
        'localhost'    => 'http://localhost',
        'codePath'     => '/code/',
        'storagePath'  => '/storage/',
        'definitionPath' => '/defs/',
        'definitionVerb' => 'GET',
    ];

    // ====================================================================
    // Constructor
    // ====================================================================

    public function testConstructorSetsConfigInMemory(): void
    {
        $state = $this->createState();

        $this->assertSame('my-org', $state->memory()->get('hefesto-org'));
        $this->assertSame('prod', $state->memory()->get('hefesto-env'));
        $this->assertSame('api-key-123', $state->memory()->get('hefesto-api'));
        $this->assertSame('http://localhost', $state->memory()->get('hefesto-localhost'));
        $this->assertSame('/code/', $state->memory()->get('hefesto-pathcode'));
        $this->assertSame('/storage/', $state->memory()->get('hefesto-pathstorage'));
        $this->assertSame('/defs/', $state->memory()->get('hefesto-definitionpath'));
        $this->assertSame('GET', $state->memory()->get('hefesto-definitionverb'));
    }

    public function testConstructorGeneratesIdWhenNotProvided(): void
    {
        $state = $this->createState();
        $this->assertNotEmpty($state->id());
        $this->assertIsString($state->id());
    }

    public function testConstructorUsesCustomIdWhenProvided(): void
    {
        $config = self::DEFAULT_CONFIG;
        $config['id'] = 'custom-id-456';

        $state = $this->createState($config);
        $this->assertSame('custom-id-456', $state->id());
    }

    public function testConstructorStartsWithEmptyDebug(): void
    {
        $state = $this->createState();
        $this->assertSame([], $state->getDebug());
    }

    public function testConstructorStartsWithDirectiveDebugDisabled(): void
    {
        $state = $this->createState();
        $this->assertFalse($state->isDirectiveDebug());
    }

    public function testConstructorStartsWithNotQueued(): void
    {
        $state = $this->createState();
        $this->assertFalse($state->isQueued());
    }

    public function testConstructorLoadsMapsFromApiMemoryWhenPresent(): void
    {
        $apiMemory = new ExecutionTimeMemory();
        $apiMemory->set('api-maps', ['users' => new Map(['id' => 1])]);

        $config = self::DEFAULT_CONFIG;
        $config['apiMemory'] = $apiMemory;

        $state = $this->createState($config);
        $map = $state->map('users');

        $this->assertInstanceOf(Map::class, $map);
        $this->assertSame(1, $map->get('id'));
    }

    public function testConstructorHandlesNullApiMemory(): void
    {
        $config = self::DEFAULT_CONFIG;
        $config['apiMemory'] = null;

        $state = $this->createState($config);

        // Should not throw; map() should still work via repository
        $this->assertInstanceOf(State::class, $state);
    }

    // ====================================================================
    // id()
    // ====================================================================

    public function testIdReturnsString(): void
    {
        $state = $this->createState();
        $this->assertIsString($state->id());
    }

    // ====================================================================
    // message()
    // ====================================================================

    public function testMessageReturnsInjectedMessage(): void
    {
        $message = new Message('GET', '/test', [], '', [], [], 200);
        $state = $this->createState(self::DEFAULT_CONFIG, $message);

        $this->assertSame($message, $state->message());
    }

    // ====================================================================
    // groups()
    // ====================================================================

    public function testGroupsReturnsInjectedGroups(): void
    {
        $groups = new Groups();
        $groups->enable('TEST_FLOW');
        $state = $this->createState(self::DEFAULT_CONFIG, null, $groups);

        $this->assertTrue($state->groups()->isEnabled('TEST_FLOW'));
    }

    // ====================================================================
    // memory()
    // ====================================================================

    public function testMemoryReturnsInjectedMemory(): void
    {
        $memory = new ExecutionTimeMemory();
        $memory->set('custom-key', 'custom-value');
        $state = $this->createState(self::DEFAULT_CONFIG, null, null, $memory);

        $this->assertSame('custom-value', $state->memory()->get('custom-key'));
    }

    // ====================================================================
    // map()
    // ====================================================================

    public function testMapLoadsFromRepositoryWhenNotCached(): void
    {
        $storage = ['name' => 'Alice', 'age' => 30];
        $repository = $this->createMock(MapRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('load')
            ->with('users')
            ->willReturn($storage);

        $state = $this->createState(self::DEFAULT_CONFIG, null, null, null, $repository);

        $map = $state->map('users');
        $this->assertInstanceOf(Map::class, $map);
        $this->assertSame('Alice', $map->get('name'));
    }

    public function testMapReturnsCachedMapOnSubsequentCalls(): void
    {
        $repository = $this->createMock(MapRepositoryInterface::class);
        $repository->expects($this->once()) // only one load
            ->method('load')
            ->with('products')
            ->willReturn(['sku' => 'ABC']);

        $state = $this->createState(self::DEFAULT_CONFIG, null, null, null, $repository);

        $firstCall = $state->map('products');
        $secondCall = $state->map('products');

        $this->assertSame($firstCall, $secondCall);
        $this->assertSame('ABC', $secondCall->get('sku'));
    }

    public function testMapStoresInApiMemoryWhenAvailable(): void
    {
        $apiMemory = new ExecutionTimeMemory();
        $config = self::DEFAULT_CONFIG;
        $config['apiMemory'] = $apiMemory;

        $repository = $this->createMock(MapRepositoryInterface::class);
        $repository->method('load')->willReturn(['key' => 'val']);

        $state = $this->createState($config, null, null, null, $repository);

        $state->map('test-map');

        $cached = $apiMemory->get('api-maps');
        $this->assertIsArray($cached);
        $this->assertArrayHasKey('test-map', $cached);
        $this->assertInstanceOf(Map::class, $cached['test-map']);
    }

    public function testMapDoesNotThrowWhenApiMemoryIsNull(): void
    {
        $config = self::DEFAULT_CONFIG;
        $config['apiMemory'] = null;

        $repository = $this->createMock(MapRepositoryInterface::class);
        $repository->method('load')->willReturn(['x' => 'y']);

        $state = $this->createState($config, null, null, null, $repository);

        $map = $state->map('some-key');
        $this->assertInstanceOf(Map::class, $map);
        $this->assertSame('y', $map->get('x'));
    }

    // ====================================================================
    // alias()
    // ====================================================================

    public function testAliasReturnsKeyWhenNoAliasSet(): void
    {
        $state = $this->createState();

        $this->assertSame('plain-key', $state->alias('plain-key'));
    }

    public function testAliasDelegatesToAliasObjectWhenSet(): void
    {
        $state = $this->createState();
        $aliasMock = $this->createMock(Alias::class);
        $aliasMock->expects($this->once())
            ->method('find')
            ->with('$.message.path')
            ->willReturn('/resolved/path');

        $state->setAlias($aliasMock);

        $this->assertSame('/resolved/path', $state->alias('$.message.path'));
    }

    // ====================================================================
    // Queue
    // ====================================================================

    public function testQueueSetsIsQueuedToTrue(): void
    {
        $state = $this->createState();
        $this->assertFalse($state->isQueued());

        $state->queue();
        $this->assertTrue($state->isQueued());
    }

    // ====================================================================
    // Debug
    // ====================================================================

    public function testResetDebugClearsLog(): void
    {
        $state = $this->createState();
        $state->addDebug(['msg' => 'first']);
        $state->addDebug(['msg' => 'second']);
        $this->assertCount(2, $state->getDebug());

        $state->resetDebug();
        $this->assertSame([], $state->getDebug());
    }

    public function testAddDebugAppendsEntries(): void
    {
        $state = $this->createState();
        $state->addDebug(['step' => 1]);
        $state->addDebug(['step' => 2]);

        $this->assertCount(2, $state->getDebug());
        $this->assertSame(['step' => 1], $state->getDebug()[0]);
        $this->assertSame(['step' => 2], $state->getDebug()[1]);
    }

    public function testIsDirectiveDebugInitialState(): void
    {
        $state = $this->createState();
        $this->assertFalse($state->isDirectiveDebug());
    }

    public function testEnableDirectiveDebugSetsFlag(): void
    {
        $state = $this->createState();

        $state->enableDirectiveDebug();
        $this->assertTrue($state->isDirectiveDebug());
    }

    // ====================================================================
    // Factory helper
    // ====================================================================

    private function createState(
        array $config = self::DEFAULT_CONFIG,
        ?Message $message = null,
        ?Groups $groups = null,
        ?Memory $memory = null,
        ?MapRepositoryInterface $mapRepository = null,
    ): State {
        $message ??= new Message('POST', '/test', [], '{}', [], [], 200);
        $groups ??= new Groups();
        $memory ??= new ExecutionTimeMemory();
        $mapRepository ??= $this->createMock(MapRepositoryInterface::class);

        $defaults = self::DEFAULT_CONFIG;
        $config = array_merge($defaults, $config);

        return new State($message, $config, $groups, $memory, $mapRepository);
    }
}