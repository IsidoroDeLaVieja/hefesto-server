<?php

declare(strict_types=1);

namespace App\Core;

class Groups {

    private $groups = [];

    public const NORMAL_FLOW = 'NORMAL_FLOW';
    public const ERROR_FLOW = 'ERROR_FLOW';
    public const QUEUE_FLOW = 'QUEUE_FLOW';
    public const AFTER_FLOW = 'AFTER_FLOW';
    
    public function isEnabled(string $key) : bool
    {
        return in_array(strtoupper($key),$this->groups);
    }

    public function isAnyKeyEnabled(array $keys) : bool
    {
        array_walk($keys, function(&$value){
            $value = strtoupper($value);
        });
        return count(array_intersect($keys,$this->groups)) > 0;
    }

    public function enable(string $key) : void
    {
        $group = strtoupper($key);
        if ($group === self::QUEUE_FLOW || $group === self::ERROR_FLOW) {
            $this->disableAll();
        }
        $this->groups[] = $group;
        $this->groups = array_unique($this->groups);
    }

    public function disable(string $key) : void
    {
        if ($this->isEnabled($key)) {
            $key = array_search(strtoupper($key),$this->groups);
            unset($this->groups[$key]);
        }
    }

    public function disableAll() : void 
    {
        $this->groups = [];
    }

    public function read() : array 
    {
        return $this->groups;
    }
}