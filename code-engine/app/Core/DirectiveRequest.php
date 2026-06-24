<?php

declare(strict_types=1);

namespace App\Core;

class DirectiveRequest
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $config,
        public readonly ?array $groups = null,
    ) {}
}