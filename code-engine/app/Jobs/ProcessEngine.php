<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Core\Engine;

class ProcessEngine implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $engine;

    public function __construct(Engine $engine)
    {
        $this->engine = $engine;
    }

    public function handle() : void
    {
        $this->engine->state()->queue();
        $this->engine->execute();
        $this->engine->executeAfter();
    }
}
