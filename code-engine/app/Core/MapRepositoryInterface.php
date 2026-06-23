<?php

declare(strict_types=1);

namespace App\Core;

interface MapRepositoryInterface
{
    /**
     * @return array<string, mixed>
     */
    public function load(string $key): array;
}