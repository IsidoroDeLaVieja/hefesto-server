<?php

declare(strict_types=1);

namespace Tests\Fixture;
use App\Core\Directive;
use App\Core\State;
use Exception;

class DirectiveFixture extends Directive {

    protected function execute(State $state, array $config) : void
    {
        if (isset($config['key-header'])) {
            $state->message()->setHeader($config['key-header'],$config['value-header']);
            if ($config['key-header'] === 'x-header-error' 
                    && $config['value-header'] === 'ok') {
                throw new Exception('An error');
            }
            if ($config['key-header'] === 'queue-flow' 
                    && $config['value-header'] === 'ok') {
                $state->groups()->enable('QUEUE_FLOW');
            }
        }
        if (isset($config['path'])) {
            $state->message()->setPath($config['path']);
        }
        if (isset($config['body'])) {
            $state->message()->setBody($config['body']);
        }
        if (isset($config['status'])) {
            $state->message()->setStatus($config['status']);
        }
        if (isset($config['key-query-param'])) {
            $state->message()->setQueryParam($config['key-query-param']
                ,$config['value-query-param']);
        }
        if (isset($config['key-memory'])) {
            $state->memory()->set($config['key-memory'],$config['value-memory']);
        }
    }
}