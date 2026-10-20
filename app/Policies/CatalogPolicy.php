<?php

namespace App\Policies;

use App\Models\User;

class CatalogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    public function update(User $user): bool
    {
        return $user->can('products.update');
    }

    public function delete(User $user): bool
    {
        return $user->can('products.update');
    }
}
