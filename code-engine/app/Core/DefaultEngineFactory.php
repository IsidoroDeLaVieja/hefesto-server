<?php

declare(strict_types=1);

namespace App\Core;

use SplDoublyLinkedList;

class DefaultEngineFactory implements EngineFactory
{
    public function __construct(
        private readonly DirectiveFactory $directiveFactory = new DefaultDirectiveFactory()
    ) {}

    public function make(State $state, SplDoublyLinkedList $directives, EngineDispatcher $dispatcher): Engine
    {
        return new Engine($state, $directives, $dispatcher, $this->directiveFactory);
    }
}