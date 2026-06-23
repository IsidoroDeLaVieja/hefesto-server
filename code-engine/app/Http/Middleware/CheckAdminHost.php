<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Core\VirtualHostAccessAdmin;
use Throwable;

class CheckAdminHost
{
    private $virtualHostAccessAdmin;

    public function __construct(
        VirtualHostAccessAdmin $virtualHostAccessAdmin
    ) {
        $this->virtualHostAccessAdmin = $virtualHostAccessAdmin;
    }

    public function handle(
        Request $request, 
        Closure $next
    ) {
        try {
            if (config('app.ADMIN_CLOSED') === true) {
                throw new \Exception('Admin is closed');
            }
            $virtualHost = $this->virtualHostAccessAdmin->get(
                $request->getHost(),
                $request->header('public-host'),
                $request->header('public-host-key'),
                true
            );
            $request->virtualHost = $virtualHost;
        } catch (Throwable $e) {
            Log::alert('CheckAdminHost '.$request->ip().' '.$e->getMessage());
            return response('',404);
        }
        return $next($request);
    }
}