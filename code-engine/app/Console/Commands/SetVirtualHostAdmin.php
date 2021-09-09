<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Core\VirtualHostStorage;

class SetVirtualHostAdmin extends Command
{
    protected $signature = 'set:virtualhost:admin {name}';

    public function handle(VirtualHostStorage $storage)
    {
        $storage->setAdmin($this->argument('name'));
    }
}