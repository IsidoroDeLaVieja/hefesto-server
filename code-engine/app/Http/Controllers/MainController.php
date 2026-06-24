<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Adapters\ApiMemoryFactory;
use App\Core\Alias;
use App\Core\Api;
use App\Core\DefaultDirectiveFactory;
use App\Core\DirectiveFactory;
use App\Core\Engine;
use App\Core\EngineDispatcher;
use App\Core\ExecutionTimeMemory;
use App\Core\FilesystemMapRepository;
use App\Core\Groups;
use App\Core\Message;
use App\Core\PathInterpreter;
use App\Core\State;
use Illuminate\Http\Request;

class MainController extends Controller
{
    public function __construct(
        private readonly PathInterpreter $pathInterpreter,
        private readonly EngineDispatcher $engineDispatcher,
        private readonly ApiMemoryFactory $apiMemoryFactory,
        private readonly DirectiveFactory $directiveFactory = new DefaultDirectiveFactory(),
    ) {}

    public function execute(Request $request)
    {
        $path = $this->resolvePath($request);
        $apiCode = $this->getApi($request->api['release']);

        $pathInfo = $this->pathInterpreter->execute(
            $request->method(),
            $path,
            $apiCode->actions(),
        );

        if ($pathInfo === null) {
            return response('', 404);
        }

        $message = new Message(
            $request->method(),
            $path,
            $this->getHeaders($request),
            $request->getContent(),
            $request->query(),
            $pathInfo['PATH_PARAMS'],
            200,
        );

        $engine = new Engine(
            new State(
                $message,
                $this->buildStateConfig($request, $pathInfo),
                new Groups(),
                new ExecutionTimeMemory(),
                new FilesystemMapRepository(
                    config('app.CODE_PATH') . $request->api['release'] . '/'
                ),
            ),
            $apiCode->getDirectives(
                $pathInfo['DEFINITION_VERB'],
                $pathInfo['DEFINITION_PATH'],
            ),
            $this->engineDispatcher,
            $this->directiveFactory,
        );

        $engine->state()->setAlias(new Alias($engine->state()));
        $returnedMessage = $engine->execute();

        $request->engine = $engine;

        $this->setCookies($engine);

        return response(
            $returnedMessage->getBody(),
            $returnedMessage->getStatus(),
        )->withHeaders(
            $returnedMessage->getHeaders(),
        );
    }

    private function getApi(string $release): Api
    {
        $class = config('app.API_NAMESPACE') . $release . '\\' . $release;

        return new $class();
    }

    private function resolvePath(Request $request): string
    {
        $virtualHostPath = $request->virtualHost['TYPE'] === 'ADMIN'
            ? ''
            : $request->virtualHost['PATH'];

        $requestPath = parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $requestPath = ($requestPath !== null && $requestPath !== '/') ? $requestPath : '';

        $pathWithKey = trim($virtualHostPath . $requestPath);
        $segments = explode('/', $pathWithKey);

        // Remove the first two segments (org and env)
        array_shift($segments);
        array_shift($segments);

        return '/' . implode('/', $segments);
    }

    private function buildStateConfig(Request $request, array $pathInfo): array
    {
        return [
            'organization' => $request->virtualHost['ORG'],
            'environment' => $request->virtualHost['ENV'],
            'apiMemory' => $this->apiMemoryFactory->make(
                $request->virtualHost['ORG'],
                $request->virtualHost['ENV'],
                $request->api['key'],
            ),
            'keyApi' => $request->api['key'],
            'codePath' => config('app.CODE_PATH') . $request->api['release'] . '/',
            'storagePath' => config('app.STORAGE_PATH')
                . $request->virtualHost['ORG'] . '/'
                . $request->virtualHost['ENV'] . '/'
                . $request->api['key'] . '/',
            'localhost' => config('app.LOCALHOST'),
            'definitionVerb' => $pathInfo['DEFINITION_VERB'],
            'definitionPath' => $pathInfo['DEFINITION_PATH'],
        ];
    }

    private function getHeaders(Request $request): array
    {
        $result = [];

        foreach ($request->headers->all() as $name => $values) {
            $result[$name] = $values[0];
        }

        return $result;
    }

    private function setCookies(Engine $engine): void
    {
        $cookies = $engine->state()->memory()->get(
            config('app.COOKIES_KEY'),
        );

        if ($cookies === null) {
            return;
        }

        $domain = $engine->state()->memory()->get(
            config('app.COOKIES_DOMAIN_KEY'),
        );

        foreach ($cookies->toArray() as $cookie) {
            $cookie = array_change_key_case($cookie);

            setcookie(
                $cookie['name'],
                rawurldecode($cookie['value']),
                $cookie['expires'] ?? 0,
                $cookie['path'],
                $domain ?? $cookie['domain'],
                $cookie['secure'],
                $cookie['httponly'],
            );
        }
    }
}