<?php

declare(strict_types=1);

namespace App\Core;
use Throwable;

abstract class Directive {

    private $originalState;
    private $timeInit;
    
    final public function onInit(State $state) : void
    {
        $this->originalState = unserialize(serialize(($state)));
        $this->timeInit = microtime(true);
    }
    
    final public function call(State $state, array $config,array $groups,int $order) : void
    {
        $log = $state->isDirectiveDebug();
        if($log) {
            $this->onInit($state);
        }
        $error = false;
        if ($state->groups()->isAnyKeyEnabled($groups)) {
            try {
                foreach ($config as $keyConfig => $valueConfig) {
                    $config[$keyConfig] = $state->alias($valueConfig);
                }
                $this->execute($state,$config);
            } catch(Throwable $e) {
                $error = $e->getMessage();
                $state->groups()->enable(Groups::ERROR_FLOW);
            } 
        }
        if($log || $error !== false) {
            $this->onFinish($state,$order,$error);
        }
    }

    final public function onFinish(State $state,int $order,$error) : void 
    {
        $output = [
            'id' => $state->id(),
            'directive' => get_class($this),
            'order' => $order,
            'error' => $error
        ];

        if ($state->isDirectiveDebug()) {
            $output['duration'] = round((microtime(true) - $this->timeInit) * 1000);
            $this->addDiffStateToOutput($output,$state,'groups','read','groups');
            $this->addDiffStateToOutput($output,$state,'message','getPath','path');
            $this->addDiffStateToOutput($output,$state,'message','getHeaders','headers');
            $this->addDiffStateToOutput($output,$state,'message','getBody','body');
            $this->addDiffStateToOutput($output,$state,'message','getStatus','status');
            $this->addDiffStateToOutput($output,$state,'message','getQueryParams','queryParams');
            $this->addDiffStateToOutput($output,$state,'memory','read','memory');
        }

        $state->addDebug($output);
    }

    final public function addDiffStateToOutput(
        &$output, 
        State $state, 
        string $stateMethod,
        string $getMethod,
        string $name
    ) : void 
    {
        if ($this->originalState->$stateMethod()->$getMethod() 
            !== $state->$stateMethod()->$getMethod()) {
            $output[$name] = [
                'from' => $this->originalState->$stateMethod()->$getMethod(), 
                'to'=> $state->$stateMethod()->$getMethod()
            ];
        }
    }

    final public static function run(State $state, array $config) : void
    {
        $class=get_called_class();
        (new $class)->execute($state,$config);
    }

    abstract protected function execute(State $state, array $config) : void;
}