<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StorePetRequest;
use App\Http\Requests\Customers\UpdatePetRequest;
use App\Http\Resources\PetResource;
use App\Models\Cliente;
use App\Models\Pet;
use App\Services\PetService;
use App\Support\ApiResponse;
use App\Support\PageSize;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PetController extends Controller
{
    public function __construct(private readonly PetService $pets)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Pet::class);

        return $this->paginated($this->pets->paginate(null, $request), 'Mascotas');
    }

    public function store(StorePetRequest $request): JsonResponse
    {
        $this->authorize('create', Pet::class);

        $cliente = Cliente::query()->findOrFail($request->validated('cliente_id'));
        $pet = $this->pets->create($cliente, $request->validated());

        return ApiResponse::success(new PetResource($pet), 'Mascota creada', 201);
    }

    public function show(Pet $pet): JsonResponse
    {
        $this->authorize('viewAny', Cliente::class);

        return ApiResponse::success(new PetResource($pet->load(['cliente.persona'])), 'Mascota');
    }

    public function update(UpdatePetRequest $request, Pet $pet): JsonResponse
    {
        $this->authorize('update', Cliente::class);

        $updated = $this->pets->update($pet, $request->validated());

        return ApiResponse::success(new PetResource($updated), 'Mascota actualizada');
    }

    public function destroy(Pet $pet): JsonResponse
    {
        $this->authorize('update', Pet::class);

        $this->pets->delete($pet);

        return ApiResponse::success(null, 'Mascota eliminada');
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

    public function downloadPhoto(Request $request, Pet $pet): StreamedResponse
    {
        $user = $request->user();
        $staff = $user?->can('customers.view') || $user?->can('customers.update');
        $owner = $user?->can('portal.access') && (int) $user->cliente_id === (int) $pet->cliente_id;
        abort_unless($staff || $owner, 403);

        $disk = null;
        if ($pet->foto && Storage::disk('local')->exists($pet->foto)) {
            $disk = 'local';
        } elseif ($pet->foto && Storage::disk('public')->exists($pet->foto)) {
            $disk = 'public';
        }
        if ($disk === null) {
            abort(404);
        }

        return Storage::disk($disk)->download($pet->foto, basename($pet->foto));
    }

    private function paginated(LengthAwarePaginator $page, string $message): JsonResponse
    {
        return ApiResponse::success([
            'items' => PetResource::collection($page->getCollection())->resolve(),
            'meta' => PageSize::meta($page),
            'summary' => $this->pets->summary(),
        ], $message);
    }
}
