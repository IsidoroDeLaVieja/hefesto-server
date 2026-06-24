<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

use App\Core\VirtualHostStorage;
use App\Core\VirtualHostAccessAdmin;
use App\Http\Middleware\CheckPublicHost;
use Illuminate\Http\Request;
use Exception;

class CheckPublicHostTest extends TestCase
{
    private VirtualHostStorage $virtualHostStorage;
    private VirtualHostAccessAdmin $virtualHostAccessAdmin;
    private CheckPublicHost $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->virtualHostStorage = $this->createMock(VirtualHostStorage::class);
        $this->virtualHostAccessAdmin = $this->createMock(VirtualHostAccessAdmin::class);

        $this->middleware = new CheckPublicHost(
            $this->virtualHostStorage,
            $this->virtualHostAccessAdmin
        );

        $this->withoutMiddleware();
    }

    // ──────────────────────────────────────────────
    //  handle – Public host found
    // ──────────────────────────────────────────────

    public function testHandleSetsVirtualHostWhenPublicHostFound(): void
    {
        $_SERVER['SERVER_NAME'] = 'public.example.com';

        $this->virtualHostStorage
            ->expects($this->once())
            ->method('getPublic')
            ->with('public.example.com')
            ->willReturn([
                'ORG' => 'myorg',
                'TYPE' => 'PUBLIC',
                'ENV' => 'production',
                'PATH' => '',
            ]);

        $this->virtualHostAccessAdmin
            ->expects($this->never())
            ->method('get');

        $request = $this->buildRequest();
        $nextCalled = false;

        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            $this->assertSame('myorg', $req->virtualHost['ORG']);
            $this->assertSame('PUBLIC', $req->virtualHost['TYPE']);
            $this->assertSame('production', $req->virtualHost['ENV']);
            return response('OK');
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getContent());
    }

    // ──────────────────────────────────────────────
    //  handle – No public host, calls VirtualHostAccessAdmin
    // ──────────────────────────────────────────────

    public function testHandleCallsAccessAdminWhenNoPublicHost(): void
    {
        $_SERVER['SERVER_NAME'] = 'admin.example.com';

        $this->virtualHostStorage
            ->expects($this->once())
            ->method('getPublic')
            ->with('admin.example.com')
            ->willReturn(null);

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

        $request = $this->buildRequest();
        $nextCalled = false;

        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            $this->assertSame('myorg', $req->virtualHost['ORG']);
            $this->assertSame('ADMIN', $req->virtualHost['TYPE']);
            return response('OK');
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testHandlePassesEmptyKeyWhenHeaderNotSet(): void
    {
        $_SERVER['SERVER_NAME'] = 'admin.example.com';

        $this->virtualHostStorage
            ->expects($this->once())
            ->method('getPublic')
            ->with('admin.example.com')
            ->willReturn(null);

        $this->virtualHostAccessAdmin
            ->expects($this->once())
            ->method('get')
            ->with(
                'admin.example.com',
                'public-host-value',
                '',
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
            'public-host-key' => '',
        ]);

        $this->middleware->handle($request, function ($req) {
            return response('OK');
        });
    }

    // ──────────────────────────────────────────────
    //  handle – Admin closed scenarios
    // ──────────────────────────────────────────────

    public function testHandleReturns404WhenAdminIsClosedAndNotLocal(): void
    {
        $_SERVER['SERVER_NAME'] = 'admin.example.com';

        config(['app.ADMIN_CLOSED' => true]);

        $this->virtualHostStorage
            ->expects($this->once())
            ->method('getPublic')
            ->with('admin.example.com')
            ->willReturn(null);

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
    //  handle – Exception from dependencies
    // ──────────────────────────────────────────────

    public function testHandleReturns404WhenVirtualHostAccessAdminThrowsException(): void
    {
        $_SERVER['SERVER_NAME'] = 'admin.example.com';

        $this->virtualHostStorage
            ->expects($this->once())
            ->method('getPublic')
            ->with('admin.example.com')
            ->willReturn(null);

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
        $_SERVER['SERVER_NAME'] = 'admin.example.com';

        $this->virtualHostStorage
            ->expects($this->once())
            ->method('getPublic')
            ->with('admin.example.com')
            ->willReturn(null);

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
        $_SERVER['SERVER_NAME'] = 'admin.example.com';

        $this->virtualHostStorage
            ->expects($this->once())
            ->method('getPublic')
            ->with('admin.example.com')
            ->willReturn(null);

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

    public function testHandleReturns404WhenGetPublicThrowsException(): void
    {
        $_SERVER['SERVER_NAME'] = 'public.example.com';

        $this->virtualHostStorage
            ->expects($this->once())
            ->method('getPublic')
            ->with('public.example.com')
            ->willThrowException(new Exception('storage error'));

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
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    private function buildRequest(array $headers = []): Request
    {
        $request = Request::create('/hefesto/api', 'GET');
        $request->setUserResolver(fn () => null);
        $request->setRouteResolver(fn () => null);

        if (isset($headers['public-host'])) {
            $request->headers->set('public-host', $headers['public-host']);
        } else {
            $request->headers->set('public-host', 'public-host-value');
        }

        if (isset($headers['public-host-key'])) {
            $request->headers->set('public-host-key', $headers['public-host-key']);
        } else {
            $request->headers->set('public-host-key', 'public-host-key-value');
        }

        return $request;
    }
}