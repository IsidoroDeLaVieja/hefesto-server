<?php

declare(strict_types=1);

namespace App\Core;

use SplDoublyLinkedList;

class EngineDispatcher
{
    public function __construct(
        private readonly SenderEngineDispatcher $dispatcher,
        private readonly EngineFactory $engineFactory,
    ) {}

    public function send(Engine $engine, int $oldOrder, int $delay): void
    {
        $sourceDirectives = $engine->directives();
        $sourceDirectives->rewind();
        $state = $engine->state();

        $remainingDirectives = new SplDoublyLinkedList();
        $index = 1;

        while ($sourceDirectives->valid()) {
            if ($index > $oldOrder) {
                $remainingDirectives->push($sourceDirectives->current());
            }
            $sourceDirectives->next();
            $index++;
        }

        $engineToDispatch = $this->engineFactory->make($state, $remainingDirectives, $this);
        $this->dispatcher->execute($engineToDispatch, $delay);
    }
}