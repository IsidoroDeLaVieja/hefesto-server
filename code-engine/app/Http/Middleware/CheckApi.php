<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Core\ApiStorage;
use Throwable;
use Exception;

class CheckApi
{
    private $apiStorage;

    public function __construct(
        ApiStorage $apiStorage
    ) {
        $this->apiStorage = $apiStorage;
    }

    public function handle(
        Request $request, 
        Closure $next
    ) {
        try {
            $api = $this->getApi($request);
            $isAdmin = $request->virtualHost['TYPE'] === 'ADMIN';
            if (        !$api 
                    ||  !$api['active']
                    ||  (!$isAdmin && !$api['public'] ) 
            ) {
                throw new Exception('Api not found');
            }
            $request->api = $api;
        } catch (Throwable $e) {
            Log::alert('CheckApi '.$request->ip().' '.$e->getMessage());
            return response('',404);
        }
        return $next($request);
    }

    private function getApi(Request $request) : ?array
    {
        return $this->apiStorage->find(
            $request->virtualHost['ORG'],
            $request->virtualHost['ENV'],
            $this->getKey($request)
        );
    }

    private function getKey(Request $request) : string 
    {
        $path = $request->virtualHost['PATH'] 
            ? $request->virtualHost['PATH'] 
            : '/'.$request->path();
        $segments = explode('/',$path);
        if (isset($segments[1])) {
            return $segments[1];
        }
        throw new Exception('Key not found');
    }
}