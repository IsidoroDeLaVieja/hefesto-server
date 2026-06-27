<?php

declare(strict_types=1);

namespace App\Core;

class Alias
{
    public function __construct(
        private readonly State $state
    ) {}

    public function find(mixed $key): mixed
    {
        if (!is_string($key) || !str_starts_with($key, '$.')) {
            return $key;
        }

        $keyParts = explode('.', $key);
        $stateMethod = $keyParts[1];

        if ($stateMethod === 'id') {
            return $this->state->id();
        }

        [$object, $action, $arguments, $keys] = $this->$stateMethod($keyParts);
        $value = $object->$action(...$arguments);

        return $keys ? $this->recursiveValue($value, $keys) : $value;
    }

    private function recursiveValue(array $collection, array $keys): mixed
    {
        foreach ($keys as $key) {
            $collection = $collection[$key];
        }

        return $collection;
    }

    private function message(array $keyParts): array
    {
        $action = 'get' . ucfirst($keyParts[2]);

        return [
            $this->state->message(),
            $action,
            array_slice($keyParts, 3, 4),
            false,
        ];
    }

    private function memory(array $keyParts): array
    {
        return [
            $this->state->memory(),
            'get',
            array_slice($keyParts, 2, 3),
            count($keyParts) > 3 ? array_slice($keyParts, 3) : false,
        ];
    }

    private function map(array $keyParts): array
    {
        $object = $this->state->map($keyParts[2]);

        if (!isset($keyParts[3])) {
            return [$object, 'read', [], false];
        }

        return [
            $object,
            'get',
            array_slice($keyParts, 3, 4),
            count($keyParts) > 4 ? array_slice($keyParts, 4) : false,
        ];
    }
}