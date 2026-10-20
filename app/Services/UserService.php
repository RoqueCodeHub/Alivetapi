<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function paginate(): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->orderBy('name')
            ->paginate(15);
    }

    public function create(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => $data['is_active'] ?? true,
        ]);

        $user->syncRoles($data['roles']);

        return $user->load('roles');
    }

    public function update(User $user, array $data): User
    {
        $payload = [];

        if (isset($data['name'])) {
            $payload['name'] = $data['name'];
        }
        if (isset($data['email'])) {
            $payload['email'] = $data['email'];
        }
        if (array_key_exists('is_active', $data)) {
            $payload['is_active'] = $data['is_active'];
        }
        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        if ($payload !== []) {
            $user->update($payload);
        }

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        return $user->fresh()->load('roles');
    }

    public function setActive(User $user, bool $active): User
    {
        $user->update(['is_active' => $active]);

        if (! $active) {
            $user->tokens()->delete();
        }

        return $user->fresh()->load('roles');
    }
}
