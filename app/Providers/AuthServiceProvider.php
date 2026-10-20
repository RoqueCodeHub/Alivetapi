<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\User::class => \App\Policies\UserPolicy::class,
        \App\Models\Categoria::class => \App\Policies\CatalogPolicy::class,
        \App\Models\Marca::class => \App\Policies\CatalogPolicy::class,
        \App\Models\Producto::class => \App\Policies\CatalogPolicy::class,
        \App\Models\Variante::class => \App\Policies\CatalogPolicy::class,
        \App\Models\Proveedor::class => \App\Policies\PurchasePolicy::class,
        \App\Models\Purchase::class => \App\Policies\PurchasePolicy::class,
        \App\Models\Sale::class => \App\Policies\SalePolicy::class,
        \App\Models\Cliente::class => \App\Policies\CustomerPolicy::class,
        \App\Models\Pet::class => \App\Policies\CustomerPolicy::class,
        \App\Models\VetService::class => \App\Policies\ClinicPolicy::class,
        \App\Models\Appointment::class => \App\Policies\ClinicPolicy::class,
        \App\Models\MedicalRecord::class => \App\Policies\ClinicPolicy::class,
        \App\Models\Vaccination::class => \App\Policies\ClinicPolicy::class,
        \App\Models\Deworming::class => \App\Policies\ClinicPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
