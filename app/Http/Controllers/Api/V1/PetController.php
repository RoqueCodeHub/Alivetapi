<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\UpdatePetRequest;
use App\Http\Resources\PetResource;
use App\Models\Cliente;
use App\Models\Pet;
use App\Services\PetService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PetController extends Controller
{
    public function __construct(private readonly PetService $pets)
    {
    }

    public function show(Pet $pet): JsonResponse
    {
        $this->authorize('viewAny', Cliente::class);

        return ApiResponse::success(new PetResource($pet->load('cliente')), 'Mascota');
    }

    public function update(UpdatePetRequest $request, Pet $pet): JsonResponse
    {
        $this->authorize('update', Cliente::class);

        $updated = $this->pets->update($pet, $request->validated());

        return ApiResponse::success(new PetResource($updated), 'Mascota actualizada');
    }

    public function photo(Request $request, Pet $pet): JsonResponse
    {
        $this->authorize('update', Cliente::class);
        $request->validate([
            'foto' => ['required', 'image', 'max:4096'],
        ]);

        $updated = $this->pets->storePhoto($pet, $request->file('foto'));

        return ApiResponse::success(new PetResource($updated), 'Foto actualizada');
    }
}
