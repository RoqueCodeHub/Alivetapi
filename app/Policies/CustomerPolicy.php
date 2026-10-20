<?php

namespace App\Policies;

use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('customers.view') || $user->can('sales.create');
    }

    public function create(User $user): bool
    {
        return $user->can('customers.create') || $user->can('sales.create');
    }

    public function update(User $user): bool
    {
        return $user->can('customers.update') || $user->can('sales.create');
    }
}
