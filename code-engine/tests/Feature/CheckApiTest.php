<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

use App\Core\ApiStorage;
use App\Http\Middleware\CheckApi;
use Illuminate\Http\Request;
use Tests\Fixture\InMemoryMemory;

class CheckApiTest extends TestCase
{
    private ApiStorage $apiStorage;
    private CheckApi $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $memory = new InMemoryMemory();
        $this->apiStorage = new ApiStorage($memory);
        $this->middleware = new CheckApi($this->apiStorage);
    }

    // ──────────────────────────────────────────────
    //  Admin scenarios
    // ──────────────────────────────────────────────

    public function testAdminPassesWhenApiIsFoundAndActive(): void
    {
        $this->apiStorage->set('myorg', 'production', 'my-key', 'release1', true, false);

        $request = $this->buildAdminRequest('/my-key/settings');
        $response = $this->handleRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getContent());
        $this->assertNotNull($request->api);
        $this->assertSame('my-key', $request->api['key']);
    }

    public function testAdminReturns404WhenApiNotFound(): void
    {
        $request = $this->buildAdminRequest('/nonexistent');
        $response = $this->handleRequest($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAdminReturns404WhenApiIsNotActive(): void
    {
        $this->apiStorage->set('myorg', 'production', 'my-key', 'release1', false, false);

        $request = $this->buildAdminRequest('/my-key');
        $response = $this->handleRequest($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAdminReturns404WhenKeyCannotBeExtracted(): void
    {
        $request = $this->buildAdminRequest('/');
        $response = $this->handleRequest($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    // ──────────────────────────────────────────────
    //  Non-admin scenarios
    // ──────────────────────────────────────────────

    public function testNonAdminPassesWhenApiIsFoundActiveAndPublic(): void
    {
        $this->apiStorage->set('myorg', 'production', 'my-key', 'release1', true, true);

        $request = $this->buildUserRequest('/my-key');
        $response = $this->handleRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getContent());
        $this->assertNotNull($request->api);
        $this->assertSame('my-key', $request->api['key']);
    }

    public function testNonAdminReturns404WhenApiIsPrivate(): void
    {
        $this->apiStorage->set('myorg', 'production', 'my-key', 'release1', true, false);

        $request = $this->buildUserRequest('/my-key');
        $response = $this->handleRequest($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testNonAdminReturns404WhenApiNotFound(): void
    {
        $request = $this->buildUserRequest('/nonexistent');
        $response = $this->handleRequest($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testNonAdminReturns404WhenApiIsNotActive(): void
    {
        $this->apiStorage->set('myorg', 'production', 'my-key', 'release1', false, true);

        $request = $this->buildUserRequest('/my-key');
        $response = $this->handleRequest($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    // ──────────────────────────────────────────────
    //  PATH-based key resolution (non-admin)
    // ──────────────────────────────────────────────

    public function testNonAdminUsesVirtualHostPathForKeyResolution(): void
    {
        $this->apiStorage->set('myorg', 'production', 'my-key', 'release1', true, true);

        $request = $this->buildRequestWithPath('/ignored', '/my-key/extra');
        $response = $this->handleRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($request->api);
        $this->assertSame('my-key', $request->api['key']);
    }

    public function testAdminIgnoresVirtualHostPathAndUsesRequestPath(): void
    {
        $this->apiStorage->set('myorg', 'production', 'my-key', 'release1', true, false);

        // Admin ignores PATH, uses request path which puts key at segment 1
        $request = $this->buildAdminRequestWithPath('/my-key/settings', '/ignored');
        $response = $this->handleRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($request->api);
        $this->assertSame('my-key', $request->api['key']);
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    private function buildAdminRequest(string $uri): Request
    {
        $request = Request::create($uri, 'GET');
        $request->virtualHost = [
            'ORG' => 'myorg',
            'ENV' => 'production',
            'TYPE' => 'ADMIN',
            'PATH' => null,
        ];
        return $request;
    }

    private function buildUserRequest(string $uri): Request
    {
        $request = Request::create($uri, 'GET');
        $request->virtualHost = [
            'ORG' => 'myorg',
            'ENV' => 'production',
            'TYPE' => 'USER',
            'PATH' => null,
        ];
        return $request;
    }

    private function buildRequestWithPath(string $uri, string $virtualHostPath): Request
    {
        $request = Request::create($uri, 'GET');
        $request->virtualHost = [
            'ORG' => 'myorg',
            'ENV' => 'production',
            'TYPE' => 'USER',
            'PATH' => $virtualHostPath,
        ];
        return $request;
    }

    private function buildAdminRequestWithPath(string $uri, string $virtualHostPath): Request
    {
        $request = Request::create($uri, 'GET');
        $request->virtualHost = [
            'ORG' => 'myorg',
            'ENV' => 'production',
            'TYPE' => 'ADMIN',
            'PATH' => $virtualHostPath,
        ];
        return $request;
    }

    private function handleRequest(Request $request): mixed
    {
        $next = function ($req) {
            return response('OK', 200);
        };
        return $this->middleware->handle($request, $next);
    }
}