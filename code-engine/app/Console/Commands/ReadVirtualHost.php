<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Core\VirtualHostStorage;

class ReadVirtualHost extends Command
{
    protected $signature = 'read:virtualhost';

    public function handle(VirtualHostStorage $storage)
    {        
        $this->info(
            implode(' ',$storage->read())
        );
    }
}