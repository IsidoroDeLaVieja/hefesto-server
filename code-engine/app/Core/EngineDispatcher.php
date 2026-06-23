<?php

declare(strict_types=1);

namespace App\Core;
use SplDoublyLinkedList;

class EngineDispatcher {

    private $dispatcher;
    private $engineFactory;

    public function __construct(SenderEngineDispatcher $dispatcher, EngineFactory $engineFactory) 
    {
        $this->dispatcher = $dispatcher;
        $this->engineFactory = $engineFactory;
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

        $engineToDispatch = $this->engineFactory->make($state, $newDirectives, $this);
        $this->dispatcher->execute($engineToDispatch, $delay);
    }
}