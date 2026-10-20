<?php

namespace App\Policies;

use App\Models\User;

class ClinicPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clinic.view');
    }

    public function manage(User $user): bool
    {
        return $user->can('clinic.manage');
    }

    public function attend(User $user): bool
    {
        return $user->can('clinic.attend');
    }

    public function charge(User $user): bool
    {
        return $user->can('clinic.charge') || $user->can('sales.create');
    }
}
