<?php

namespace App\Services;

use App\Models\User;
use App\Support\Counts;
use App\Support\PageSize;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function paginate(?string $search = null): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->paginate(PageSize::resolve());
    }

    public function summary(): array
    {
        $since = now()->subDays(30)->toDateTimeString();
        $row = User::query()->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as activos,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactivos,
            SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as recientes
        ', [$since])->first();

        return Counts::ints($row, ['total', 'activos', 'inactivos', 'recientes']);
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
