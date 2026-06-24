<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\VirtualHostAccessAdmin;
use App\Core\VirtualHostStorage;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckPublicHost
{
    public function __construct(
        private readonly VirtualHostStorage $virtualHostStorage,
        private readonly VirtualHostAccessAdmin $virtualHostAccessAdmin,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        try {
            $virtualHost = $this->virtualHostStorage->getPublic(
                $_SERVER['SERVER_NAME'],
            );

            if ($virtualHost === null) {
                if (config('app.ADMIN_CLOSED') === true) {
                    throw new Exception('Admin is closed');
                }

                $virtualHost = $this->virtualHostAccessAdmin->get(
                    $_SERVER['SERVER_NAME'],
                    $request->header('public-host'),
                    $request->header('public-host-key') ?? '',
                    true,
                );
            }

            $request->virtualHost = $virtualHost;
        } catch (Throwable $e) {
            Log::alert('CheckPublicHost ' . $request->ip() . ' ' . $e->getMessage());

            return response('', 404);
        }

        return $next($request);
    }
}