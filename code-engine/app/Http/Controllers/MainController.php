<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Adapters\ApiMemoryFactory;
use App\Core\Engine;
use App\Core\State;
use App\Core\EngineDispatcher;
use App\Core\Message;
use App\Core\PathInterpreter;
use App\Core\Api;

class MainController extends Controller
{
    private $pathInterpreter;
    private $engineDispatcher;
    private $apiMemoryFactory;

    public function __construct(
        PathInterpreter $pathInterpreter,
        EngineDispatcher $engineDispatcher,
        ApiMemoryFactory $apiMemoryFactory
    ){
        $this->pathInterpreter = $pathInterpreter;
        $this->engineDispatcher = $engineDispatcher;
        $this->apiMemoryFactory = $apiMemoryFactory;
    }

    public function execute(Request $request) 
    {
        $path = $this->getPath($request->virtualHost['PATH'],$request->path());
        $apiCode = $this->getApi($request->api['release']);
        
        $pathInfo = $this->pathInterpreter->execute($request->method(),$path,$apiCode->actions());
        if (is_null($pathInfo)) {
            return response('',404);
        }

        $message = new Message(
            $request->method(),
            $path,
            $this->getHeaders($request),
            $request->getContent(),
            $request->query(),
            $pathInfo['PATH_PARAMS'],
            200
        );

        $state = new State($message,$this->getStateConfig(
            $request->virtualHost['ORG'],
            $request->virtualHost['ENV'],
            $request->api['key'],
            $request->api['release'],
            $pathInfo['DEFINITION_VERB'],
            $pathInfo['DEFINITION_PATH']
        ));

        $engine = new Engine(
            $state,
            $apiCode->getDirectives(
                $pathInfo['DEFINITION_VERB'],
                $pathInfo['DEFINITION_PATH']
            ),
            $this->engineDispatcher
        );

        $message = $engine->execute();
        $request->engine = $engine;

        $this->setCookies($engine);
        return response(
            $message->getBody(), 
            $message->getStatus()
        )->withHeaders(
            $message->getHeaders()
        );
    }

    private function getApi(string $release) : Api 
    {
        $apiCode = config('app.API_NAMESPACE').$release.'\\'.$release;
        return new $apiCode();
    }

    private function getPath(string $virtualHostPath, string $requestPath) : string 
    {
        $requestPath = $requestPath && $requestPath !== '/' ? '/'.$requestPath : '';
        $pathWithKey = trim($virtualHostPath . $requestPath);
        $segments = explode('/',$pathWithKey);
        if (isset($segments[0])) {
            unset($segments[0]);
        }
        if (isset($segments[1])) {
            unset($segments[1]);
        }
        return '/'.implode('/',$segments);
    }

    private function getStateConfig(
        string $org,
        string $env,
        string $key,
        string $release,
        string $definitionVerb,
        string $definitionPath
    ) : array {
        return [
            'organization' => $org,
            'environment' => $env,
            'apiMemory' => $this->apiMemoryFactory->make($org,$env,$key),
            'keyApi' => $key,
            'codePath' => config('app.CODE_PATH').$release.'/',
            'storagePath' => config('app.STORAGE_PATH').$org.'/'.$env.'/'.$key.'/',
            'localhost' => config('app.LOCALHOST'),
            'definitionVerb' => $definitionVerb,
            'definitionPath' => $definitionPath
        ];
    }

    private function getHeaders( Request $request ) : array 
    {
        $headers = [];
        $allHeaders = $request->headers->all();
        foreach ($allHeaders as $i => $header) {
            $headers[$i] = $header[0];
        }
        return $headers;
    }

    private function setCookies(Engine $engine) : void 
    {
	    $cookies = $engine->state()->memory()->get(
            config('app.COOKIES_KEY')
        );
        if ( is_null($cookies) ) {
            return;
        }
        $domain = $engine->state()->memory()->get(
            config('app.COOKIES_DOMAIN_KEY')
        );
        $cookies = $cookies->toArray();
        foreach($cookies as $cookie) {
            $cookie = array_change_key_case($cookie);
            setcookie(
                $cookie['name'],
                rawurldecode($cookie['value']),
                is_null($cookie['expires']) ? 0 : $cookie['expires'],
                $cookie['path'],
                isset($domain) ? $domain : $cookie['domain'],
                $cookie['secure'],
                $cookie['httponly']
            );
        }
    }
}
