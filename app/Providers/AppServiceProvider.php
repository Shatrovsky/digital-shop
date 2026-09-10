<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\HttpSupplierGateway;
use App\Services\SupplierGatewayInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SupplierGatewayInterface::class, HttpSupplierGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
