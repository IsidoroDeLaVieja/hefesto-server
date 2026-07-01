<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Core\VirtualHostStorage;
use App\Core\Queue;

class CountJobs extends Command
{
    protected $signature = 'count:jobs {virtualhost}';

    public function handle(VirtualHostStorage $storage, Queue $queue): int
    {
        $virtualhost = $this->argument('virtualhost');

        $public = $storage->getPublic($virtualhost);

        if ($public === null) {
            $this->error("Virtual host not found: {$virtualhost}");

            return 1;
        }

        $org = $public['ORG'];
        $env = $public['ENV'];

        try {
            $count = $queue->count($org, $env);
        } catch (\Throwable $e) {
            $this->error("Error counting jobs: " . $e->getMessage());

            return 1;
        }

        $this->line(json_encode($count, JSON_PRETTY_PRINT));

        return 0;
    }
}