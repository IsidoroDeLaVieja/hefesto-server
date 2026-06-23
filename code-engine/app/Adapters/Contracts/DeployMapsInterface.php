<?php

declare(strict_types=1);

namespace App\Adapters\Contracts;

interface DeployMapsInterface
{
    public function execute(string $sourceFolder, string $targetFolder, string $env): void;
}