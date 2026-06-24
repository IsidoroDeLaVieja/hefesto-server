<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /** @var array<string, list<class-string>> */
    protected $middlewareGroups = [
        'api' => [],
        'public' => [
            \App\Http\Middleware\CheckPublicHost::class,
            \App\Http\Middleware\ExecuteEngineAfter::class,
            \App\Http\Middleware\CheckApi::class,
        ],
    ];

    // ALERT: when adding a middleware, it must also be added to middlewarePriority
    /** @var array<string, class-string> */
    protected $routeMiddleware = [
        'check.admin.host' => \App\Http\Middleware\CheckAdminHost::class,
        'check.public.host' => \App\Http\Middleware\CheckPublicHost::class,
        'check.api' => \App\Http\Middleware\CheckApi::class,
        'execute.engine.after' => \App\Http\Middleware\ExecuteEngineAfter::class,
    ];

    /** @var list<class-string> */
    protected $middlewarePriority = [
        \App\Http\Middleware\CheckAdminHost::class,
        \App\Http\Middleware\CheckPublicHost::class,
        \App\Http\Middleware\CheckApi::class,
        \App\Http\Middleware\ExecuteEngineAfter::class,
    ];
}