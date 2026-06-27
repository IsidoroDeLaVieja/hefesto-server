<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

use App\Adapters\ApiMemoryFactory;
use App\Core\EngineDispatcher;
use App\Core\PathInterpreter;
use App\Core\DirectiveFactory;
use App\Core\Directive;
use App\Core\Message;
use App\Core\State;
use App\Core\Engine;
use App\Core\Groups;
use App\Http\Controllers\MainController;
use Illuminate\Http\Request;
use SplDoublyLinkedList;

class MainControllerTest extends TestCase
{
    private PathInterpreter $pathInterpreter;
    private EngineDispatcher $engineDispatcher;
    private ApiMemoryFactory $apiMemoryFactory;
    private DirectiveFactory $directiveFactory;
    private MainController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pathInterpreter = $this->createMock(PathInterpreter::class);
        $this->engineDispatcher = $this->createStub(EngineDispatcher::class);
        $this->apiMemoryFactory = $this->createStub(ApiMemoryFactory::class);
        $this->directiveFactory = $this->createStub(DirectiveFactory::class);

        $this->controller = new MainController(
            $this->pathInterpreter,
            $this->engineDispatcher,
            $this->apiMemoryFactory,
            $this->directiveFactory
        );

        $this->withoutMiddleware();
    }

    // ──────────────────────────────────────────────
    //  execute – Happy Path
    // ──────────────────────────────────────────────

    public function testExecuteReturns200OnHappyPath(): void
    {
        $this->configureConfig();

        $this->pathInterpreter
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'DEFINITION_VERB' => 'GET',
                'DEFINITION_PATH' => '/test-path',
                'PATH_PARAMS' => [],
            ]);

        $mockDirective = $this->createStub(Directive::class);
        $this->directiveFactory
            ->method('make')
            ->willReturn($mockDirective);

        $request = $this->buildRequest([
            'TYPE' => 'PUBLIC',
            'PATH' => '/myorg/mykey',
        ], 'GET', '/test-path', 'TestRelease');

        $response = $this->controller->execute($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    // ──────────────────────────────────────────────
    //  execute – 404 when path not found
    // ──────────────────────────────────────────────

    public function testExecuteReturns404WhenPathNotFound(): void
    {
        $this->configureConfig();

        $this->pathInterpreter
            ->expects($this->once())
            ->method('execute')
            ->willReturn(null);

        $request = $this->buildRequest([
            'TYPE' => 'PUBLIC',
            'PATH' => '/myorg/mykey',
        ], 'GET', '/nonexistent', 'TestRelease');

        $response = $this->controller->execute($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    // ──────────────────────────────────────────────
    //  execute – ADMIN virtual host type
    // ──────────────────────────────────────────────

    public function testExecuteWithAdminVirtualHostType(): void
    {
        $this->configureConfig();

        $this->pathInterpreter
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'DEFINITION_VERB' => 'GET',
                'DEFINITION_PATH' => '/test-path',
                'PATH_PARAMS' => [],
            ]);

        $mockDirective = $this->createStub(Directive::class);
        $this->directiveFactory
            ->method('make')
            ->willReturn($mockDirective);

        $request = $this->buildRequest([
            'TYPE' => 'ADMIN',
            'PATH' => '',
        ], 'GET', '/test-path', 'TestRelease');

        $response = $this->controller->execute($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    // ──────────────────────────────────────────────
    //  execute – with path params
    // ──────────────────────────────────────────────

    public function testExecuteWithPathParams(): void
    {
        $this->configureConfig();

        $this->pathInterpreter
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'DEFINITION_VERB' => 'GET',
                'DEFINITION_PATH' => '/test-with-param/{id}',
                'PATH_PARAMS' => ['id' => '42'],
            ]);

        $mockDirective = $this->createStub(Directive::class);
        $this->directiveFactory
            ->method('make')
            ->willReturn($mockDirective);

        $request = $this->buildRequest([
            'TYPE' => 'PUBLIC',
            'PATH' => '/myorg/mykey',
        ], 'GET', '/test-with-param/42', 'TestRelease');

        $response = $this->controller->execute($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    // ──────────────────────────────────────────────
    //  execute – default DirectiveFactory when null
    // ──────────────────────────────────────────────

    public function testExecuteUsesDefaultDirectiveFactoryWhenNull(): void
    {
        $this->configureConfig();

        $this->pathInterpreter
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'DEFINITION_VERB' => 'GET',
                'DEFINITION_PATH' => '/test-path',
                'PATH_PARAMS' => [],
            ]);

        $controller = new MainController(
            $this->pathInterpreter,
            $this->engineDispatcher,
            $this->apiMemoryFactory
        );

        $request = $this->buildRequest([
            'TYPE' => 'PUBLIC',
            'PATH' => '/myorg/mykey',
        ], 'GET', '/test-path', 'TestRelease');

        $response = $controller->execute($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    // ──────────────────────────────────────────────
    //  execute – root request returns 404 (empty path)
    // ──────────────────────────────────────────────

    public function testExecuteReturns404ForRootRequest(): void
    {
        $this->configureConfig();

        $this->pathInterpreter
            ->expects($this->once())
            ->method('execute')
            ->willReturn(null);

        $request = $this->buildRequest([
            'TYPE' => 'PUBLIC',
            'PATH' => '/myorg/mykey',
        ], 'GET', '/', 'TestRelease');

        $response = $this->controller->execute($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    private function configureConfig(): void
    {
        config([
            'app.API_NAMESPACE' => 'Tests\\Fixture\\Release\\',
            'app.CODE_PATH' => '/tmp/hefesto-test/',
            'app.STORAGE_PATH' => '/tmp/hefesto-test/',
            'app.LOCALHOST' => 'localhost',
        ]);
    }

    private function buildRequest(
        array $virtualHost,
        string $method,
        string $path,
        string $release
    ): Request {
        $_SERVER['REQUEST_URI'] = $path;

        $request = Request::create($path, $method);
        $request->setUserResolver(fn () => null);
        $request->setRouteResolver(fn () => null);
        $request->headers->set('HOST', 'localhost');

        $request->virtualHost = [
            'TYPE' => $virtualHost['TYPE'],
            'PATH' => $virtualHost['PATH'],
            'ORG' => 'myorg',
            'ENV' => 'production',
        ];

        $request->api = [
            'key' => 'mykey',
            'release' => $release,
        ];

        return $request;
    }
}