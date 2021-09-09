<?php

declare(strict_types=1);

namespace App\Adapters;
use App\Jobs\ProcessEngine;
use App\Core\Engine;
use App\Core\SenderEngineDispatcher;

class LaravelSenderEngineDispatcher implements SenderEngineDispatcher{

    public function execute(Engine $engine, int $delay) : void
    {
        ProcessEngine::dispatch($engine)->delay(now()->addSeconds($delay));
    }
}