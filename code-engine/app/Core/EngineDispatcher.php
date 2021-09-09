<?php

declare(strict_types=1);

namespace App\Core;
use SplDoublyLinkedList;

class EngineDispatcher {

    private $dispatcher;

    public function __construct(SenderEngineDispatcher $dispatcher) 
    {
        $this->dispatcher = $dispatcher;
    }

    public function send(Engine $engine, int $oldOrder, int $delay) : void 
    {
        $oldDirectives = $engine->directives();
        $oldDirectives->rewind();
        $state = $engine->state();

        $newDirectives = new SplDoublyLinkedList();
        $newOrder = 1;
        while( $oldDirectives->valid() ) {
            if ( $newOrder > $oldOrder ) {
                $newDirectives->push(
                    $oldDirectives->current()
                );
            }
            $oldDirectives->next();
            $newOrder++;
        }

        $engineToDispatch = new Engine($state,$newDirectives,$this);
        $this->dispatcher->execute($engineToDispatch,$delay);
    }
}