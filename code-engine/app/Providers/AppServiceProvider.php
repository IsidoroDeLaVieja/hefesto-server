<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Core\VirtualHostStorage;
use App\Core\VirtualHostAccessAdmin;
use App\Core\ApiStorage;
use App\Core\EngineDispatcher;
use App\Core\Queue;
use App\Adapters\CachedFileMemory;
use App\Adapters\LaravelSenderEngineDispatcher;
use App\Adapters\PredisQueue;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton(VirtualHostStorage::class, function ($app) {
            return new VirtualHostStorage(
                new CachedFileMemory('host')
            );
        });
        $this->app->singleton(VirtualHostAccessAdmin::class, function ($app) {
            return new VirtualHostAccessAdmin(
                $app->make(VirtualHostStorage::class)
            );
        });
        $this->app->singleton(ApiStorage::class, function ($app) {
            return new ApiStorage(
                new CachedFileMemory('apisdeployed')
            );
        });
        $this->app->singleton(EngineDispatcher::class, function ($app) {
            return new EngineDispatcher(
                new LaravelSenderEngineDispatcher(
                    $app->make(Queue::class)
                )
            );
        });
        $this->app->singleton(Queue::class, function ($app) {
            return new PredisQueue(
                new \Predis\Client([
                    'scheme' => 'tcp',
                    'host'   => config('database.redis.default.host'),
                    'port'   => config('database.redis.default.port'),
                    'database' => config('database.redis.default.database')
                ])
            );
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

    }
}
