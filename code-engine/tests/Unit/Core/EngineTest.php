<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Tests\Fixture\StateFixture;
use Tests\Fixture\DirectivesFixture;
use Tests\Fixture\DirectiveFixture;
use App\Core\State;
use App\Core\EngineDispatcher;
use App\Core\Engine;
use App\Core\Groups;
use App\Core\DirectiveFactory;
use App\Core\Directive;
use SplDoublyLinkedList;

class EngineTest extends TestCase 
{
    private $state;
    private $engineDispatcherStub;
    private $directiveFactoryStub;
    
    protected function setUp() : void
    {
        $this->state = StateFixture::get([]);
        $this->state->enableDirectiveDebug();
        $this->engineDispatcherStub = $this->createStub(EngineDispatcher::class);
        $this->directiveFactoryStub = $this->createStub(DirectiveFactory::class);
    }

    private function buildEngine(SplDoublyLinkedList $directives, ?object $dispatcher = null): Engine
    {
        return new Engine(
            $this->state,
            $directives,
            $dispatcher ?? $this->engineDispatcherStub,
            $this->directiveFactoryStub
        );
    }

    private function expectMakeDirective(): void
    {
        $this->directiveFactoryStub
            ->method('make')
            ->willReturnCallback(function ($name) {
                return new $name();
            });
    }

    public function testShouldDoLoopOverDirectives() : void 
    {
        $this->expectMakeDirective();

        $dispatcher = $this->getMockBuilder(EngineDispatcher::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();
        $dispatcher->expects($this->never())->method('send');

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

        $engine = $this->buildEngine($directives, $dispatcher);
        
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
        $this->expectMakeDirective();

        $dispatcher = $this->getMockBuilder(EngineDispatcher::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();
        $dispatcher->expects($this->never())->method('send');

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

        $engine = $this->buildEngine($directives, $dispatcher);
        
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
        $this->expectMakeDirective();

        $dispatcher = $this->getMockBuilder(EngineDispatcher::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();

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

        $engine = $this->buildEngine($directives, $dispatcher);

        $dispatcher->expects($this->once())
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
        $this->expectMakeDirective();

        $directives = DirectivesFixture::get([[
            'status' => 201,
        ],[
            'status' => 500,
            'groups' => [Groups::ERROR_FLOW]
        ],[
            'status' => 204,
            'groups' => [Groups::AFTER_FLOW]
        ]]);

        $engine = $this->buildEngine($directives);
        
        $engine->execute();
        self::assertSame(201,$this->state->message()->getStatus());

        $engine->executeAfter();
        self::assertSame(204,$this->state->message()->getStatus());
    }

    public function testShouldReturnDirectivesCloned() : void 
    {
        $directives = DirectivesFixture::get([[
            'status' => 200,
        ]]);

        $engine = $this->buildEngine($directives);
        $cloned = $engine->directives();

        self::assertNotSame($directives, $cloned);
        self::assertEquals($directives, $cloned);
    }

    public function testShouldReturnState() : void 
    {
        $directives = new SplDoublyLinkedList();
        $engine = $this->buildEngine($directives);

        self::assertSame($this->state, $engine->state());
    }

    public function testShouldExecuteWithEmptyDirectives() : void 
    {
        $directives = new SplDoublyLinkedList();
        $this->directiveFactoryStub->method('make');

        $engine = $this->buildEngine($directives);
        $message = $engine->execute();

        self::assertSame(200, $message->getStatus());
    }

    public function testShouldExecuteWithoutLogging() : void 
    {
        $directives = new SplDoublyLinkedList();
        $this->directiveFactoryStub->method('make');

        $dispatcher = $this->getMockBuilder(EngineDispatcher::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();
        $dispatcher->expects($this->never())->method('send');

        $engine = $this->buildEngine($directives, $dispatcher);
        $message = $engine->execute(Groups::NORMAL_FLOW, false);

        self::assertSame([], $this->state->getDebug());
    }

    public function testShouldNotDispatchWhenNotQueueFlow() : void 
    {
        $this->expectMakeDirective();

        $dispatcher = $this->getMockBuilder(EngineDispatcher::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();
        $dispatcher->expects($this->never())->method('send');

        $directives = DirectivesFixture::get([[
            'key-header' => 'geom',
            'value-header' => 'cuadrado'
        ],[
            'status' => 200,
        ]]);

        $engine = $this->buildEngine($directives, $dispatcher);
        $message = $engine->execute();

        self::assertSame(200, $message->getStatus());
    }

    public function testShouldNotDispatchTwice() : void 
    {
        $this->expectMakeDirective();

        $dispatcher = $this->getMockBuilder(EngineDispatcher::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();
        $dispatcher->expects($this->once())->method('send');

        $directives = DirectivesFixture::get([[
            'key-header' => 'queue-flow',
            'value-header' => 'ok'
        ],[
            'status' => 200,
        ]]);

        $engine = $this->buildEngine($directives, $dispatcher);
        $engine->execute();
        $engine->execute();

        self::assertTrue(true);
    }

    public function testShouldReturnBodyTooBigMessage() : void 
    {
        $this->expectMakeDirective();

        $body = str_repeat('a', 401);
        $this->state->message()->setBody($body);
        $this->state->message()->setHeader('CONTENT-LENGTH', '401');

        $directives = DirectivesFixture::get([[
            'status' => 200,
        ]]);

        $engine = $this->buildEngine($directives);
        $message = $engine->execute();

        $debug = $this->state->getDebug();
        self::assertSame('body is too big', $debug[0]['body']);
        self::assertSame('body is too big', $debug[count($debug) - 1]['body']);
    }

    public function testExecuteAfterWithoutAfterFlowDirectives() : void 
    {
        $this->expectMakeDirective();

        $dispatcher = $this->getMockBuilder(EngineDispatcher::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();
        $dispatcher->expects($this->never())->method('send');

        $directives = DirectivesFixture::get([[
            'status' => 200,
        ]]);

        $engine = $this->buildEngine($directives, $dispatcher);
        $engine->execute();

        $message = $engine->executeAfter();
        self::assertSame(200, $message->getStatus());
    }

    public function testOnInitWhenIsQueued() : void
    {
        $this->expectMakeDirective();

        $this->state->queue();
        $directives = new SplDoublyLinkedList();

        $engine = $this->buildEngine($directives);
        $engine->execute(Groups::NORMAL_FLOW, true);

        $debug = $this->state->getDebug();
        self::assertSame('INIT_JOB', $debug[0]['type']);
        self::assertFalse(isset($debug[0]['realVerb']));
        self::assertFalse(isset($debug[0]['realPath']));
        self::assertFalse(isset($debug[0]['queryParams']));
        self::assertFalse(isset($debug[0]['body']));
        self::assertArrayHasKey('verb', $debug[0]);
        self::assertArrayHasKey('path', $debug[0]);
        self::assertArrayHasKey('headers', $debug[0]);
        self::assertArrayHasKey('timestamp', $debug[0]);
    }

    public function testOnFinishWhenIsQueued() : void
    {
        $this->expectMakeDirective();

        $this->state->queue();
        $directives = new SplDoublyLinkedList();

        $engine = $this->buildEngine($directives);
        $engine->execute(Groups::NORMAL_FLOW, true);

        $debug = $this->state->getDebug();
        $finish = $debug[count($debug) - 1];
        self::assertSame('FINISH_JOB', $finish['type']);
        self::assertFalse(isset($finish['headers']));
        self::assertFalse(isset($finish['body']));
        self::assertArrayHasKey('status', $finish);
        self::assertArrayHasKey('duration', $finish);
    }

    public function testOnFinishWithCorrelationId() : void
    {
        $this->expectMakeDirective();

        $directives = DirectivesFixture::get([[
            'key-memory' => 'correlationId',
            'value-memory' => 'corr-12345',
            'status' => 200,
        ]]);

        $engine = $this->buildEngine($directives);
        $engine->execute();

        $debug = $this->state->getDebug();
        $finish = $debug[count($debug) - 1];
        self::assertArrayHasKey('correlationId', $finish);
        self::assertSame('corr-12345', $finish['correlationId']);
    }

    public function testOnInitWithBodyTooBig() : void
    {
        $this->expectMakeDirective();

        $body = str_repeat('x', 401);
        $this->state->message()->setBody($body);
        $this->state->message()->setHeader('CONTENT-LENGTH', '401');

        $directives = new SplDoublyLinkedList();

        $engine = $this->buildEngine($directives);
        $engine->execute();

        $debug = $this->state->getDebug();
        self::assertSame('body is too big', $debug[0]['body']);
    }

    public function testDirectiveWithExplicitGroups() : void
    {
        $this->expectMakeDirective();

        $directives = new SplDoublyLinkedList();
        $directives->push(new \App\Core\DirectiveRequest(
            '1',
            \Tests\Fixture\DirectiveFixture::class,
            ['status' => 201],
            [Groups::NORMAL_FLOW]
        ));

        $engine = $this->buildEngine($directives);
        $message = $engine->execute();

        self::assertSame(201, $message->getStatus());
    }

    public function testShouldUseCustomDirectiveFactory() : void 
    {
        $customDirective = $this->getMockBuilder(Directive::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['execute'])
            ->getMock();

        $customDirective->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function (State $state, array $config) {
                $state->message()->setStatus(418);
            });

        $directiveFactoryMock = $this->createMock(DirectiveFactory::class);
        $directiveFactoryMock
            ->expects($this->once())
            ->method('make')
            ->with(DirectiveFixture::class)
            ->willReturn($customDirective);

        $directives = DirectivesFixture::get([[
            'status' => 200,
        ]]);

        $engine = new Engine(
            $this->state,
            $directives,
            $this->engineDispatcherStub,
            $directiveFactoryMock
        );
        $message = $engine->execute();

        self::assertSame(418, $message->getStatus());
    }
}