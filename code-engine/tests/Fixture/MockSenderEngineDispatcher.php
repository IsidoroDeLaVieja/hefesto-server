<?php

declare(strict_types=1);

namespace Tests\Fixture;
use App\Core\SenderEngineDispatcher;
use App\Core\Engine;

class MockSenderEngineDispatcher implements SenderEngineDispatcher 
{
    public function execute(Engine $engine,int $delay) : void
    {
        $engine->execute();
    }
}