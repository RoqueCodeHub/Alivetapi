<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Batch;
use App\Models\CashSession;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Invoice;
use App\Models\Marca;
use App\Models\Pet;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Models\Variante;
use App\Models\VetService;
use App\Observers\AuditObserver;
use App\Services\Invoicing\FakePseGateway;
use App\Services\Invoicing\HttpPseGateway;
use App\Services\Invoicing\PseGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PseGateway::class, function ($app) {
            return $app['config']->get('invoicing.driver') === 'http'
                ? $app->make(HttpPseGateway::class)
                : $app->make(FakePseGateway::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (class_exists(\Spatie\Permission\Middleware\RoleMiddleware::class)) {
            $router = $this->app->make('router');
            $router->aliasMiddleware('role', \Spatie\Permission\Middleware\RoleMiddleware::class);
            $router->aliasMiddleware('permission', \Spatie\Permission\Middleware\PermissionMiddleware::class);
            $router->aliasMiddleware('role_or_permission', \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class);
        }

        $observe = [
            User::class,
            Categoria::class,
            Marca::class,
            Producto::class,
            Variante::class,
            Cliente::class,
            Pet::class,
            Proveedor::class,
            Purchase::class,
            Sale::class,
            Invoice::class,
            Appointment::class,
            VetService::class,
            Batch::class,
            CashSession::class,
        ];
        foreach ($observe as $model) {
            $model::observe(AuditObserver::class);
        }
    }
}
