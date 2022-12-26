<?php

declare(strict_types=1);

namespace App\Core;

interface Queue 
{
    public function push(Engine $engine, int $delay) : void;

    public function next() : ?Engine;

    public function fail(string $id) : void;

    public function success(string $id,string $org,string $env) : void;

    public function requeueFailed(string $org,string $env) : int;

    public function flush(string $org,string $env) : int;

    public function count(string $org,string $env) : array; 
}