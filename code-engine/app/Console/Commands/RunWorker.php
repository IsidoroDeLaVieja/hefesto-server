<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Core\Worker;

class RunWorker extends Command
{
    protected $signature = 'run:worker';

    public function handle(Worker $worker)
    {
        $worker->loop();
    }
}