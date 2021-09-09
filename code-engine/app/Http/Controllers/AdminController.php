<?php

declare(strict_types=1);

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Adapters\Deploy;
use App\Adapters\ApiMemoryFactory;
use App\Core\ApiStorage;
use Throwable;
use Exception;

class AdminController extends Controller
{
    private $apiStorage;
    private $apiMemoryFactory;

    public function __construct(
        ApiStorage $apiStorage,
        ApiMemoryFactory $apiMemoryFactory
    ) {
        $this->apiStorage = $apiStorage;
        $this->apiMemoryFactory = $apiMemoryFactory;
    }

    public function postApi(Request $request) 
    {
        try {
            list($release,$key) = Deploy::execute(                
                $request->virtualHost['ORG'],
                $request->virtualHost['ENV'],
                $request
            );
            $api = $this->apiStorage->find(
                $request->virtualHost['ORG'],
                $request->virtualHost['ENV'],
                $key
            );
            $cleanedReleases = $this->apiStorage->set(
                $request->virtualHost['ORG'],
                $request->virtualHost['ENV'],
                $key,
                $release,
                true,
                $api ? $api['public'] : false
            );
            $this->clearApiMemory(                
                $request->virtualHost['ORG'],
                $request->virtualHost['ENV'],
                $key
            );
            Deploy::cleanReleases($cleanedReleases);
            return response()->json([
                'key' => $key,
                'release' => $release
            ]);
        } catch ( Throwable $e ) {
            return response($e->getMessage(), $this->code($e));
        }
    }

    public function getApi(Request $request)
    {
        try {
            $api = $this->apiStorage->find(
                $request->virtualHost['ORG'],
                $request->virtualHost['ENV'],
                $request->key
            );
            if (!$api) {
                throw new Exception('api not found', 404);
            }
            return response()->json($api);
        } catch ( Throwable $e ) {
            return $this->handleException($e);
        }
    }

    public function getApis(Request $request)
    {
        try {
            $apis = $this->apiStorage->findAll(
                $request->virtualHost['ORG'],
                $request->virtualHost['ENV']
            );
            return response()->json($apis);
        } catch ( Throwable $e ) {
            return $this->handleException($e);
        }
    }

    public function putApi(Request $request)
    {
        try {
            $active = $request->input('active');
            $release = $request->input('release');
            $public = $request->input('public');
            
            if (!is_bool($active)) {
                throw new Exception('active is mandatory, it should be bool', 400);
            }
            if (!is_string($release)) {
                throw new Exception('release is mandatory, it should be string', 400);
            }
            if (!is_bool($public)) {
                throw new Exception('public is mandatory, it should be bool', 400);
            }

            $api = $this->apiStorage->find(
                $request->virtualHost['ORG'],
                $request->virtualHost['ENV'],
                $request->key
            );
            if (!$api) {
                throw new Exception('key not found', 404);
            }
            if ($release !== $api['release'] 
                && !in_array($release,$api['releases'])) {
                throw new Exception('release not found', 404);
            }
            $this->apiStorage->set(
                $request->virtualHost['ORG'],
                $request->virtualHost['ENV'],
                $request->key,
                $release,
                $active,
                $public
            );
            $this->clearApiMemory(
                $request->virtualHost['ORG'],
                $request->virtualHost['ENV'],
                $request->key
            );
            return response(null, 204);
        } catch ( Throwable $e ) {
            return $this->handleException($e);
        }
    }

    private function handleException(Throwable $e)
    {
        return response()->json([
            'message' => $e->getMessage()
            ], 
            $this->code($e)
        );
    }

    private function code(Throwable $e) : int 
    {
        $code = (int)$e->getCode();
        return in_array($code, [ 400 , 401 , 403 ,404 ] ) ? $code : 500;
    }

    private function clearApiMemory(string $org,string $env, string $key) : void 
    {
        $apiMemory = $this->apiMemoryFactory->make($org,$env,$key);
        $apiMemory->set('api-maps',null);
    }
}