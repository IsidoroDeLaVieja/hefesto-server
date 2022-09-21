<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Core\VirtualHostAccessAdmin;
use App\Core\VirtualHostStorage;
use Exception;
use Throwable;

class CheckPublicHost
{
    private $virtualHostStorage;
    private $virtualHostAccessAdmin;

    public function __construct(
        VirtualHostStorage $virtualHostStorage,
        VirtualHostAccessAdmin $virtualHostAccessAdmin
    ) {
        $this->virtualHostAccessAdmin = $virtualHostAccessAdmin;
        $this->virtualHostStorage = $virtualHostStorage;
    }

    public function handle(
        Request $request, 
        Closure $next
    ) {
        try {
            $virtualHost = $this->virtualHostStorage->getPublic(
                $_SERVER['SERVER_NAME']
            );
            if ( ! $virtualHost ) {
                $isLocal = $this->isLocalCall($request->ip());
                if (!$isLocal && config('app.ADMIN_CLOSED') === true) {
                    throw new Exception('Admin is closed');
                }
                $virtualHost = $this->virtualHostAccessAdmin->get(
                    $_SERVER['SERVER_NAME'],
                    $request->header('public-host'),
                    $request->header('public-host-key') 
                        ? $request->header('public-host-key') 
                        : '',
                        !$isLocal
                );
            }
            $request->virtualHost = $virtualHost;
        } catch (Throwable $e) {
            Log::alert('CheckPublicHost '.$request->ip().' '.$e->getMessage());
            return response('',404);
        }
        return $next($request);
    }

    private function isLocalCall(string $ip) : bool 
    {
        return $_SERVER['SERVER_NAME'] === 'localhost' && !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE
        );
    }
}