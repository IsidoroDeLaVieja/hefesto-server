<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Core\VirtualHostStorage;

class DeleteVirtualHost extends Command
{
    protected $signature = 'delete:virtualhost {name}';

    public function handle(VirtualHostStorage $storage)
    {
        $storage->delete($this->argument('name'));
    }
}