<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales.view') || $user->can('sales.create');
    }

    public function view(User $user, Sale $sale): bool
    {
        if ($user->can('reports.view_all') || $user->hasRole('Administrador') || $user->can('sales.charge')) {
            return $user->can('sales.view') || $user->can('sales.charge');
        }

        return $user->can('sales.view') && $sale->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('sales.create');
    }

    public function charge(User $user): bool
    {
        return $user->can('sales.charge');
    }

    public function void(User $user): bool
    {
        return $user->can('sales.void');
    }
}
