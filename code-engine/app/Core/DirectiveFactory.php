<?php

declare(strict_types=1);

namespace App\Core;

interface DirectiveFactory {

    public function make(string $name): Directive;
}