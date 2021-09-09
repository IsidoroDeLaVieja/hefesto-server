<?php

declare(strict_types=1);

namespace App\Core;

interface Memory {

    public function get(string $key);

    public function set(string $key,$value) : void;

    public function read() : array;
}