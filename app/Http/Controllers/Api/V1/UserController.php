<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users)
    {
    }

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $page = $this->users->paginate();

        return ApiResponse::success([
            'items' => UserResource::collection($page->getCollection())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ], 'Usuarios');
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = $this->users->create($request->validated());

        return ApiResponse::success(new UserResource($user), 'Usuario creado', 201);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return ApiResponse::success(new UserResource($user->load('roles')), 'Usuario');
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $updated = $this->users->update($user, $request->validated());

        return ApiResponse::success(new UserResource($updated), 'Usuario actualizado');
    }

    public function activate(User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $updated = $this->users->setActive($user, true);

        return ApiResponse::success(new UserResource($updated), 'Usuario activado');
    }

    public function deactivate(User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $updated = $this->users->setActive($user, false);

        return ApiResponse::success(new UserResource($updated), 'Usuario desactivado');
    }

    public function roles(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $roles = Role::query()->orderBy('name')->pluck('name');

        return ApiResponse::success($roles, 'Roles');
    }
}
