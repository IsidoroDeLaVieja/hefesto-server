<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Core\VirtualHostStorage;
use App\Core\VirtualHostAccessAdmin;
use App\Core\ApiStorage;
use App\Core\EngineDispatcher;
use App\Adapters\CachedFileMemory;
use App\Adapters\LaravelSenderEngineDispatcher;

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
                new LaravelSenderEngineDispatcher()
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
