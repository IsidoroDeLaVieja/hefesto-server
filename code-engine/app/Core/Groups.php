<?php

declare(strict_types=1);

namespace App\Core;

class Groups
{
    public const NORMAL_FLOW = 'NORMAL_FLOW';
    public const ERROR_FLOW = 'ERROR_FLOW';
    public const QUEUE_FLOW = 'QUEUE_FLOW';
    public const AFTER_FLOW = 'AFTER_FLOW';

    /** @var string[] */
    private array $groups = [];

    public function isEnabled(string $key): bool
    {
        return in_array(strtoupper($key), $this->groups, true);
    }

    public function isAnyKeyEnabled(array $keys): bool
    {
        $upperKeys = array_map(strtoupper(...), $keys);

        return array_intersect($upperKeys, $this->groups) !== [];
    }

    public function enable(string $key): void
    {
        $group = strtoupper($key);

        if ($group === self::QUEUE_FLOW || $group === self::ERROR_FLOW) {
            $this->disableAll();
        }

        $this->groups[] = $group;
        $this->groups = array_unique($this->groups);
    }

    public function disable(string $key): void
    {
        $upperKey = strtoupper($key);

        if ($this->isEnabled($key)) {
            $foundKey = array_search($upperKey, $this->groups, true);
            unset($this->groups[$foundKey]);
        }
    }

    public function disableAll(): void
    {
        $this->groups = [];
    }

    public function read(): array
    {
        return $this->groups;
    }
}