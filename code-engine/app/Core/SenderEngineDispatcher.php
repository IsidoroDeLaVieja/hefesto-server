<?php 

declare(strict_types=1);

namespace App\Core;

interface SenderEngineDispatcher {

    public function execute(Engine $engine,int $delay) : void;
}