<?php

declare(strict_types=1);

namespace App\Core;
use SplDoublyLinkedList;

class Engine { 

    public const MAX_LENGTH_BODY = 400;
    private $state;
    private $directives;
    private $engineDispatcher;
    private $dispatched;
    private $timeInit;

    public function __construct(
        State $state, 
        SplDoublyLinkedList $directives,
        EngineDispatcher $engineDispatcher
    ) {
        $this->state = $state;
        $this->directives = $directives;
        $this->engineDispatcher = $engineDispatcher;
        $this->dispatched = false;
    }

    public function directives() : SplDoublyLinkedList 
    {
        return clone $this->directives;
    }

    public function state() : State 
    {
        return $this->state;
    }

    public function onInit() : void
    {
        $this->state->resetDebug();

        $this->timeInit = microtime(true);
        $debug = [
            'id' => $this->state->id(),
            'type' => 'INIT_JOB',
            'timestamp' => gmdate('Y-m-d H:i:s'),
            'verb' => $this->state->memory()->get('hefesto-definitionverb'),
            'path' => $this->state->memory()->get('hefesto-definitionpath'),
            'headers' => $this->state->message()->getHeaders()
        ];
        if (!$this->state->isQueued()) {
            $debug['realVerb'] = $this->state->message()->getVerb();
            $debug['realPath'] = $this->state->message()->getPath();
            $debug['type'] = 'REQUEST';
            $debug['queryParams'] = $this->state->message()->getQueryParams();
            $debug['body'] = $this->state->message()->getHeader('CONTENT-LENGTH') < self::MAX_LENGTH_BODY 
                ? $this->state->message()->getBody()
                : 'body is too big';
        }
        $this->state->addDebug($debug);
    }

    public function execute(
        string $group = Groups::NORMAL_FLOW,
        bool $log = true
    ) : Message {
        $this->directives->rewind();
        if ($log) {
            $this->onInit();
        }
        
        $this->state->groups()->disableAll();
        $this->state->groups()->enable($group);
        
        $order = 1;
        while($this->directives->valid()){
            $directiveRequest = $this->directives->current();
            
            $groups = $directiveRequest->groups 
                        ?   $directiveRequest->groups 
                        :   [ Groups::NORMAL_FLOW ] ;
            $directive = new $directiveRequest->name();
            $directive->call($this->state,$directiveRequest->config,$groups,$order);
            $this->checkDispatch($order);

            $order++;
            $this->directives->next();
        }

        if ($log) {
            $this->onFinish();
        }
        $this->state->memory()->set('db-conn',null);
        return $this->state->message();
    }

    public function executeAfter() : Message
    {
        return $this->execute(Groups::AFTER_FLOW,false);
    }

    public function checkDispatch(int $order) : void
    {
        if ( $this->state->groups()->isEnabled(Groups::QUEUE_FLOW) 
            && $this->dispatched === false
        ) {
            $delay = (int)$this->state->memory()->get('QUEUE_DELAY');
            $this->engineDispatcher->send($this,$order,$delay);
            $this->dispatched = true;
        }
    }

    public function onFinish() : void
    {
        $duration = round((microtime(true) - $this->timeInit) * 1000);
        $debug = [
            'id' => $this->state->id(),
            'duration' => $duration,
            'type' => 'FINISH_JOB',
            'status' => $this->state->message()->getStatus()
        ];
        if (!$this->state->isQueued()) {
            $debug['type'] = 'RESPONSE';
            $debug['headers'] = $this->state->message()->getHeaders();
            $debug['body'] = $this->state->message()->getHeader('CONTENT-LENGTH') < self::MAX_LENGTH_BODY 
                ? $this->state->message()->getBody()
                : 'body is too big';
        }
        if ($this->state->memory()->get('correlationId')) {
            $debug['correlationId'] = $this->state->memory()->get('correlationId');
        }
        $this->state->addDebug($debug);
    }
}