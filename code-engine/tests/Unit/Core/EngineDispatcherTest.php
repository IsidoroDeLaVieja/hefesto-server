<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Groups;
use App\Core\Engine;
use Tests\Fixture\DirectivesFixture;
use Tests\Fixture\MockSenderEngineDispatcher;
use App\Core\EngineDispatcher;
use Tests\Fixture\StateFixture;

class EngineDispatcherTest extends TestCase 
{
    public function testShouldCutEngine() : void 
    {
        $state = StateFixture::get([]);
        $state->enableDirectiveDebug();

        $directives = DirectivesFixture::get([[
            'key-header' => 'geom',
            'value-header' => 'cuadrado'
        ],[
            'key-header' => 'potato',
            'value-header' => 'ok'
        ],[
            'key-header' => 'geom',
            'value-header' => 'triangulo'
        ],[
            'status' => 401,
            'groups' => [Groups::ERROR_FLOW]
        ]]);

        $engineDispatcherMock = new MockSenderEngineDispatcher();
        $engineDispatcher = new EngineDispatcher($engineDispatcherMock);
        $engine = new Engine($state,$directives,$engineDispatcher);
        
        $debugExpected = [
            [
            'id' => $state->id(),
            'type' => 'REQUEST',
            'verb' => '',
            'path' => '',
            'realVerb' => $state->message()->getVerb(),
            'realPath' => $state->message()->getPath(),
            'queryParams' => $state->message()->getQueryParams(),
            'headers' => $state->message()->getHeaders(),
            'body' => $state->message()->getBody()
            ],
            [
            'id' => $state->id(),
            'directive' => 'Tests\Fixture\DirectiveFixture',
            'order' => 1,
            'duration' => 0,
            'error' => false,
            'headers' => [ 'from' => ['CONTENT-LENGTH' => '0'] , 'to' => ['CONTENT-LENGTH' => '0','GEOM'=>'triangulo'] ]
            ],
            [
            'id' => $state->id(),
            'directive' => 'Tests\Fixture\DirectiveFixture',
            'order' => 2,
            'duration' => 0,
            'error' => false
            ],
            [
            'type' => 'RESPONSE',
            'id' => $state->id(),
            'status' => 200,
            'headers' => ['CONTENT-LENGTH' => '0','GEOM'=>'triangulo'] ,
            'body' => ''
            ]
        ];

        $engineDispatcher->send($engine,2,3);
        
        $debug = $state->getDebug();
        unset($debug[0]['timestamp']);
        unset($debug[count($debug) - 1]['duration']);
        $this->assertEquals($debugExpected,$debug);
    }

}