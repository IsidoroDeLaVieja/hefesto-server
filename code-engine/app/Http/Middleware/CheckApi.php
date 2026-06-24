<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\ApiStorage;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckApi
{
    public function __construct(
        private readonly ApiStorage $apiStorage
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        try {
            $isAdmin = $request->virtualHost['TYPE'] === 'ADMIN';
            $api = $this->apiStorage->find(
                $request->virtualHost['ORG'],
                $request->virtualHost['ENV'],
                $this->resolveKey($request, $isAdmin),
            );

            if ($api === null || !$api['active'] || (!$isAdmin && !$api['public'])) {
                throw new Exception('Api not found');
            }

            $request->api = $api;
        } catch (Throwable $e) {
            Log::alert('CheckApi ' . $request->ip() . ' ' . $e->getMessage());

            return response('', 404);
        }

        return $next($request);
    }

    private function resolveKey(Request $request, bool $isAdmin): string
    {
        $basePath = (!$isAdmin && $request->virtualHost['PATH'])
            ? $request->virtualHost['PATH']
            : '/' . $request->path();

        $segments = explode('/', $basePath);

        if (isset($segments[1]) && $segments[1] !== '') {
            return $segments[1];
        }

        throw new Exception('Key not found');
    }
}