<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Engine;
use App\Core\EngineDispatcher;
use App\Core\EngineFactory;
use App\Core\SenderEngineDispatcher;
use App\Core\State;
use SplDoublyLinkedList;

class EngineDispatcherTest extends TestCase
{
    public function testShouldFilterDirectivesAndDispatch(): void
    {
        $directive1 = (object)['name' => 'DummyDirective', 'groups' => null];
        $directive2 = (object)['name' => 'DummyDirective', 'groups' => null];
        $directive3 = (object)['name' => 'DummyDirective', 'groups' => null];
        $directive4 = (object)['name' => 'DummyDirective', 'groups' => null];

        $oldDirectives = new SplDoublyLinkedList();
        $oldDirectives->push($directive1);
        $oldDirectives->push($directive2);
        $oldDirectives->push($directive3);
        $oldDirectives->push($directive4);

        $state = $this->createMock(State::class);

        $engine = $this->createMock(Engine::class);
        $engine->method('directives')->willReturn($oldDirectives);
        $engine->method('state')->willReturn($state);

        $expectedFilteredDirectives = new SplDoublyLinkedList();
        $expectedFilteredDirectives->push($directive3);  // order 3 > oldOrder=2
        $expectedFilteredDirectives->push($directive4);  // order 4 > oldOrder=2

        $engineToDispatch = $this->createMock(Engine::class);

        $engineFactory = $this->createMock(EngineFactory::class);
        $engineFactory
            ->expects($this->once())
            ->method('make')
            ->with(
                $this->identicalTo($state),
                $this->callback(function (SplDoublyLinkedList $directives) use ($expectedFilteredDirectives) {
                    $directives->rewind();
                    $expectedFilteredDirectives->rewind();

                    while ($directives->valid() && $expectedFilteredDirectives->valid()) {
                        if ($directives->current() !== $expectedFilteredDirectives->current()) {
                            return false;
                        }
                        $directives->next();
                        $expectedFilteredDirectives->next();
                    }

                    return !$directives->valid() && !$expectedFilteredDirectives->valid();
                }),
                $this->isInstanceOf(EngineDispatcher::class)
            )
            ->willReturn($engineToDispatch);

        $senderDispatcher = $this->createMock(SenderEngineDispatcher::class);
        $senderDispatcher
            ->expects($this->once())
            ->method('execute')
            ->with($this->identicalTo($engineToDispatch), 5);

        $engineDispatcher = new EngineDispatcher($senderDispatcher, $engineFactory);
        $engineDispatcher->send($engine, 2, 5);
    }

    public function testShouldNotFilterWhenOldOrderIsZero(): void
    {
        $directive1 = (object)['name' => 'DummyDirective', 'groups' => null];
        $directive2 = (object)['name' => 'DummyDirective', 'groups' => null];

        $oldDirectives = new SplDoublyLinkedList();
        $oldDirectives->push($directive1);
        $oldDirectives->push($directive2);

        $state = $this->createMock(State::class);

        $engine = $this->createMock(Engine::class);
        $engine->method('directives')->willReturn($oldDirectives);
        $engine->method('state')->willReturn($state);

        $expectedAllDirectives = new SplDoublyLinkedList();
        $expectedAllDirectives->push($directive1); // order 1 > 0
        $expectedAllDirectives->push($directive2); // order 2 > 0

        $engineToDispatch = $this->createMock(Engine::class);

        $engineFactory = $this->createMock(EngineFactory::class);
        $engineFactory
            ->expects($this->once())
            ->method('make')
            ->with(
                $this->identicalTo($state),
                $this->callback(function (SplDoublyLinkedList $directives) use ($expectedAllDirectives) {
                    $directives->rewind();
                    $expectedAllDirectives->rewind();

                    while ($directives->valid() && $expectedAllDirectives->valid()) {
                        if ($directives->current() !== $expectedAllDirectives->current()) {
                            return false;
                        }
                        $directives->next();
                        $expectedAllDirectives->next();
                    }

                    return !$directives->valid() && !$expectedAllDirectives->valid();
                }),
                $this->isInstanceOf(EngineDispatcher::class)
            )
            ->willReturn($engineToDispatch);

        $senderDispatcher = $this->createMock(SenderEngineDispatcher::class);
        $senderDispatcher
            ->expects($this->once())
            ->method('execute')
            ->with($this->identicalTo($engineToDispatch), 0);

        $engineDispatcher = new EngineDispatcher($senderDispatcher, $engineFactory);
        $engineDispatcher->send($engine, 0, 0);
    }

    public function testShouldFilterAllWhenOldOrderExceedsCount(): void
    {
        $directive1 = (object)['name' => 'DummyDirective', 'groups' => null];
        $directive2 = (object)['name' => 'DummyDirective', 'groups' => null];

        $oldDirectives = new SplDoublyLinkedList();
        $oldDirectives->push($directive1);
        $oldDirectives->push($directive2);

        $state = $this->createMock(State::class);

        $engine = $this->createMock(Engine::class);
        $engine->method('directives')->willReturn($oldDirectives);
        $engine->method('state')->willReturn($state);

        $expectedEmptyDirectives = new SplDoublyLinkedList();

        $engineToDispatch = $this->createMock(Engine::class);

        $engineFactory = $this->createMock(EngineFactory::class);
        $engineFactory
            ->expects($this->once())
            ->method('make')
            ->with(
                $this->identicalTo($state),
                $this->callback(function (SplDoublyLinkedList $directives) use ($expectedEmptyDirectives) {
                    return $directives->count() === 0 && $expectedEmptyDirectives->count() === 0;
                }),
                $this->isInstanceOf(EngineDispatcher::class)
            )
            ->willReturn($engineToDispatch);

        $senderDispatcher = $this->createMock(SenderEngineDispatcher::class);
        $senderDispatcher
            ->expects($this->once())
            ->method('execute')
            ->with($this->identicalTo($engineToDispatch), 10);

        $engineDispatcher = new EngineDispatcher($senderDispatcher, $engineFactory);
        $engineDispatcher->send($engine, 5, 10);
    }
}