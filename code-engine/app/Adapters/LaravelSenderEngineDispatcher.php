<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Core\Engine;
use App\Core\Queue;
use App\Core\SenderEngineDispatcher;

readonly class LaravelSenderEngineDispatcher implements SenderEngineDispatcher
{
    public function __construct(
        private readonly Queue $queue,
    ) {}

    public function execute(Engine $engine, int $delay): void
    {
        $this->queue->push($engine, $delay);
    }
}
