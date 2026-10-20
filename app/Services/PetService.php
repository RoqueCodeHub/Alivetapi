<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Pet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PetService
{
    public function create(Cliente $cliente, array $data): Pet
    {
        $data['cliente_id'] = $cliente->id;

        return Pet::create($data)->load('cliente');
    }

    public function update(Pet $pet, array $data): Pet
    {
        $pet->update($data);

        return $pet->fresh('cliente');
    }

    public function storePhoto(Pet $pet, UploadedFile $file): Pet
    {
        $path = $file->store('pets', 'public');
        if ($pet->foto) {
            Storage::disk('public')->delete($pet->foto);
        }
        $pet->update(['foto' => $path]);

        return $pet->fresh('cliente');
    }
}
