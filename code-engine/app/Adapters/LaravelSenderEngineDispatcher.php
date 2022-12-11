<?php

declare(strict_types=1);

namespace App\Adapters;
use App\Core\Engine;
use App\Core\SenderEngineDispatcher;
use Throwable;

class LaravelSenderEngineDispatcher implements SenderEngineDispatcher{

    public function execute(Engine $engine, int $delay) : void
    {
        $queue = new Queue();
        $queue->push($engine,$delay);
    }
}