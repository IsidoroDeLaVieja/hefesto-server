<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $middlewareGroups = [ 
        'api' => [],   
        'public' => [
            \App\Http\Middleware\CheckPublicHost::class,
            \App\Http\Middleware\ExecuteEngineAfter::class,
            \App\Http\Middleware\CheckApi::class,
        ],
    ];

    //**ALERT: if add a middleware is mandatory add middlewarePriority */
    protected $routeMiddleware = [
        'check.admin.host' => \App\Http\Middleware\CheckAdminHost::class,
        'check.public.host' => \App\Http\Middleware\CheckPublicHost::class,
        'check.api' => \App\Http\Middleware\CheckApi::class,
        'execute.engine.after' => \App\Http\Middleware\ExecuteEngineAfter::class
    ];
    //**ALERT: if add a middleware is mandatory add middlewarePriority */

    //**ALERT: if add a middleware is mandatory add middlewarePriority */
    protected $middlewarePriority = [
        \App\Http\Middleware\CheckAdminHost::class,
        \App\Http\Middleware\CheckPublicHost::class,
        \App\Http\Middleware\CheckApi::class,
        \App\Http\Middleware\ExecuteEngineAfter::class
    ];
    //**ALERT: if add a middleware is mandatory add middlewarePriority */
}
