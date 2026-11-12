<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Pet;
use App\Support\PageSize;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PetService
{
    private const COLUMNS = [
        'id',
        'cliente_id',
        'nombre',
        'especie',
        'raza',
        'sexo',
        'fecha_nacimiento',
        'peso',
        'alergias',
        'foto',
        'created_at',
        'updated_at',
    ];

    public function paginate(?int $clienteId = null, ?Request $request = null): LengthAwarePaginator
    {
        $request ??= request();
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'especie' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50'],
        ]);
        $clienteId ??= $request->filled('cliente_id') ? (int) $request->query('cliente_id') : null;
        $search = trim((string) $request->query('q', ''));
        $species = trim((string) $request->query('especie', ''));

        return Pet::query()
            ->select(self::COLUMNS)
            ->with([
                'cliente:id,persona_id',
                'cliente.persona:id,nombres,apellidos',
            ])
            ->when($clienteId, fn ($query) => $query->where('cliente_id', $clienteId))
            ->when($species !== '', fn ($query) => $query->where('especie', $species))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('nombre', 'like', $like)
                        ->orWhere('raza', 'like', $like)
                        ->orWhere('especie', 'like', $like);
                });
            })
            ->orderBy('nombre')
            ->orderBy('id')
            ->paginate(PageSize::resolve($request));
    }

    public function create(Cliente $cliente, array $data): Pet
    {
        unset($data['cliente_id']);
        $data['cliente_id'] = $cliente->id;

        return Pet::query()->create($data)->load(['cliente.persona']);
    }

    public function update(Pet $pet, array $data): Pet
    {
        $pet->update($data);

        return $pet->fresh(['cliente.persona']);
    }

    public function delete(Pet $pet): void
    {
        if ($this->hasClinicalHistory($pet)) {
            throw new ConflictHttpException('No se puede eliminar la mascota porque tiene citas o historia clínica.');
        }

        if ($pet->foto) {
            Storage::disk('public')->delete($pet->foto);
        }

        $pet->delete();
    }

    public function storePhoto(Pet $pet, UploadedFile $file): Pet
    {
        $path = $file->store('pets', 'public');
        if ($pet->foto) {
            Storage::disk('public')->delete($pet->foto);
        }
        $pet->update(['foto' => $path]);

        return $pet->fresh(['cliente.persona']);
    }

    private function hasClinicalHistory(Pet $pet): bool
    {
        return $pet->appointments()->exists()
            || $pet->medicalRecords()->exists()
            || $pet->vaccinations()->exists()
            || $pet->dewormings()->exists();
    }
}
