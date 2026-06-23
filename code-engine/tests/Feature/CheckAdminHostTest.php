<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

use App\Core\VirtualHostAccessAdmin;
use App\Http\Middleware\CheckAdminHost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Closure;
use Exception;

class CheckAdminHostTest extends TestCase
{
    private VirtualHostAccessAdmin $virtualHostAccessAdmin;
    private CheckAdminHost $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->virtualHostAccessAdmin = $this->createMock(VirtualHostAccessAdmin::class);

        $this->middleware = new CheckAdminHost(
            $this->virtualHostAccessAdmin
        );

        $this->withoutMiddleware();
    }

    // ──────────────────────────────────────────────
    //  handle – Happy Path
    // ──────────────────────────────────────────────

    public function testHandleSetsVirtualHostOnRequestWhenAccessGranted(): void
    {
        config(['app.ADMIN_CLOSED' => false]);

        $this->virtualHostAccessAdmin
            ->expects($this->once())
            ->method('get')
            ->willReturn([
                'ORG' => 'myorg',
                'TYPE' => 'ADMIN',
                'ENV' => 'production',
                'PATH' => '',
            ]);

        $request = $this->buildRequest();
        $nextCalled = false;

        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            $this->assertSame('myorg', $req->virtualHost['ORG']);
            $this->assertSame('ADMIN', $req->virtualHost['TYPE']);
            $this->assertSame('production', $req->virtualHost['ENV']);
            return response('OK');
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getContent());
    }

    public function testHandlePassesCorrectParametersToVirtualHostAccessAdmin(): void
    {
        config(['app.ADMIN_CLOSED' => false]);

        $this->virtualHostAccessAdmin
            ->expects($this->once())
            ->method('get')
            ->with(
                'admin.example.com',
                'public-host-value',
                'public-host-key-value',
                true
            )
            ->willReturn([
                'ORG' => 'myorg',
                'TYPE' => 'ADMIN',
                'ENV' => 'production',
                'PATH' => '',
            ]);

        $request = $this->buildRequest([
            'public-host' => 'public-host-value',
            'public-host-key' => 'public-host-key-value',
        ]);
        $request->headers->set('HOST', 'admin.example.com');

        $this->middleware->handle($request, function ($req) {
            return response('OK');
        });
    }

    // ──────────────────────────────────────────────
    //  handle – Admin Closed
    // ──────────────────────────────────────────────

    public function testHandleReturns404WhenAdminIsClosed(): void
    {
        config(['app.ADMIN_CLOSED' => true]);

        $this->virtualHostAccessAdmin
            ->expects($this->never())
            ->method('get');

        $request = $this->buildRequest();
        $nextCalled = false;

        $response = $this->middleware->handle($request, function () use (&$nextCalled) {
            $nextCalled = true;
            return response('OK');
        });

        $this->assertFalse($nextCalled);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    // ──────────────────────────────────────────────
    //  handle – Exception from VirtualHostAccessAdmin
    // ──────────────────────────────────────────────

    public function testHandleReturns404WhenVirtualHostAccessAdminThrowsException(): void
    {
        config(['app.ADMIN_CLOSED' => false]);

        $this->virtualHostAccessAdmin
            ->expects($this->once())
            ->method('get')
            ->willThrowException(new Exception('admin does not exist'));

        $request = $this->buildRequest();
        $nextCalled = false;

        $response = $this->middleware->handle($request, function () use (&$nextCalled) {
            $nextCalled = true;
            return response('OK');
        });

        $this->assertFalse($nextCalled);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    public function testHandleReturns404WhenPublicDoesNotExist(): void
    {
        config(['app.ADMIN_CLOSED' => false]);

        $this->virtualHostAccessAdmin
            ->expects($this->once())
            ->method('get')
            ->willThrowException(new Exception('public does not exist'));

        $request = $this->buildRequest();
        $nextCalled = false;

        $response = $this->middleware->handle($request, function () use (&$nextCalled) {
            $nextCalled = true;
            return response('OK');
        });

        $this->assertFalse($nextCalled);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testHandleReturns404WhenKeyIsNotCorrect(): void
    {
        config(['app.ADMIN_CLOSED' => false]);

        $this->virtualHostAccessAdmin
            ->expects($this->once())
            ->method('get')
            ->willThrowException(new Exception('key is not correct'));

        $request = $this->buildRequest();
        $nextCalled = false;

        $response = $this->middleware->handle($request, function () use (&$nextCalled) {
            $nextCalled = true;
            return response('OK');
        });

        $this->assertFalse($nextCalled);
        $this->assertEquals(404, $response->getStatusCode());
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    private function buildRequest(array $headers = []): Request
    {
        $request = Request::create('/hefesto/api', 'GET');
        $request->setUserResolver(fn () => null);
        $request->setRouteResolver(fn () => null);

        $request->headers->set('HOST', 'admin.example.com');

        if (isset($headers['public-host'])) {
            $request->headers->set('public-host', $headers['public-host']);
        } else {
            $request->headers->set('public-host', 'myorg.example.com');
        }

        if (isset($headers['public-host-key'])) {
            $request->headers->set('public-host-key', $headers['public-host-key']);
        } else {
            $request->headers->set('public-host-key', 'my-key');
        }

        return $request;
    }
}