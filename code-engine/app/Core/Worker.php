<?php

declare(strict_types=1);

namespace App\Core;

class Worker
{
    private const MAX_JOBS = 10;
    private const DELAY = 10;

    private Queue $queue;
    private int $countJobs = 0;

    public function __construct(Queue $queue)
    {
        $this->queue = $queue;
    }

    public function loop() : void 
    {
        while(true) {
            
            $work = $this->work();
            if (!$work) {
                sleep(self::DELAY);
                continue;
            }
            $this->countJobs++;

            if ($this->countJobs >= self::MAX_JOBS) {
                exit;
            }

        }
    }

    private function work() : bool
    {
        $engine = $this->queue->next();
        if (!$engine) {
            return false;
        }
        $id = $engine->state()->id();
        $org = $engine->state()->memory()->get('hefesto-org');
        $env = $engine->state()->memory()->get('hefesto-env');
        $status = $this->processEngine($engine);

        if ($status >= 500) {
            $this->queue->fail($id);
            return true;
        }

        $this->queue->success(
            $id , 
            $org, 
            $env
        );
        return true;
    }

    private function processEngine(Engine $engine) : int 
    {
        $engine->state()->queue();
        $engine->execute();
        $engine->executeAfter();
        return $engine->state()->message()->getStatus();
    }

}