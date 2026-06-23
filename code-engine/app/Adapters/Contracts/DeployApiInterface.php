<?php

declare(strict_types=1);

namespace App\Adapters\Contracts;

interface DeployApiInterface
{
    public function execute(string $sourceFolder, string $targetFolder, string $org, string $env, string $release): string;
}