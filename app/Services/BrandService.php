<?php

namespace App\Services;

use App\Models\Marca;
use Illuminate\Database\Eloquent\Collection;

class BrandService
{
    public function all(): Collection
    {
        return Marca::query()->orderBy('nombre')->get();
    }

    public function create(array $data): Marca
    {
        return Marca::create($data);
    }

    public function update(Marca $marca, array $data): Marca
    {
        $marca->update($data);

        return $marca->fresh();
    }
}
