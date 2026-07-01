<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Core\VirtualHostStorage;

class GetJob extends Command
{
    protected $signature = 'get:job {virtualhost} {id}';

    public function handle(VirtualHostStorage $storage): int
    {
        $virtualhost = $this->argument('virtualhost');
        $jobId = $this->argument('id');

        $public = $storage->getPublic($virtualhost);

        if ($public === null) {
            $this->error("Virtual host not found: {$virtualhost}");

            return 1;
        }

        $org = $public['ORG'];
        $env = $public['ENV'];

        $dbName = preg_replace("/[^a-zA-Z0-9]+/", "", $org . $env);
        $key = $dbName . ':jobs:' . $jobId;

        $value = \Illuminate\Support\Facades\Redis::get($key);

        if ($value === null || $value === '') {
            $this->error("Job Not Found: {$jobId}");

            return 1;
        }

        $this->line($value);

        return 0;
    }
}