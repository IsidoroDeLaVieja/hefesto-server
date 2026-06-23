<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

use App\Core\Engine;
use App\Http\Middleware\ExecuteEngineAfter;
use Illuminate\Http\Request;

class ExecuteEngineAfterTest extends TestCase
{
    private ExecuteEngineAfter $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new ExecuteEngineAfter();

        $this->withoutMiddleware();
    }

    // ──────────────────────────────────────────────
    //  handle – Always passes through
    // ──────────────────────────────────────────────

    public function testHandlePassesThrough(): void
    {
        $request = Request::create('/hefesto/api', 'GET');
        $nextCalled = false;

        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return response('OK');
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getContent());
    }

    public function testHandleReturnsNextResponse(): void
    {
        $request = Request::create('/hefesto/api', 'GET');
        $request->attributes->set('engine', 'should-be-ignored-in-handle');

        $response = $this->middleware->handle($request, function ($req) {
            return response('from-next');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('from-next', $response->getContent());
    }

    // ──────────────────────────────────────────────
    //  terminate – Happy Path
    // ──────────────────────────────────────────────

    public function testTerminateCallsExecuteAfterWhenEngineIsSet(): void
    {
        $engine = $this->createMock(Engine::class);
        $engine->expects($this->once())
            ->method('executeAfter');

        $request = new Request();
        $request->setUserResolver(fn () => null);
        $request->engine = $engine;

        $response = response('OK');

        $this->middleware->terminate($request, $response);
    }

    // ──────────────────────────────────────────────
    //  terminate – No engine set
    // ──────────────────────────────────────────────

    public function testTerminateDoesNothingWhenEngineIsNotSet(): void
    {
        $request = new Request();
        $request->setUserResolver(fn () => null);

        $response = response('OK');

        // This should not throw or have any side effects
        $this->middleware->terminate($request, $response);

        $this->assertTrue(true);
    }

    public function testTerminateDoesNothingWhenEngineIsNull(): void
    {
        $request = new Request();
        $request->setUserResolver(fn () => null);
        $request->engine = null;

        $response = response('OK');

        $this->middleware->terminate($request, $response);

        $this->assertTrue(true);
    }
}