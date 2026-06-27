<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

use App\Adapters\Deploy;
use App\Adapters\ApiMemoryFactory;
use App\Core\ApiStorage;
use App\Http\Controllers\AdminController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Exception;

class AdminControllerTest extends TestCase
{
    private ApiStorage $apiStorage;
    private ApiMemoryFactory $apiMemoryFactory;
    private AdminController $controller;
    private Deploy $deploy;

    protected function setUp(): void
    {
        parent::setUp();

        $memory = new \Tests\Fixture\InMemoryMemory();
        $this->apiStorage = new ApiStorage($memory);

        $cachedFileMemory = $this->createStub(\App\Adapters\CachedFileMemory::class);
        $this->apiMemoryFactory = $this->createStub(ApiMemoryFactory::class);
        $this->apiMemoryFactory
            ->method('make')
            ->willReturn($cachedFileMemory);

        $this->deploy = $this->createStub(Deploy::class);

        $this->controller = new AdminController(
            $this->apiStorage,
            $this->apiMemoryFactory,
            $this->deploy
        );

        $this->withoutMiddleware();
    }

    // ──────────────────────────────────────────────
    //  postApi
    // ──────────────────────────────────────────────

    public function testPostApiHappyPath(): void
    {
        $deploy = $this->getMockBuilder(Deploy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['instanceExecute', 'instanceCleanReleases'])
            ->getMock();

        $deploy
            ->expects($this->once())
            ->method('instanceExecute')
            ->willReturn(['release987', 'api-key-test']);

        $deploy
            ->expects($this->once())
            ->method('instanceCleanReleases');

        $controller = new AdminController(
            $this->apiStorage,
            $this->apiMemoryFactory,
            $deploy
        );

        $request = $this->buildRequestWithFile();
        $response = $controller->postApi($request);

        $this->assertEquals(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertSame('api-key-test', $payload['key']);
        $this->assertSame('release987', $payload['release']);

        // Api should be stored in real memory
        $found = $this->apiStorage->find('myorg', 'production', 'api-key-test');
        $this->assertNotNull($found);
        $this->assertSame('release987', $found['release']);
    }

    public function testPostApiThrowsExceptionWithKnownCode(): void
    {
        $deploy = $this->getMockBuilder(Deploy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['instanceExecute'])
            ->getMock();

        $deploy
            ->expects($this->once())
            ->method('instanceExecute')
            ->willThrowException(new Exception('deploy failed', 400));

        $controller = new AdminController(
            $this->apiStorage,
            $this->apiMemoryFactory,
            $deploy
        );

        $request = $this->buildRequestWithFile();
        $response = $controller->postApi($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertSame('deploy failed', $response->getContent());
    }

    public function testPostApiThrowsExceptionWithUnknownCodeFallsBackTo500(): void
    {
        $deploy = $this->getMockBuilder(Deploy::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['instanceExecute'])
            ->getMock();

        $deploy
            ->expects($this->once())
            ->method('instanceExecute')
            ->willThrowException(new Exception('server error', 0));

        $controller = new AdminController(
            $this->apiStorage,
            $this->apiMemoryFactory,
            $deploy
        );

        $request = $this->buildRequestWithFile();
        $response = $controller->postApi($request);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertSame('server error', $response->getContent());
    }

    // ──────────────────────────────────────────────
    //  getApi
    // ──────────────────────────────────────────────

    public function testGetApiReturnsApiWhenFound(): void
    {
        $this->apiStorage->set('myorg', 'production', 'my-key', 'release1', true, false);

        $request = $this->buildRequest(['key' => 'my-key']);
        $response = $this->controller->getApi($request);

        $this->assertEquals(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertSame('my-key', $payload['key']);
        $this->assertSame('release1', $payload['release']);
        $this->assertTrue($payload['active']);
        $this->assertFalse($payload['public']);
    }

    public function testGetApiReturns404WhenNotFound(): void
    {
        $request = $this->buildRequest(['key' => 'nonexistent']);
        $response = $this->controller->getApi($request);

        $this->assertEquals(404, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertSame('api not found', $payload['message']);
    }

    // ──────────────────────────────────────────────
    //  getApis
    // ──────────────────────────────────────────────

    public function testGetApisReturnsAllApisForOrgAndEnv(): void
    {
        $this->apiStorage->set('myorg', 'production', 'key-one', 'r1', true, false);
        $this->apiStorage->set('myorg', 'production', 'key-two', 'r2', false, true);

        $request = $this->buildRequest();
        $response = $this->controller->getApis($request);

        $this->assertEquals(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertCount(2, $payload);
    }

    public function testGetApisReturnsEmptyWhenNoneExist(): void
    {
        $request = $this->buildRequest();
        $response = $this->controller->getApis($request);

        $this->assertEquals(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertSame([], $payload);
    }

    // ──────────────────────────────────────────────
    //  putApi
    // ──────────────────────────────────────────────

    public function testPutApiUpdatesApiSuccessfully(): void
    {
        $this->apiStorage->set('myorg', 'production', 'my-key', 'release1', true, false);

        $request = $this->buildRequest([
            'key' => 'my-key',
            'active' => true,
            'release' => 'release1',
            'public' => true,
        ]);
        $response = $this->controller->putApi($request);

        $this->assertEquals(204, $response->getStatusCode());

        // Verify the update
        $api = $this->apiStorage->find('myorg', 'production', 'my-key');
        $this->assertTrue($api['public']);
    }

    public function testPutApiReturns400WhenActiveIsNotBool(): void
    {
        $request = $this->buildRequest([
            'key' => 'my-key',
            'active' => 'not-bool',
            'release' => 'release1',
            'public' => true,
        ]);
        $response = $this->controller->putApi($request);

        $this->assertEquals(400, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertStringContainsString('active', $payload['message']);
    }

    public function testPutApiReturns400WhenReleaseIsNotString(): void
    {
        $request = $this->buildRequest([
            'key' => 'my-key',
            'active' => true,
            'release' => 123,
            'public' => true,
        ]);
        $response = $this->controller->putApi($request);

        $this->assertEquals(400, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertStringContainsString('release', $payload['message']);
    }

    public function testPutApiReturns400WhenPublicIsNotBool(): void
    {
        $request = $this->buildRequest([
            'key' => 'my-key',
            'active' => true,
            'release' => 'release1',
            'public' => 'not-bool',
        ]);
        $response = $this->controller->putApi($request);

        $this->assertEquals(400, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertStringContainsString('public', $payload['message']);
    }

    public function testPutApiReturns404WhenKeyNotFound(): void
    {
        $request = $this->buildRequest([
            'key' => 'nonexistent',
            'active' => true,
            'release' => 'release1',
            'public' => true,
        ]);
        $response = $this->controller->putApi($request);

        $this->assertEquals(404, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertSame('key not found', $payload['message']);
    }

    public function testPutApiReturns404WhenReleaseNotFound(): void
    {
        $this->apiStorage->set('myorg', 'production', 'my-key', 'release1', true, false);
        // Create release history by switching releases
        $this->apiStorage->set('myorg', 'production', 'my-key', 'release2', true, false);
        // 'release3' is neither current nor in releases history
        $this->apiStorage->set('myorg', 'production', 'my-key', 'release2', true, false);

        $request = $this->buildRequest([
            'key' => 'my-key',
            'active' => true,
            'release' => 'release3',
            'public' => true,
        ]);
        $response = $this->controller->putApi($request);

        $this->assertEquals(404, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertSame('release not found', $payload['message']);
    }

    public function testPutApiReturns204WhenReleaseChangesToPreviousRelease(): void
    {
        $this->apiStorage->set('myorg', 'production', 'my-key', 'release1', true, false);
        $this->apiStorage->set('myorg', 'production', 'my-key', 'release2', true, false);

        // Switch back to release1 (which is in release history)
        $request = $this->buildRequest([
            'key' => 'my-key',
            'active' => true,
            'release' => 'release1',
            'public' => false,
        ]);
        $response = $this->controller->putApi($request);

        $this->assertEquals(204, $response->getStatusCode());
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    private function buildRequest(array $extra = []): Request
    {
        $defaults = [
            'key' => 'default-key',
            'active' => true,
            'release' => 'release1',
            'public' => false,
        ];

        $merged = array_merge($defaults, $extra);

        $request = Request::create('/hefesto/api', 'GET', $merged);
        $request->setUserResolver(fn () => null);
        $request->setRouteResolver(fn () => null);

        $request->virtualHost = [
            'ORG' => 'myorg',
            'ENV' => 'production',
        ];

        return $request;
    }

    private function buildRequestWithFile(): Request
    {
        $file = UploadedFile::fake()->create('api.tar.gz', 100);

        $request = Request::create('/hefesto/api', 'POST', [], [], [
            'file' => $file,
        ]);
        $request->virtualHost = [
            'ORG' => 'myorg',
            'ENV' => 'production',
        ];

        return $request;
    }
}