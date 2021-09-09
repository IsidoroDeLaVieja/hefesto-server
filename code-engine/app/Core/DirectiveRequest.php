<?php

declare(strict_types=1);

namespace App\Core;

class DirectiveRequest {

    public $id;
    public $name;
    public $config;
    public $groups;

    public function __construct(
        string $id,
        string $name, 
        array $config, 
        ?array $groups = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->config = $config;
        $this->groups = $groups;
    }
}