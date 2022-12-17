<?php

declare(strict_types=1);

namespace App\Adapters;
use App\Core\Queue;
use App\Core\Engine;
use App\Core\SenderEngineDispatcher;
use Throwable;

class LaravelSenderEngineDispatcher implements SenderEngineDispatcher
{
    private Queue $queue;

    public function __construct(Queue $queue)
    {
        $this->queue = $queue;
    }

    public function execute(Engine $engine, int $delay) : void
    {  
        $this->queue->push($engine,$delay);
    }
}