<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Core\VirtualHostStorage;

class SetVirtualHostPublic extends Command
{
    protected $signature = 'set:virtualhost:public {name} {env} {key} {path?}';

    public function handle(VirtualHostStorage $storage)
    {        
        $storage->setPublic(
            $this->argument('name'),
            $this->argument('key'),
            $this->argument('env'),
            $this->argument('path') ? $this->argument('path') : ''
        );
    }
}