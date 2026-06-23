<?php

declare(strict_types=1);

namespace App\Core;

class DefaultDirectiveFactory implements DirectiveFactory {

    public function make(string $name): Directive
    {
        return new $name();
    }
}