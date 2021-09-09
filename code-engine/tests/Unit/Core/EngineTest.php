<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Tests\Fixture\StateFixture;
use Tests\Fixture\DirectivesFixture;
use App\Core\State;
use App\Core\EngineDispatcher;
use App\Adapters\Log;
use App\Core\Engine;
use App\Core\Groups;
use SplDoublyLinkedList;

class EngineTest extends TestCase 
{
    private $state;
    private $engineDispatcherMock;
    
    protected function setUp() : void
    {
        $this->state = StateFixture::get([]);
        $this->state->enableDirectiveDebug();
        $this->engineDispatcherMock = $this->getMockBuilder(EngineDispatcher::class)
                ->disableOriginalConstructor()->setMethods([
                    'send',
                ])->getMock();
    }

    public function testShouldDoLoopOverDirectives() : void 
    {
        $directives = DirectivesFixture::get([[
            'key-header' => 'geom',
            'value-header' => 'cuadrado',
            'status' => 404
        ],[
            'key-header' => 'geom',
            'value-header' => 'triangulo',
            'body' => 'rectangulo'
        ],[
            'status' => 500,
            'groups' => [Groups::AFTER_FLOW]
        ]]);

        $debugExpected = [
                [
                    'id' => $this->state->id(),
                    'type' => 'REQUEST',
                    'verb' => '',
                    'path' => '',
                    'realVerb' => $this->state->message()->getVerb(),
                    'realPath' => $this->state->message()->getPath(),
                    'queryParams' => $this->state->message()->getQueryParams(),
                    'headers' => $this->state->message()->getHeaders(),
                    'body' => $this->state->message()->getBody()
                ],
                [
                    'id' => $this->state->id(),
                    'directive' => 'Tests\Fixture\DirectiveFixture',
                    'order' => 1,
                    'duration' => 0,
                    'error' => false,
                    'headers' => [ 'from' => ['CONTENT-LENGTH'=>'0'] , 'to' => ['CONTENT-LENGTH'=>'0','GEOM'=>'cuadrado'] ],
                    'status' => [ 'from' => $this->state->message()->getStatus() , 'to' => 404 ]
                ],
                [
                    'id' => $this->state->id(),
                    'directive' => 'Tests\Fixture\DirectiveFixture',
                    'order' => 2,
                    'duration' => 0,
                    'error' => false,
                    'headers' => [ 'from' => ['CONTENT-LENGTH'=>'0','GEOM'=>'cuadrado'] , 'to' => ['CONTENT-LENGTH'=>'10','GEOM'=>'triangulo'] ],
                    'body' => [ 'from' => '' , 'to' => 'rectangulo' ]
                ],
                [
                    'id' => $this->state->id(),
                    'directive' => 'Tests\Fixture\DirectiveFixture',
                    'order' => 3,
                    'error' => false,
                    'duration' => 0.0
                ],
                [
                    'id' => $this->state->id(),
                    'type' => 'RESPONSE',
                    'status' => 404,
                    'headers' => ['CONTENT-LENGTH'=>'10','GEOM'=>'triangulo'],
                    'body' => 'rectangulo'
                ],
        ];
        $this->engineDispatcherMock->expects($this->never())->method('send');

        $engine = new Engine($this->state,$directives,$this->engineDispatcherMock);
        
        $message = $engine->execute();
        
        self::assertSame(['CONTENT-LENGTH'=>'10','GEOM'=>'triangulo'],$message->getHeaders());
        self::assertSame(404,$message->getStatus());
        self::assertSame('rectangulo',$message->getBody());

        $debug = $this->state->getDebug();
        unset($debug[0]['timestamp']);
        unset($debug[count($debug) - 1]['duration']);
        $this->assertEquals($debugExpected,$debug);
    }

    public function testShouldNotExecuteDirectivesWhenThereIsError() : void 
    {
        $directives = DirectivesFixture::get([[
            'key-header' => 'x-header-error',
            'value-header' => 'ok'
        ],[
            'key-header' => 'geom',
            'value-header' => 'cuadrado',
            'status' => 404
        ],[
            'key-header' => 'geom',
            'value-header' => 'triangulo',
            'body' => 'rectangulo'
        ],[
            'status' => 401,
            'groups' => [Groups::ERROR_FLOW]
        ]]);

        $debugExpected = [
            [
                'id' => $this->state->id(),
                'type' => 'REQUEST',
                'verb' => '',
                'path' => '',
                'realVerb' => $this->state->message()->getVerb(),
                'realPath' => $this->state->message()->getPath(),
                'queryParams' => $this->state->message()->getQueryParams(),
                'headers' => $this->state->message()->getHeaders(),
                'body' => $this->state->message()->getBody()
            ],
            [
                'id' => $this->state->id(),
                'directive' => 'Tests\Fixture\DirectiveFixture',
                'order' => 1,
                'duration' => 0,
                'error' => 'An error',
                'groups' => [ 'from' => [Groups::NORMAL_FLOW] , 'to' => [Groups::ERROR_FLOW] ],
                'headers' => [ 'from' => ['CONTENT-LENGTH' => '0'] , 'to' => ['CONTENT-LENGTH' => '0','X-HEADER-ERROR'=>'ok'] ]
            ],
            [
                'id' => $this->state->id(),
                'directive' => 'Tests\Fixture\DirectiveFixture',
                'order' => 2,
                'duration' => 0,
                'error' => false
            ],
            [
                'id' => $this->state->id(),
                'directive' => 'Tests\Fixture\DirectiveFixture',
                'order' => 3,
                'duration' => 0,
                'error' => false
            ],
            [
                'id' => $this->state->id(),
                'directive' => 'Tests\Fixture\DirectiveFixture',
                'order' => 4,
                'duration' => 0,
                'error' => false,
                'status' => [ 'from' => $this->state->message()->getStatus() , 'to' => 401 ]
            ],
            [
                'id' => $this->state->id(),
                'type' => 'RESPONSE',
                'status' => 401,
                'headers' => ['CONTENT-LENGTH'=>'0','X-HEADER-ERROR'=>'ok'],
                'body' => ''
            ],
        ];
        $this->engineDispatcherMock->expects($this->never())->method('send');

        $engine = new Engine($this->state,$directives,$this->engineDispatcherMock);
        
        $message = $engine->execute();
        
        self::assertSame(['CONTENT-LENGTH'=>'0','X-HEADER-ERROR'=>'ok'],$message->getHeaders());
        self::assertSame(401,$message->getStatus());
        self::assertSame('',$message->getBody());
        
        $debug = $this->state->getDebug();
        unset($debug[0]['timestamp']);
        unset($debug[count($debug) - 1]['duration']);
        $this->assertEquals($debugExpected,$debug);
    }

    public function testShouldDispatchItself() : void 
    {
        $directives = DirectivesFixture::get([[
            'key-header' => 'geom',
            'value-header' => 'cuadrado'
        ],[
            'key-header' => 'queue-flow',
            'value-header' => 'ok'
        ],[
            'key-header' => 'geom',
            'value-header' => 'triangulo',
            'body' => 'rectangulo'
        ],[
            'status' => 201,
            'groups' => [Groups::QUEUE_FLOW]
        ],[
            'status' => 401,
            'groups' => [Groups::ERROR_FLOW]
        ]]);

        $debugExpected = [
            [
                'id' => $this->state->id(),
                'type' => 'REQUEST',
                'verb' => '',
                'path' => '',
                'realVerb' => $this->state->message()->getVerb(),
                'realPath' => $this->state->message()->getPath(),
                'queryParams' => $this->state->message()->getQueryParams(),
                'headers' => $this->state->message()->getHeaders(),
                'body' => $this->state->message()->getBody()
            ],
            [
                'id' => $this->state->id(),
                'directive' => 'Tests\Fixture\DirectiveFixture',
                'order' => 1,
                'duration' => 0,
                'error' => false,
                'headers' => [ 'from' => ['CONTENT-LENGTH' => '0'] , 'to' => ['CONTENT-LENGTH' => '0','GEOM'=>'cuadrado'] ]
            ],
            [
                'id' => $this->state->id(),
                'directive' => 'Tests\Fixture\DirectiveFixture',
                'order' => 2,
                'duration' => 0,
                'error' => false,
                'groups' => [ 'from' => [Groups::NORMAL_FLOW] , 'to' => [Groups::QUEUE_FLOW] ],
                'headers' => [ 'from' => ['CONTENT-LENGTH' => '0','GEOM'=>'cuadrado'] , 'to' => ['CONTENT-LENGTH' => '0','GEOM'=>'cuadrado','QUEUE-FLOW' => 'ok'] ]
            ],
            [
                'id' => $this->state->id(),
                'directive' => 'Tests\Fixture\DirectiveFixture',
                'order' => 3,
                'duration' => 0,
                'error' => false
            ],
            [
                'id' => $this->state->id(),
                'directive' => 'Tests\Fixture\DirectiveFixture',
                'order' => 4,
                'duration' => 0,
                'error' => false,
                'status' => [ 'from' => $this->state->message()->getStatus() , 'to' => 201 ]
            ],
            [
                'id' => $this->state->id(),
                'directive' => 'Tests\Fixture\DirectiveFixture',
                'order' => 5,
                'duration' => 0,
                'error' => false
            ],
            [
                'id' => $this->state->id(),
                'type' => 'RESPONSE',
                'status' => 201,
                'headers' => ['CONTENT-LENGTH' => '0','GEOM'=>'cuadrado','QUEUE-FLOW' => 'ok'],
                'body' => ''
            ],
        ];

        $engine = new Engine($this->state,$directives,$this->engineDispatcherMock);

        $this->engineDispatcherMock->expects($this->once())
            ->method('send')
            ->with($engine,2,0);
        
        $message = $engine->execute();

        self::assertSame(201,$message->getStatus());

        $debug = $this->state->getDebug();
        unset($debug[0]['timestamp']);
        unset($debug[count($debug) - 1]['duration']);
        $this->assertEquals($debugExpected,$debug);
    }

    public function testEngineAfter() : void 
    {
        $directives = DirectivesFixture::get([[
            'status' => 201,
        ],[
            'status' => 500,
            'groups' => [Groups::ERROR_FLOW]
        ],[
            'status' => 204,
            'groups' => [Groups::AFTER_FLOW]
        ]]);

        $engine = new Engine($this->state,$directives,$this->engineDispatcherMock);
        
        $engine->execute();
        self::assertSame(201,$this->state->message()->getStatus());

        $engine->executeAfter();
        self::assertSame(204,$this->state->message()->getStatus());
    }
}