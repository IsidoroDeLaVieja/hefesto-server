<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ExecuteEngineAfter
{
    public function handle(Request $request, Closure $next): mixed
    {
        return $next($request);
    }

    public function terminate(mixed $request, mixed $response): void
    {
        if (isset($request->engine)) {
            $request->engine->executeAfter();
        }
    }
}