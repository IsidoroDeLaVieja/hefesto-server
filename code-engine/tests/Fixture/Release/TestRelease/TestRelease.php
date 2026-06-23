<?php

declare(strict_types=1);

namespace Tests\Fixture\Release\TestRelease;

use App\Core\Api;
use SplDoublyLinkedList;

class TestRelease implements Api
{
    public function actions(): array
    {
        return [
            ['GET', '/test-path'],
            ['GET', '/test-with-param/{id}'],
        ];
    }

    public function getDirectives(string $verb, string $definitionPath): SplDoublyLinkedList
    {
        return new SplDoublyLinkedList();
    }
}