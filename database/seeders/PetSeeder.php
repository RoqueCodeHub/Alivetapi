<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Pet;
use Illuminate\Database\Seeder;

class PetSeeder extends Seeder
{
    public function run(): void
    {
        $clientes = Cliente::query()
            ->select(['id', 'persona_id'])
            ->with('persona:id,nombres,numero_documento')
            ->whereHas('persona', function ($query) {
                $query->whereIn('numero_documento', array_keys($this->rows()));
            })
            ->get()
            ->keyBy(fn (Cliente $cliente) => $cliente->persona?->numero_documento);

        $creados = 0;
        $actualizados = 0;

        foreach ($this->rows() as $documento => $mascotas) {
            $cliente = $clientes->get($documento);
            if ($cliente === null) {
                $this->command?->warn("Cliente {$documento} no encontrado. Ejecuta php artisan db:seed --class=ClienteSeeder.");

                continue;
            }

            foreach ($mascotas as $mascota) {
                $pet = Pet::query()->updateOrCreate(
                    [
                        'cliente_id' => $cliente->id,
                        'nombre' => $mascota['nombre'],
                    ],
                    [
                        'especie' => $mascota['especie'],
                        'raza' => $mascota['raza'],
                        'sexo' => $mascota['sexo'],
                        'fecha_nacimiento' => $mascota['fecha_nacimiento'],
                        'peso' => $mascota['peso'],
                        'alergias' => $mascota['alergias'],
                    ]
                );

                $pet->wasRecentlyCreated ? $creados++ : $actualizados++;
            }
        }

        $this->command?->info("Mascotas: {$creados} creadas, {$actualizados} actualizadas.");
    }

    private function rows(): array
    {
        return [
            '44556677' => [
                $this->pet('Luna', 'felino', 'Siamés', 'hembra', '2022-03-14', 4.20, 'Polen'),
                $this->pet('Copito', 'felino', 'Persa', 'macho', '2021-11-02', 5.10, null),
            ],
            '33445566' => [
                $this->pet('Max', 'canino', 'Labrador', 'macho', '2020-06-18', 28.40, null),
                $this->pet('Rocky', 'canino', 'Mestizo', 'macho', '2019-01-09', 18.00, 'Pollo'),
                $this->pet('Kira', 'canino', 'Pastor alemán', 'hembra', '2023-04-27', 22.75, null),
            ],
            '55667788' => [
                $this->pet('Michi', 'felino', 'Mestizo', 'hembra', '2024-02-11', 3.40, null),
                $this->pet('Nube', 'ave', 'Periquito', 'desconocido', '2024-08-01', 0.08, null),
            ],
            '40112233' => [
                $this->pet('Toby', 'canino', 'Beagle', 'macho', '2018-09-30', 12.60, 'Carne de res'),
                $this->pet('Pelusa', 'roedor', 'Cobayo', 'hembra', '2025-01-15', 0.90, null),
            ],
            '41223344' => [
                $this->pet('Sol', 'felino', 'Angora', 'hembra', '2021-07-21', 4.80, null),
                $this->pet('Bruno', 'canino', 'Bulldog', 'macho', '2017-12-05', 24.30, 'Lácteos'),
                $this->pet('Lola', 'canino', 'Cocker', 'hembra', '2022-10-19', 11.20, null),
                $this->pet('Paco', 'ave', 'Loro', 'macho', '2016-05-03', 0.40, null),
            ],
        ];
    }

    private function pet(
        string $nombre,
        string $especie,
        string $raza,
        string $sexo,
        string $fechaNacimiento,
        float $peso,
        ?string $alergias,
    ): array {
        return [
            'nombre' => $nombre,
            'especie' => $especie,
            'raza' => $raza,
            'sexo' => $sexo,
            'fecha_nacimiento' => $fechaNacimiento,
            'peso' => $peso,
            'alergias' => $alergias,
        ];
    }
}
