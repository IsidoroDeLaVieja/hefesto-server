<?php

declare(strict_types=1);

namespace App\Adapters\Contracts;

interface DeployDirectivesInterface
{
    public function execute(string $sourceFolder, string $targetFolder, string $release): void;
}