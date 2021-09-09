<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ExecuteEngineAfter
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate($request, $response)
    {
        if (isset($request->engine)) {
            $request->engine->executeAfter();
        }
    }
}
