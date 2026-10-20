<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->rebuildSequentialIds();
        Marca::query()->firstOrCreate(['nombre' => 'Genérico'], ['activo' => true]);
    }

    /**
     * @return list<array{nombre: string, descripcion: string, children: list<array{nombre: string, descripcion: string}>}>
     */
    private function tree(): array
    {
        return [
            [
                'nombre' => 'Alimentos',
                'descripcion' => 'Alimentos y nutrición para perros, gatos y otras mascotas',
                'children' => [
                    ['nombre' => 'Alimento seco', 'descripcion' => 'Alimentos secos y croquetas para mascotas'],
                    ['nombre' => 'Alimento húmedo', 'descripcion' => 'Alimentos húmedos, latas, sobres y patés'],
                    ['nombre' => 'Alimento terapéutico', 'descripcion' => 'Alimentos formulados para necesidades nutricionales específicas'],
                    ['nombre' => 'Alimento para cachorros', 'descripcion' => 'Alimentos destinados a mascotas en etapa de crecimiento'],
                    ['nombre' => 'Alimento para adultos', 'descripcion' => 'Alimentos destinados a mascotas adultas'],
                    ['nombre' => 'Alimento para seniors', 'descripcion' => 'Alimentos destinados a mascotas adultas mayores'],
                    ['nombre' => 'Snacks y premios', 'descripcion' => 'Premios, golosinas y snacks para mascotas'],
                ],
            ],
            [
                'nombre' => 'Farmacia veterinaria',
                'descripcion' => 'Medicamentos y productos farmacológicos de uso veterinario',
                'children' => [
                    ['nombre' => 'Antibióticos', 'descripcion' => 'Medicamentos antibacterianos de uso veterinario'],
                    ['nombre' => 'Antiinflamatorios y analgésicos', 'descripcion' => 'Medicamentos utilizados para controlar inflamación y dolor'],
                    ['nombre' => 'Antiparasitarios internos', 'descripcion' => 'Medicamentos destinados al control de parásitos internos'],
                    ['nombre' => 'Antiparasitarios externos', 'descripcion' => 'Productos destinados al control de pulgas, garrapatas y otros ectoparásitos'],
                    ['nombre' => 'Antifúngicos', 'descripcion' => 'Medicamentos utilizados para el tratamiento de infecciones por hongos'],
                    ['nombre' => 'Dermatológicos', 'descripcion' => 'Medicamentos y tratamientos destinados a enfermedades de la piel'],
                    ['nombre' => 'Oftálmicos', 'descripcion' => 'Medicamentos y productos destinados al tratamiento y cuidado ocular'],
                    ['nombre' => 'Óticos', 'descripcion' => 'Medicamentos y productos destinados al tratamiento y cuidado de los oídos'],
                    ['nombre' => 'Gastrointestinales', 'descripcion' => 'Medicamentos destinados al tratamiento de trastornos gastrointestinales'],
                    ['nombre' => 'Cardiovasculares', 'descripcion' => 'Medicamentos destinados al tratamiento de enfermedades cardiovasculares'],
                    ['nombre' => 'Respiratorios', 'descripcion' => 'Medicamentos destinados al tratamiento de afecciones respiratorias'],
                    ['nombre' => 'Reproductivos', 'descripcion' => 'Medicamentos relacionados con reproducción y manejo reproductivo'],
                    ['nombre' => 'Anestésicos y sedantes', 'descripcion' => 'Medicamentos utilizados en anestesia, sedación y procedimientos veterinarios'],
                    ['nombre' => 'Otros medicamentos veterinarios', 'descripcion' => 'Otros medicamentos veterinarios que no corresponden a las categorías anteriores'],
                ],
            ],
            [
                'nombre' => 'Suplementos y nutrición',
                'descripcion' => 'Vitaminas, minerales, probióticos y suplementos nutricionales',
                'children' => [
                    ['nombre' => 'Vitaminas', 'descripcion' => 'Suplementos vitamínicos para mascotas'],
                    ['nombre' => 'Minerales', 'descripcion' => 'Suplementos minerales para mascotas'],
                    ['nombre' => 'Probióticos', 'descripcion' => 'Productos con microorganismos beneficiosos para la salud intestinal'],
                    ['nombre' => 'Prebióticos', 'descripcion' => 'Productos destinados a favorecer la microbiota intestinal'],
                    ['nombre' => 'Suplementos articulares', 'descripcion' => 'Suplementos destinados al soporte de articulaciones y movilidad'],
                    ['nombre' => 'Suplementos digestivos', 'descripcion' => 'Suplementos destinados al soporte de la función digestiva'],
                    ['nombre' => 'Suplementos para piel y pelaje', 'descripcion' => 'Suplementos destinados al mantenimiento de la piel y el pelaje'],
                    ['nombre' => 'Suplementos nutricionales', 'descripcion' => 'Suplementos nutricionales generales para mascotas'],
                ],
            ],
            [
                'nombre' => 'Higiene y cuidado',
                'descripcion' => 'Productos destinados a la higiene, limpieza y cuidado de las mascotas',
                'children' => [
                    ['nombre' => 'Shampoos', 'descripcion' => 'Shampoos de higiene general para mascotas'],
                    ['nombre' => 'Shampoos medicados', 'descripcion' => 'Shampoos formulados para necesidades dermatológicas específicas'],
                    ['nombre' => 'Higiene dental', 'descripcion' => 'Cepillos, pastas y productos para higiene bucal'],
                    ['nombre' => 'Higiene ocular', 'descripcion' => 'Productos destinados a la limpieza y cuidado de los ojos'],
                    ['nombre' => 'Higiene ótica', 'descripcion' => 'Productos destinados a la limpieza y cuidado de los oídos'],
                    ['nombre' => 'Peines y cepillos', 'descripcion' => 'Peines, cepillos y accesorios para el cuidado del pelaje'],
                    ['nombre' => 'Cortauñas', 'descripcion' => 'Herramientas para el corte y mantenimiento de uñas'],
                    ['nombre' => 'Colonias y perfumes', 'descripcion' => 'Colonias, perfumes y productos aromáticos para mascotas'],
                    ['nombre' => 'Pañales', 'descripcion' => 'Pañales y productos absorbentes para mascotas'],
                    ['nombre' => 'Control de orina', 'descripcion' => 'Productos para manejo de orina y entrenamiento'],
                    ['nombre' => 'Limpieza y cuidado', 'descripcion' => 'Productos generales para limpieza y cuidado de mascotas'],
                ],
            ],
            [
                'nombre' => 'Material médico veterinario',
                'descripcion' => 'Materiales, insumos y dispositivos utilizados para atención veterinaria',
                'children' => [
                    ['nombre' => 'Jeringas y agujas', 'descripcion' => 'Jeringas, agujas y accesorios para administración de medicamentos'],
                    ['nombre' => 'Gasas y algodón', 'descripcion' => 'Gasas, algodón y materiales absorbentes'],
                    ['nombre' => 'Vendas', 'descripcion' => 'Vendas, vendas elásticas y materiales para vendaje'],
                    ['nombre' => 'Esparadrapos', 'descripcion' => 'Esparadrapos, cintas y materiales adhesivos médicos'],
                    ['nombre' => 'Suturas', 'descripcion' => 'Materiales y productos para sutura'],
                    ['nombre' => 'Guantes', 'descripcion' => 'Guantes para procedimientos y atención veterinaria'],
                    ['nombre' => 'Catéteres', 'descripcion' => 'Catéteres y dispositivos para acceso vascular'],
                    ['nombre' => 'Material de curación', 'descripcion' => 'Materiales utilizados para limpieza y tratamiento de heridas'],
                    ['nombre' => 'Termómetros', 'descripcion' => 'Termómetros y dispositivos para medición de temperatura'],
                    ['nombre' => 'Instrumental quirúrgico', 'descripcion' => 'Instrumental utilizado en procedimientos quirúrgicos veterinarios'],
                    ['nombre' => 'Otros dispositivos médicos', 'descripcion' => 'Otros materiales y dispositivos de uso médico veterinario'],
                ],
            ],
            [
                'nombre' => 'Accesorios',
                'descripcion' => 'Accesorios para transporte, alimentación, paseo, descanso y cuidado',
                'children' => [
                    ['nombre' => 'Collares', 'descripcion' => 'Collares para perros y gatos'],
                    ['nombre' => 'Correas y tiros', 'descripcion' => 'Correas, tiros y accesorios para paseo'],
                    ['nombre' => 'Arneses y pecheras', 'descripcion' => 'Arneses, pecheras y sistemas de sujeción'],
                    ['nombre' => 'Bozales', 'descripcion' => 'Bozales para perros y otros animales'],
                    ['nombre' => 'Transportadores', 'descripcion' => 'Transportadoras, jaulas y bolsos para mascotas'],
                    ['nombre' => 'Platos y bebederos', 'descripcion' => 'Recipientes para alimentación y agua'],
                    ['nombre' => 'Comederos automáticos', 'descripcion' => 'Comederos y dispensadores automáticos'],
                    ['nombre' => 'Ropa', 'descripcion' => 'Prendas de vestir para mascotas'],
                    ['nombre' => 'Camas y descanso', 'descripcion' => 'Camas, colchones y productos para descanso'],
                    ['nombre' => 'Casas y refugios', 'descripcion' => 'Casas, refugios y espacios de descanso'],
                    ['nombre' => 'Mantas', 'descripcion' => 'Mantas y cobertores para mascotas'],
                    ['nombre' => 'Otros accesorios', 'descripcion' => 'Otros accesorios para mascotas'],
                ],
            ],
            [
                'nombre' => 'Juguetes y entretenimiento',
                'descripcion' => 'Juguetes y productos destinados al entretenimiento y estimulación de las mascotas',
                'children' => [
                    ['nombre' => 'Pelotas', 'descripcion' => 'Pelotas y juguetes para actividad física'],
                    ['nombre' => 'Mordedores', 'descripcion' => 'Juguetes diseñados para morder y masticar'],
                    ['nombre' => 'Juguetes interactivos', 'descripcion' => 'Juguetes destinados a estimular física y mentalmente a las mascotas'],
                    ['nombre' => 'Juguetes dispensadores', 'descripcion' => 'Juguetes que permiten dispensar alimento o premios'],
                    ['nombre' => 'Juguetes para gatos', 'descripcion' => 'Juguetes destinados principalmente a gatos'],
                    ['nombre' => 'Rascadores', 'descripcion' => 'Rascadores y elementos para gatos'],
                    ['nombre' => 'Otros juguetes', 'descripcion' => 'Otros juguetes y productos de entretenimiento'],
                ],
            ],
            [
                'nombre' => 'Equipamiento y grooming',
                'descripcion' => 'Equipos, herramientas y productos utilizados para peluquería y cuidado profesional',
                'children' => [
                    ['nombre' => 'Máquinas de corte', 'descripcion' => 'Máquinas eléctricas y equipos para corte de pelo'],
                    ['nombre' => 'Tijeras de grooming', 'descripcion' => 'Tijeras profesionales para peluquería de mascotas'],
                    ['nombre' => 'Secadores', 'descripcion' => 'Secadores y equipos para secado de mascotas'],
                    ['nombre' => 'Mesas de grooming', 'descripcion' => 'Mesas y estaciones para peluquería de mascotas'],
                    ['nombre' => 'Accesorios de peluquería', 'descripcion' => 'Accesorios y herramientas para grooming'],
                    ['nombre' => 'Equipamiento veterinario', 'descripcion' => 'Equipos utilizados para atención y procedimientos veterinarios'],
                ],
            ],
        ];
    }

    private function rebuildSequentialIds(): void
    {
        $now = now();
        $rows = [];
        $nameToNewId = [];
        $nextId = 1;

        foreach ($this->tree() as $root) {
            $rootId = $nextId++;
            $nameToNewId[mb_strtolower($root['nombre'])] = $rootId;
            $rows[] = [
                'id' => $rootId,
                'parent_id' => null,
                'nombre' => $root['nombre'],
                'descripcion' => $root['descripcion'],
                'activo' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            foreach ($root['children'] as $child) {
                $childId = $nextId++;
                $nameToNewId[mb_strtolower($child['nombre'])] = $childId;
                $rows[] = [
                    'id' => $childId,
                    'parent_id' => $rootId,
                    'nombre' => $child['nombre'],
                    'descripcion' => $child['descripcion'],
                    'activo' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $aliases = [
            'medicamentos' => 'farmacia veterinaria',
            'higiene' => 'higiene y cuidado',
            'material médico' => 'material médico veterinario',
            'herramientas e instrumental' => 'equipamiento y grooming',
            'camas' => 'camas y descanso',
            'ropa' => 'ropa',
        ];
        $fallbackId = $nameToNewId['alimentos'] ?? 1;
        $oldToNew = [];
        foreach (Categoria::query()->get(['id', 'nombre']) as $old) {
            $key = mb_strtolower((string) $old->nombre);
            $alias = $aliases[$key] ?? null;
            $oldToNew[$old->id] = $nameToNewId[$key] ?? ($alias ? ($nameToNewId[$alias] ?? $fallbackId) : $fallbackId);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        if ($oldToNew !== []) {
            Producto::query()->update(['categoria_id' => DB::raw('categoria_id + 100000')]);
            foreach ($oldToNew as $oldId => $newId) {
                Producto::query()->where('categoria_id', $oldId + 100000)->update(['categoria_id' => $newId]);
            }
            Producto::query()->where('categoria_id', '>=', 100000)->update(['categoria_id' => $fallbackId]);
        }

        Categoria::query()->delete();
        foreach ($rows as $row) {
            DB::table('categorias')->insert($row);
        }
        DB::statement('ALTER TABLE categorias AUTO_INCREMENT = '.$nextId);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
