<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\ProductService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class ProductoSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasColumn('productos', 'principio_activo')) {
            $this->command?->error('Faltan los campos clínicos en productos. Ejecuta php artisan migrate.');

            return;
        }

        $categorias = Categoria::query()->pluck('id', 'nombre');
        if ($categorias->isEmpty()) {
            $this->command?->error('No hay categorías. Ejecuta primero php artisan db:seed --class=CatalogSeeder.');

            return;
        }

        $proveedores = Proveedor::query()->orderBy('id')->pluck('id')->all();
        $marcas = [];
        $creados = 0;
        $actualizados = 0;
        $omitidos = 0;

        foreach ($this->rows() as $index => $row) {
            $categoriaId = $categorias->get($row['categoria']);
            if ($categoriaId === null) {
                $this->command?->warn("Categoría no encontrada: {$row['categoria']}. Se omite {$row['codigo']}.");
                $omitidos++;

                continue;
            }

            $marcaNombre = $row['marca'];
            $marcas[$marcaNombre] ??= Marca::query()->firstOrCreate(
                ['nombre' => $marcaNombre],
                ['activo' => true],
            )->id;

            $attributes = [
                'nombre' => $row['nombre'],
                'descripcion' => $row['descripcion'],
                'codigo' => $row['codigo'],
                'categoria_id' => $categoriaId,
                'marca_id' => $marcas[$marcaNombre],
                'proveedor_id' => $proveedores === [] ? null : $proveedores[$index % count($proveedores)],
                'especie' => $row['especie'],
                'tipo' => $row['tipo'],
                'presentacion' => $row['presentacion'],
                'talla' => $row['talla'],
                'precio_compra' => $row['precio_compra'],
                'precio_venta' => $row['precio_venta'],
                'stock_minimo' => $row['stock_minimo'],
                'unidad_medida' => $row['unidad_medida'],
                'fecha_vencimiento' => $this->fecha($row['vence']),
                'requiere_receta' => $row['tipo_receta'] !== 'OTC',
                'requires_batch' => $row['requires_batch'],
                'activo' => $row['activo'],
                'principio_activo' => $row['principio_activo'],
                'concentracion' => $row['concentracion'],
                'tipo_receta' => $row['tipo_receta'],
                'especies_destino' => $row['especies_destino'],
                'cadena_frio' => $row['cadena_frio'],
                'tiempo_retiro_dias' => $row['tiempo_retiro_dias'],
                'permite_fraccionamiento' => $row['permite_fraccionamiento'],
                'unidad_dispensacion' => $row['unidad_dispensacion'],
                'factor_conversion' => $row['factor_conversion'],
                'precio_venta_dosis' => $row['precio_venta_dosis'],
                'codigo_lote' => $row['codigo_lote'],
            ];

            $existente = Producto::query()->where('codigo', $row['codigo'])->first();
            if ($existente) {
                $existente->update($attributes);
                $actualizados++;

                continue;
            }

            app(ProductService::class)->create($attributes + [
                'stock_actual' => $row['stock_actual'],
            ]);
            $creados++;
        }

        $this->command?->info("Productos de prueba: {$creados} creados, {$actualizados} actualizados, {$omitidos} omitidos.");
    }

    private function fecha(?string $modificador): ?string
    {
        if ($modificador === null) {
            return null;
        }

        return Carbon::parse($modificador)->toDateString();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        return [
            $this->fila('DEMO-0001', 'Amoxicilina 500 mg', 'Caja de 100 tabletas para infecciones bacterianas.', 'Antibióticos', 'Zoetis', 'Perro/Gato', 'Antibiótico', 'Caja x 100 tabletas', null, 42.00, 68.00, 40, 10, 'caja', '+18 months', true, 'Amoxicilina', '500 mg', 'RECETA_SIMPLE', ['perro', 'gato'], false, null, true, 'tableta', 100, 0.90, 'AMX-24081'),
            $this->fila('DEMO-0002', 'Cefalexina 250 mg', 'Stock bajo para probar alertas.', 'Antibióticos', 'Virbac', 'Perro/Gato', 'Antibiótico', 'Frasco x 20 cápsulas', null, 28.00, 46.00, 3, 15, 'frasco', '+10 months', true, 'Cefalexina', '250 mg', 'RECETA_SIMPLE', ['perro', 'gato'], false, null, true, 'capsula', 20, 2.80, 'CFX-24012'),
            $this->fila('DEMO-0003', 'Enrofloxacina 10%', 'Inyectable de uso en grandes especies, con tiempo de retiro.', 'Antibióticos', 'Bayer', 'Bovino', 'Antibiótico', 'Frasco 100 ml', null, 55.00, 89.00, 12, 4, 'frasco', '+8 months', true, 'Enrofloxacina', '10%', 'RECETA_SIMPLE', ['bovino', 'porcino'], false, 28, false, null, null, null, 'ENR-24110'),
            $this->fila('DEMO-0004', 'Ketamina 100 mg/ml', 'Anestésico controlado de uso clínico.', 'Anestésicos y sedantes', 'Boehringer', 'Múltiple', 'Anestésico', 'Frasco 10 ml', null, 38.00, 72.00, 6, 2, 'frasco', '+6 months', true, 'Ketamina', '100 mg/ml', 'CONTROLADO', ['perro', 'gato', 'equino'], false, null, false, null, null, null, 'KET-23990'),
            $this->fila('DEMO-0005', 'Meloxicam 1.5 mg/ml', 'Antiinflamatorio oral fraccionable.', 'Antiinflamatorios y analgésicos', 'Boehringer', 'Perro', 'Antiinflamatorio', 'Frasco 32 ml', null, 24.00, 39.50, 18, 6, 'frasco', '+14 months', true, 'Meloxicam', '1.5 mg/ml', 'RECETA_SIMPLE', ['perro'], false, null, true, 'ml', 32, 1.50, 'MLX-24102'),
            $this->fila('DEMO-0006', 'Carprofeno 50 mg', 'Vence dentro de 30 días.', 'Antiinflamatorios y analgésicos', 'Zoetis', 'Perro', 'Antiinflamatorio', 'Blíster x 10 tabletas', null, 18.00, 31.00, 9, 4, 'blister', '+12 days', true, 'Carprofeno', '50 mg', 'RECETA_SIMPLE', ['perro'], false, null, true, 'tableta', 10, 3.40, 'CRP-25001'),
            $this->fila('DEMO-0007', 'Dexametasona 4 mg', 'Corticoide inyectable de uso controlado.', 'Antiinflamatorios y analgésicos', 'Genérico', 'Múltiple', 'Antiinflamatorio', 'Ampolla 2 ml', null, 3.50, 8.00, 30, 10, 'ampolla', '+11 months', true, 'Dexametasona', '4 mg/2 ml', 'CONTROLADO', ['perro', 'gato', 'bovino', 'equino'], false, 7, false, null, null, null, 'DXM-24077'),
            $this->fila('DEMO-0008', 'Ivermectina 1%', 'Antiparasitario interno con tiempo de retiro.', 'Antiparasitarios internos', 'Bayer', 'Bovino', 'Antiparasitario', 'Frasco 50 ml', null, 22.00, 36.00, 14, 5, 'frasco', '+9 months', true, 'Ivermectina', '1%', 'RECETA_SIMPLE', ['bovino', 'ovino'], false, 35, false, null, null, null, 'IVM-24055'),
            $this->fila('DEMO-0009', 'Fenbendazol 10%', 'Suspensión oral de venta libre.', 'Antiparasitarios internos', 'Virbac', 'Perro/Gato', 'Antiparasitario', 'Frasco 100 ml', null, 16.00, 27.00, 22, 8, 'frasco', '+16 months', true, 'Fenbendazol', '10%', 'OTC', ['perro', 'gato'], false, null, true, 'ml', 100, 0.35, 'FNB-24120'),
            $this->fila('DEMO-0010', 'Fipronil pipeta perro M', 'Antiparasitario externo de un solo uso.', 'Antiparasitarios externos', 'Boehringer', 'Perro', 'Antiparasitario externo', 'Pipeta 1.34 ml', 'M', 12.00, 22.00, 25, 8, 'pipeta', '+20 months', true, 'Fipronil', '10%', 'OTC', ['perro'], false, null, false, null, null, null, 'FIP-24133'),
            $this->fila('DEMO-0011', 'Vacuna antirrábica', 'Biológico de cadena de frío próximo a vencer.', 'Otros medicamentos veterinarios', 'Zoetis', 'Perro/Gato', 'Vacuna', 'Frasco 1 dosis', null, 8.00, 18.00, 16, 10, 'dosis', '+10 days', true, 'Virus rábico inactivado', '1 dosis', 'RECETA_SIMPLE', ['perro', 'gato'], true, null, false, null, null, null, 'RAB-25008'),
            $this->fila('DEMO-0012', 'Vacuna múltiple canina', 'Lote vencido para probar el filtro de vencidos.', 'Otros medicamentos veterinarios', 'Zoetis', 'Perro', 'Vacuna', 'Frasco 1 dosis', null, 14.00, 28.00, 7, 4, 'dosis', '-25 days', true, 'Moquillo, parvovirus, hepatitis', '1 dosis', 'RECETA_SIMPLE', ['perro'], true, null, false, null, null, null, 'VAC-23901'),
            $this->fila('DEMO-0013', 'Suero antitetánico equino', 'Biológico refrigerado.', 'Otros medicamentos veterinarios', 'Bayer', 'Equino', 'Biológico', 'Frasco 50 ml', null, 48.00, 79.00, 5, 2, 'frasco', '+4 months', true, 'Inmunoglobulina antitetánica', '1500 UI', 'RECETA_SIMPLE', ['equino'], true, null, false, null, null, null, 'TET-24090'),
            $this->fila('DEMO-0014', 'Complejo B inyectable', 'Vitamínico fraccionable, sin receta.', 'Vitaminas', 'Genérico', 'Múltiple', 'Vitamina/Suplemento', 'Frasco 100 ml', null, 11.00, 19.00, 20, 6, 'frasco', '+13 months', true, 'Complejo B', '100 ml', 'OTC', ['perro', 'gato', 'bovino', 'equino'], false, null, true, 'ml', 100, 0.25, 'CVB-24140'),
            $this->fila('DEMO-0015', 'Probiótico intestinal', 'Sobres para soporte digestivo.', 'Probióticos', 'Virbac', 'Perro/Gato', 'Suplemento', 'Caja x 10 sobres', null, 19.00, 32.00, 11, 4, 'caja', '+7 months', false, 'Enterococcus faecium', '1x10^9 UFC', 'OTC', ['perro', 'gato'], false, null, true, 'sobre', 10, 3.60, null),
            $this->fila('DEMO-0016', 'Glucosamina articular', 'Soporte de movilidad en perros adultos.', 'Suplementos articulares', 'Zoetis', 'Perro', 'Suplemento', 'Frasco x 60 tabletas', null, 35.00, 58.00, 8, 3, 'frasco', '+22 months', false, 'Glucosamina, condroitina', '500 mg', 'OTC', ['perro'], false, null, true, 'tableta', 60, 1.10, null),
            $this->fila('DEMO-0017', 'Alimento adulto pollo 15 kg', 'Alimento seco de mantenimiento.', 'Alimento para adultos', 'Royal Canin', 'Perro', 'Alimento', 'Saco 15 kg', null, 145.00, 198.00, 6, 2, 'saco', '+10 months', false, null, null, 'OTC', ['perro'], false, null, false, null, null, null, null),
            $this->fila('DEMO-0018', 'Alimento terapéutico renal', 'Sin stock para probar agotados.', 'Alimento terapéutico', 'Royal Canin', 'Gato', 'Alimento', 'Bolsa 2 kg', null, 62.00, 96.00, 0, 3, 'bolsa', '+5 months', false, null, null, 'OTC', ['gato'], false, null, false, null, null, null, null),
            $this->fila('DEMO-0019', 'Shampoo medicado clorhexidina', 'Higiene dermatológica.', 'Shampoos medicados', 'Virbac', 'Perro/Gato', 'Higiene', 'Frasco 250 ml', null, 18.50, 32.00, 15, 5, 'frasco', '+24 months', false, 'Clorhexidina', '3%', 'OTC', ['perro', 'gato'], false, null, false, null, null, null, null),
            $this->fila('DEMO-0020', 'Gotas óticas', 'Limpieza ótica de venta libre.', 'Óticos', 'Virbac', 'Perro/Gato', 'Higiene', 'Frasco 20 ml', null, 9.00, 17.50, 13, 4, 'frasco', '+15 months', false, 'Ácido láctico', '2%', 'OTC', ['perro', 'gato'], false, null, false, null, null, null, null),
            $this->fila('DEMO-0021', 'Jeringa descartable 10 ml', 'Insumo sin vencimiento ni receta.', 'Jeringas y agujas', 'Genérico', 'Múltiple', 'Insumo', 'Unidad', null, 0.80, 1.50, 80, 20, 'unidad', null, false, null, null, 'OTC', ['perro', 'gato', 'bovino', 'equino'], false, null, false, null, null, null, null),
            $this->fila('DEMO-0022', 'Collar nylon', 'Accesorio con talla.', 'Collares', 'Genérico', 'Perro', 'Accesorio', 'Unidad', 'M', 8.00, 15.00, 12, 4, 'unidad', null, false, null, null, 'OTC', ['perro'], false, null, false, null, null, null, null),
            $this->fila('DEMO-0023', 'Gentamicina 80 mg', 'Producto inactivo, no debe ofrecerse en venta.', 'Antibióticos', 'Genérico', 'Múltiple', 'Antibiótico', 'Ampolla 2 ml', null, 4.00, 9.00, 10, 4, 'ampolla', '+12 months', true, 'Gentamicina', '80 mg/2 ml', 'RECETA_SIMPLE', ['perro', 'gato'], false, null, false, null, null, null, 'GEN-24002', false),
            $this->fila('DEMO-0024', 'Suero fisiológico 500 ml', 'Sin precio de venta para probar el filtro correspondiente.', 'Otros medicamentos veterinarios', 'Genérico', 'Múltiple', 'Insumo', 'Bolsa 500 ml', null, 4.50, 0, 20, 8, 'bolsa', '+9 months', false, 'Cloruro de sodio', '0.9%', 'OTC', ['perro', 'gato', 'bovino', 'equino'], false, null, false, null, null, null, 'SF-24100'),
        ];
    }

    /**
     * @param  list<string>  $especies
     * @return array<string, mixed>
     */
    private function fila(
        string $codigo,
        string $nombre,
        string $descripcion,
        string $categoria,
        string $marca,
        string $especie,
        string $tipo,
        string $presentacion,
        ?string $talla,
        float $precioCompra,
        float $precioVenta,
        int $stockActual,
        int $stockMinimo,
        string $unidad,
        ?string $vence,
        bool $requiresBatch,
        ?string $principio,
        ?string $concentracion,
        string $tipoReceta,
        array $especies,
        bool $cadenaFrio,
        ?int $retiro,
        bool $fraccion,
        ?string $unidadDosis,
        ?float $factor,
        ?float $precioDosis,
        ?string $lote,
        bool $activo = true,
    ): array {
        return [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'categoria' => $categoria,
            'marca' => $marca,
            'especie' => $especie,
            'tipo' => $tipo,
            'presentacion' => $presentacion,
            'talla' => $talla,
            'precio_compra' => $precioCompra,
            'precio_venta' => $precioVenta,
            'stock_actual' => $stockActual,
            'stock_minimo' => $stockMinimo,
            'unidad_medida' => $unidad,
            'vence' => $vence,
            'requires_batch' => $requiresBatch,
            'principio_activo' => $principio,
            'concentracion' => $concentracion,
            'tipo_receta' => $tipoReceta,
            'especies_destino' => $especies,
            'cadena_frio' => $cadenaFrio,
            'tiempo_retiro_dias' => $retiro,
            'permite_fraccionamiento' => $fraccion,
            'unidad_dispensacion' => $unidadDosis,
            'factor_conversion' => $factor,
            'precio_venta_dosis' => $precioDosis,
            'codigo_lote' => $lote,
            'activo' => $activo,
        ];
    }
}
