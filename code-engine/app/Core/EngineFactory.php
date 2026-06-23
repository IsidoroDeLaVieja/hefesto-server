<?php

declare(strict_types=1);

namespace App\Core;
use SplDoublyLinkedList;

interface EngineFactory {

    public function make(State $state, SplDoublyLinkedList $directives, EngineDispatcher $dispatcher): Engine;
}