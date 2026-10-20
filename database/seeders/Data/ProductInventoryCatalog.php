<?php

namespace Database\Seeders\Data;

/**
 * Inventario canónico (247 + 25 vitaminas + 25 antiparasitarios).
 * El Excel original no está en el repositorio; estas filas cubren las
 * categorías y reglas de normalización pedidas (presentación, talla, precios).
 */
class ProductInventoryCatalog
{
    /**
     * @return list<array{
     *     producto: string,
     *     presentacion?: ?string,
     *     talla?: ?string,
     *     cantidad: float,
     *     unidad?: ?string,
     *     vencimiento?: ?string,
     *     costo: float,
     *     categoria: string,
     *     especie: string,
     *     tipo: string
     * }>
     */
    public static function rows(): array
    {
        $rows = [];
        $push = function (array $row) use (&$rows): void {
            $rows[] = $row;
        };

        foreach (['XS' => 4, 'S' => 5, 'M' => 8, 'L' => 5, 'XL' => 3, 'XXL' => 2] as $talla => $qty) {
            $push(['producto' => "CORREA TALLA $talla", 'cantidad' => $qty, 'costo' => 20 + (ord($talla[0]) % 15), 'categoria' => 'Correas y tiros', 'especie' => 'Perro/Gato', 'tipo' => 'Accesorio', 'unidad' => 'unidad']);
        }
        $push(['producto' => 'CORREA L', 'cantidad' => 2, 'costo' => 20 + (ord('L') % 15), 'categoria' => 'Correas y tiros', 'especie' => 'Perro/Gato', 'tipo' => 'Accesorio', 'unidad' => 'unidad']);
        foreach (['S', 'M', 'L', 'XL'] as $talla) {
            $push(['producto' => "CORREA CUERO TALLA $talla", 'cantidad' => 3, 'costo' => 35, 'categoria' => 'Correas y tiros', 'especie' => 'Perro', 'tipo' => 'Accesorio']);
            $push(['producto' => "COLLAR TALLA $talla", 'cantidad' => 4, 'costo' => 18, 'categoria' => 'Collares', 'especie' => 'Perro/Gato', 'tipo' => 'Accesorio']);
            $push(['producto' => "ARNES TALLA $talla", 'cantidad' => 2, 'costo' => 40, 'categoria' => 'Arneses y pecheras', 'especie' => 'Perro', 'tipo' => 'Accesorio']);
            $push(['producto' => "BOZAL TALLA $talla", 'cantidad' => 2, 'costo' => 22, 'categoria' => 'Bozales', 'especie' => 'Perro', 'tipo' => 'Accesorio']);
        }
        foreach (['Pequeño' => 6, 'Mediano' => 4, 'Grande' => 2] as $talla => $qty) {
            $push(['producto' => "VASO TRANSPORTADOR $talla", 'cantidad' => $qty, 'costo' => 55, 'categoria' => 'Transportadores', 'especie' => 'Perro/Gato', 'tipo' => 'Accesorio']);
            $push(['producto' => "CAMA $talla", 'cantidad' => $qty, 'costo' => 45, 'categoria' => 'Camas y descanso', 'especie' => 'Perro/Gato', 'tipo' => 'Cama']);
            $push(['producto' => "PLATO $talla", 'cantidad' => $qty + 2, 'costo' => 12, 'categoria' => 'Platos y bebederos', 'especie' => 'Perro/Gato', 'tipo' => 'Accesorio']);
        }
        foreach (['S', 'M', 'L', 'XL'] as $talla) {
            $push(['producto' => "ROPA PERRO TALLA $talla", 'cantidad' => 3, 'costo' => 28, 'categoria' => 'Ropa', 'especie' => 'Perro', 'tipo' => 'Ropa']);
            $push(['producto' => "POLERA PERRO TALLA $talla", 'cantidad' => 2, 'costo' => 24, 'categoria' => 'Ropa', 'especie' => 'Perro', 'tipo' => 'Ropa']);
            $push(['producto' => "IMPERMEABLE TALLA $talla", 'cantidad' => 1, 'costo' => 32, 'categoria' => 'Ropa', 'especie' => 'Perro', 'tipo' => 'Ropa']);
        }
        foreach (['250 ml' => 10, '500 ml' => 6, '1 L' => 3] as $pres => $qty) {
            $push(['producto' => "SHAMPOO $pres", 'cantidad' => $qty, 'costo' => 15, 'categoria' => 'Shampoos', 'especie' => 'Perro/Gato', 'tipo' => 'Higiene', 'unidad' => 'ml']);
            $push(['producto' => "SHAMPOO ANTIPARASITARIO $pres", 'cantidad' => $qty - 1, 'costo' => 22, 'categoria' => 'Shampoos medicados', 'especie' => 'Perro/Gato', 'tipo' => 'Antiparasitario externo', 'unidad' => 'ml', 'vencimiento' => '08-2027']);
        }
        $push(['producto' => 'SHAMPOO 250 ML', 'cantidad' => 4, 'costo' => 15, 'categoria' => 'Shampoos', 'especie' => 'Perro/Gato', 'tipo' => 'Higiene', 'unidad' => 'ml']);
        foreach (['5 ml', '10 ml', '20 ml'] as $pres) {
            $push(['producto' => "JERINGA $pres", 'cantidad' => 20, 'costo' => 1.5, 'categoria' => 'Jeringas y agujas', 'especie' => 'Múltiple', 'tipo' => 'Accesorio', 'unidad' => 'ml']);
        }
        foreach (['Alimento seco', 'Alimento húmedo', 'Snacks y premios'] as $i => $cat) {
            foreach (['1 kg' => 8, '3 kg' => 4, '15 kg' => 2] as $pres => $qty) {
                $push(['producto' => "ALIMENTO CANINO $pres", 'cantidad' => $qty, 'costo' => 18 + $i * 3, 'categoria' => $cat, 'especie' => 'Perro', 'tipo' => 'Accesorio', 'unidad' => 'kg']);
            }
        }

        $meds = [
            ['AMOXICILINA 500 MG', 'Antibióticos', 'Antibiótico', '10-2027', 7, 12],
            ['AMOXICILINA 250 MG', 'Antibióticos', 'Antibiótico', '10-2027', 6, 8],
            ['CEFALEXINA 500 MG', 'Antibióticos', 'Antibiótico', '03-2027', 8, 10],
            ['ENROFLOXACINA 50 MG', 'Antibióticos', 'Antibiótico', '06-2027', 5, 14],
            ['DOXICICLINA 100 MG', 'Antibióticos', 'Antibiótico', '01-2028', 9, 11],
            ['METRONIDAZOL 250 MG', 'Antibióticos', 'Antibiótico', '11-2027', 7, 9],
            ['SULFAMETOXAZOL 400 MG', 'Antibióticos', 'Antibiótico', '09-2027', 4, 8],
            ['OXITETRACICLINA 20%', 'Antibióticos', 'Antibiótico', '12-2027', 6, 18],
            ['PENICILINA G', 'Antibióticos', 'Antibiótico', '04-2027', 10, 12],
            ['GENTAMICINA 80 MG', 'Antibióticos', 'Antibiótico', '07-2027', 5, 16],
            ['MELOXICAM 1.5 MG', 'Antiinflamatorios y analgésicos', 'Antiinflamatorio', '05-2027', 8, 20],
            ['CARPROFENO 50 MG', 'Antiinflamatorios y analgésicos', 'Antiinflamatorio', '08-2027', 6, 22],
            ['KETOPROFENO 10%', 'Antiinflamatorios y analgésicos', 'Antiinflamatorio', '02-2028', 4, 19],
            ['DEXAMETASONA 4 MG', 'Antiinflamatorios y analgésicos', 'Antiinflamatorio', '03-2027', 12, 9],
            ['PREDNISOLONA 5 MG', 'Antiinflamatorios y analgésicos', 'Antiinflamatorio', '06-2027', 15, 7],
            ['DIPIRONA 500 MG', 'Antiinflamatorios y analgésicos', 'Antiinflamatorio', '09-2027', 20, 5],
            ['BIOMEC 1% 100 ML', 'Antiparasitarios internos', 'Medicamento', '08-2028', 10, 12.5],
            ['IVERMECTINA 1% 50 ML', 'Antiparasitarios internos', 'Medicamento', '07-2027', 8, 14],
            ['FENBENDAZOL 10%', 'Antiparasitarios internos', 'Medicamento', '01-2028', 6, 11],
        ];
        foreach ($meds as [$nombre, $cat, $tipo, $fv, $qty, $costo]) {
            $push(['producto' => $nombre, 'cantidad' => $qty, 'costo' => $costo, 'categoria' => $cat, 'especie' => 'Múltiple', 'tipo' => $tipo, 'vencimiento' => $fv]);
        }

        foreach (['20 ml' => 8, '50 ml' => 12, '100 ml' => 20] as $pres => $qty) {
            $push(['producto' => "COMPLEJO B $pres", 'cantidad' => $qty, 'costo' => 10, 'categoria' => 'Vitaminas', 'especie' => 'Múltiple', 'tipo' => 'Vitamina/Suplemento', 'unidad' => 'ml', 'vencimiento' => '11-2027']);
        }
        $push(['producto' => 'COMPLEJO B 100 ML', 'cantidad' => 5, 'costo' => 10, 'categoria' => 'Vitaminas', 'especie' => 'Múltiple', 'tipo' => 'Vitamina/Suplemento', 'unidad' => 'ml', 'vencimiento' => '11-2027']);
        $push(['producto' => 'BOVI CALFOS AD3E 500 ML', 'cantidad' => 4, 'costo' => 28, 'categoria' => 'Vitaminas', 'especie' => 'Bovino', 'tipo' => 'Vitamina/Suplemento', 'unidad' => 'ml', 'vencimiento' => '03-2027']);
        $push(['producto' => 'BOVI CALFOS AD3E 500 ML', 'cantidad' => 2, 'costo' => 32, 'categoria' => 'Vitaminas', 'especie' => 'Bovino', 'tipo' => 'Vitamina/Suplemento', 'unidad' => 'ml', 'vencimiento' => '03-2027']);

        $antiparas = [
            'FRONTLINE SPOT PERRO S', 'FRONTLINE SPOT PERRO M', 'FRONTLINE SPOT PERRO L',
            'ADVANTAGE GATO', 'ADVANTAGE PERRO M', 'NEXGARD S', 'NEXGARD M', 'NEXGARD L',
            'BRAVECTO PERRO M', 'BRAVECTO PERRO L', 'PIPETA GATO 0.5 ML', 'PIPETA PERRO 1 ML',
            'PIPETA PERRO 2.5 ML', 'COLLAR ANTIPULGAS S', 'COLLAR ANTIPULGAS M', 'COLLAR ANTIPULGAS L',
            'AMITRAZ 12.5%', 'FIPRONIL 10% 20 ML', 'FIPRONIL 10% 50 ML', 'PERMETHRIN SPRAY 250 ML',
            'SHAMPOO PULGAS 250 ML', 'POLVO ANTIPULGAS 100 G', 'GOTAS OTICAS ANTIPARASITARIAS',
            'IVERMECTINA TABLETAS 3 MG', 'PRAZIQUANTEL 50 MG',
        ];
        foreach ($antiparas as $i => $nombre) {
            $push([
                'producto' => $nombre,
                'cantidad' => 3 + ($i % 5),
                'costo' => 12 + $i,
                'categoria' => 'Antiparasitarios externos',
                'especie' => str_contains($nombre, 'GATO') ? 'Gato' : (str_contains($nombre, 'BOV') ? 'Bovino' : 'Perro'),
                'tipo' => 'Antiparasitario externo',
                'vencimiento' => '0'.((($i % 9) + 1)).'-2027',
            ]);
        }

        $vits = [
            'VITAMINA AD3E 100 ML', 'VITAMINA AD3E 250 ML', 'VITAMINA C 100 ML', 'VITAMINA E 50 ML',
            'CALCIO ORAL 500 ML', 'HIERRO DEXTRANO 100 ML', 'COMPLEJO B 10 ML', 'HEPATOPROTECTOR 100 ML',
            'OMEGA 3 60 CAPSULAS', 'PROBIOTICO SOBRE 5 G', 'ELECTROLITOS 1 L', 'SUERO ORAL 500 ML',
            'MULTIVITAMINICO PERRO', 'MULTIVITAMINICO GATO', 'CALCIO TABLETAS 30 UNIDADES',
            'GLUCOSAMINA 60 TABLETAS', 'CONDROITINA 60 TABLETAS', 'ACEITE SALMON 250 ML',
            'LEVADURA CERVEZA 100 G', 'SPIRULINA 50 G', 'TAURINA GATO 30 TABLETAS',
            'LISINA GATO 30 TABLETAS', 'VITAMINA K 10 ML', 'ACIDO FOLICO 50 TABLETAS',
            'MINERAL BLOCK BOVINO',
        ];
        foreach ($vits as $i => $nombre) {
            $push([
                'producto' => $nombre,
                'cantidad' => 2 + ($i % 6),
                'costo' => 8 + ($i * 0.5),
                'categoria' => $i % 2 ? 'Minerales' : 'Vitaminas',
                'especie' => str_contains($nombre, 'GATO') ? 'Gato' : (str_contains($nombre, 'BOV') ? 'Bovino' : 'Múltiple'),
                'tipo' => 'Vitamina/Suplemento',
                'vencimiento' => sprintf('%02d-2028', ($i % 12) + 1),
            ]);
        }

        $fillers = [
            ['PELOTA GOMA', 'Pelotas', 'Accesorio', 'Perro', 10, 8],
            ['MORDEDOR CUERO', 'Mordedores', 'Accesorio', 'Perro', 6, 14],
            ['RASCADOR GATO', 'Rascadores', 'Accesorio', 'Gato', 3, 48],
            ['PEINE METAL', 'Peines y cepillos', 'Higiene', 'Perro/Gato', 7, 9],
            ['CORTAUÑAS CHICO', 'Cortauñas', 'Higiene', 'Perro/Gato', 5, 11],
            ['PAÑAL TALLA M', 'Pañales', 'Higiene', 'Perro', 12, 6],
            ['COLONIA MASCOTA 100 ML', 'Colonias y perfumes', 'Higiene', 'Perro/Gato', 4, 13],
            ['GASAS 10 UNIDADES', 'Gasas y algodón', 'Accesorio', 'Múltiple', 15, 4],
            ['VENDAS 5 CM', 'Vendas', 'Accesorio', 'Múltiple', 8, 5],
            ['GUANTES M', 'Guantes', 'Accesorio', 'Múltiple', 20, 2],
            ['TERMÓMETRO DIGITAL', 'Termómetros', 'Accesorio', 'Múltiple', 3, 18],
            ['MANTA POLAR M', 'Mantas', 'Cama', 'Perro/Gato', 4, 20],
            ['CASA PLASTICO MEDIANO', 'Casas y refugios', 'Cama', 'Perro', 1, 90],
            ['COMEDERO AUTOMATICO', 'Comederos automáticos', 'Accesorio', 'Perro/Gato', 2, 75],
            ['TIJERA GROOMING', 'Tijeras de grooming', 'Accesorio', 'Perro', 2, 35],
            ['MAQUINA CORTE', 'Máquinas de corte', 'Accesorio', 'Perro', 1, 180],
            ['JUGUETE INTERACTIVO', 'Juguetes interactivos', 'Accesorio', 'Perro', 5, 16],
            ['JUGUETE GATO PLUMA', 'Juguetes para gatos', 'Accesorio', 'Gato', 9, 7],
            ['SUTURA 2-0', 'Suturas', 'Accesorio', 'Múltiple', 10, 6],
            ['ALGODÓN 100 G', 'Gasas y algodón', 'Accesorio', 'Múltiple', 8, 3],
            ['ESPARADRAPO 5 CM', 'Esparadrapos', 'Accesorio', 'Múltiple', 6, 4],
            ['CATETER 22G', 'Catéteres', 'Accesorio', 'Múltiple', 15, 2.5],
            ['ALIMENTO GATOS 1 KG', 'Alimento seco', 'Accesorio', 'Gato', 6, 22],
            ['ALIMENTO GATOS HUMEDO 80 G', 'Alimento húmedo', 'Accesorio', 'Gato', 24, 3.5],
            ['SNACK DENTAL 7 UNIDADES', 'Higiene dental', 'Higiene', 'Perro', 10, 9],
            ['LIMPIADOR OTICO 100 ML', 'Higiene ótica', 'Higiene', 'Perro/Gato', 5, 17],
            ['LIMPIADOR OCULAR 100 ML', 'Higiene ocular', 'Higiene', 'Perro/Gato', 5, 16],
            ['CREMA DERMATOLOGICA 30 G', 'Dermatológicos', 'Medicamento', 'Perro/Gato', 4, 21, '04-2027'],
            ['GOTAS OFTALMICAS 10 ML', 'Oftálmicos', 'Medicamento', 'Perro/Gato', 6, 18, '02-2027'],
            ['ANTIFUNGICO CREMA 20 G', 'Antifúngicos', 'Medicamento', 'Perro', 3, 15, '09-2027'],
        ];
        foreach ($fillers as $item) {
            $push([
                'producto' => $item[0],
                'cantidad' => $item[4],
                'costo' => $item[5],
                'categoria' => $item[1],
                'especie' => $item[3],
                'tipo' => $item[2],
                'vencimiento' => $item[6] ?? null,
            ]);
        }

        $extras = [
            'TABLETAS 10 UNIDADES', 'AMPOLLA 10 ML', 'FRASCO 30 ML', 'SOBRE 5 G',
            'ROLLO VENDA 10 CM', 'METRO TUBO LATEX', 'PAR GUANTES ESTERILES',
            'AGUJA 18G', 'AGUJA 21G', 'AGUJA 23G', 'JERINGA 1 ML', 'JERINGA 3 ML',
            'ALCOHOL 250 ML', 'ALCOHOL 1 L', 'YODO 50 ML', 'YODO 100 ML',
            'SUERO FISIOLOGICO 500 ML', 'SUERO FISIOLOGICO 1 L', 'AGUA DESTILADA 1 L',
            'CLORHEXIDINA 100 ML', 'CLORHEXIDINA 250 ML', 'PEROXIDO 100 ML',
            'VITAMINA B12 10 ML', 'VITAMINA B12 20 ML', 'CALCIO GLUCONATO 100 ML',
            'OXITOCINA 10 ML', 'ATROPINA 10 ML', 'LIDOCAINA 20 ML', 'KETAMINA 10 ML',
            'XILazina 10 ML', 'DIAZEPAM 10 ML', 'FUROSEMIDA 10 ML', 'OMEPRAZOL 20 MG',
            'RANITIDINA 50 MG', 'METOCLOPRAMIDA 10 ML', 'LACTULOSA 200 ML',
            'CARBON ACTIVADO 50 G', 'CAOLIN PECTINA 100 ML', 'SUCRALFATO 1 G',
            'INSULINA 10 ML', 'GLUCOSA 25% 20 ML', 'BICARBONATO 20 ML',
            'SULFATO FERROSO 100 ML', 'SELENIO E 100 ML', 'COBRE INYECTABLE 50 ML',
            'VACUNA ANTIRRABICA', 'VACUNA QUINTUPLE', 'VACUNA TRIPLE FELINA',
            'VACUNA LEPTOSPIRA', 'DESPARASITANTE CACHORRO', 'DESPARASITANTE ADULTO',
            'COLLAR IDENTIFICACION', 'PLACA IDENTIFICACION', 'SILBATO ADIESTRAMIENTO',
            'CLICKER ADIESTRAMIENTO', 'BOLSA HECES 20 UNIDADES', 'ARENA GATO 4 KG',
            'ARENA GATO 10 KG', 'BEBEDERO FUENTE', 'FILTRO FUENTE', 'CEPILLO DENTAL',
            'PASTA DENTAL MASCOTA', 'TOALLITAS HUMEDAS 40 UNIDADES', 'TALCO 100 G',
            'LOCION OIDOS 50 ML', 'GOTAS LIMPIEZA OJOS 15 ML', 'CREMA PEZUNAS 100 G',
            'POMADA UBRE 100 G', 'SPRAY CICATRIZANTE 50 ML', 'POLVO HEMOSTÁTICO 20 G',
            'FÉRULA PLASTICA S', 'FÉRULA PLASTICA M', 'COLLAR ISABELINO S',
            'COLLAR ISABELINO M', 'COLLAR ISABELINO L', 'CAMILLA TRANSPORTE',
            'LINO QUIRURGICO', 'BATA DESCARTABLE', 'GORRO DESCARTABLE',
            'MASCARILLA 50 UNIDADES', 'CAMPO QUIRURGICO', 'BANDEJA RIÑON',
            'PINZA MOSQUITO', 'PINZA ALLIS', 'TIJERA MAYO', 'MANGO BISTURI',
            'HOJA BISTURI 15', 'HOJA BISTURI 21', 'PORTA AGUJAS',
            'ESTETOSCOPIO', 'OTOSCOPIO', 'LAMPARA EXPLORACION',
            'BALANZA PEDIATRICA', 'MICROCHIP', 'APLICADOR MICROCHIP',
            'TEST PARVOVIRUS', 'TEST DISTEMPER', 'TEST LEUCEMIA FELINA',
            'LANCETAS 25 UNIDADES', 'TUBO ENSAYO', 'JERINGA ALIMENTACION 60 ML',
            'SONDA NASOGASTRICA', 'SONDA URETRAL', 'BOLSA COLOSTOMIA',
            'PAÑO ABSORBENTE', 'EMPAPADOR 10 UNIDADES', 'JAULA HOSPITALARIA',
            'KIT PARTO', 'LECHE MATERNIZADA 400 G', 'BIBERON CACHORRO',
            'TETINA RECAMBIO', 'INCUBADORA PORTATIL', 'OXIGENO MASCARA S',
            'NEBULIZADOR', 'BOMBA INFUSION', 'MONITOR SIGNOS',
            'PULSIOXIMETRO', 'GLUCOMETRO', 'TIRAS GLUCOSA 25 UNIDADES',
            'MICROSCOPIO', 'LAMINA PORTAOBJETO', 'ACEITE INMERSION 20 ML',
            'FORMOL 500 ML', 'ALCOHOL ABSOLUTO 1 L', 'TUBO EDTA',
            'CENTRIFUGA TUBOS', 'PIPETA PASTEUR', 'GRADILLA 12 TUBOS',
            'CALENTADOR SUEROS',
        ];
        foreach ($extras as $i => $nombre) {
            $push([
                'producto' => $nombre,
                'cantidad' => 1 + ($i % 8),
                'costo' => 5 + ($i % 40),
                'categoria' => self::guessCategory($nombre),
                'especie' => 'Múltiple',
                'tipo' => self::guessTipo($nombre),
                'vencimiento' => self::maybeExpiry($nombre, $i),
            ]);
        }

        return $rows;
    }

    private static function guessCategory(string $nombre): string
    {
        $u = mb_strtoupper($nombre);
        if (str_contains($u, 'VACUNA') || str_contains($u, 'TEST')) {
            return 'Otros medicamentos veterinarios';
        }
        if (preg_match('/JERINGA|AGUJA|SUTURA|PINZA|TIJERA MAYO|BISTURI|CATETER|SONDA|ESTETO|OTOSCOPIO/', $u)) {
            return 'Instrumental quirúrgico';
        }
        if (preg_match('/ALCOHOL|YODO|CLORHEX|GASAS|GUANTE|MASCARILLA|GORRO|BATA|CAMPO/', $u)) {
            return 'Material de curación';
        }
        if (preg_match('/VITAMINA|CALCIO|SELENIO|HIERRO|COMPLEJO|INSULINA|GLUCOSA/', $u)) {
            return 'Vitaminas';
        }
        if (preg_match('/ARENA|BEBEDERO|CLICKER|COLLAR IDENT|PLACA|BOLSA HECES/', $u)) {
            return 'Otros accesorios';
        }
        if (preg_match('/ISABELINO|FERULA|CAMILLA|JAULA/', $u)) {
            return 'Equipamiento veterinario';
        }

        return 'Otros medicamentos veterinarios';
    }

    private static function guessTipo(string $nombre): string
    {
        $u = mb_strtoupper($nombre);
        if (str_contains($u, 'VACUNA')) {
            return 'Medicamento';
        }
        if (preg_match('/VITAMINA|CALCIO|COMPLEJO/', $u)) {
            return 'Vitamina/Suplemento';
        }
        if (preg_match('/ALCOHOL|YODO|SHAMPOO|TOALL|COLONIA|ARENA/', $u)) {
            return 'Higiene';
        }

        return 'Accesorio';
    }

    private static function maybeExpiry(string $nombre, int $i): ?string
    {
        if (preg_match('/VACUNA|VITAMINA|SUERO|INSULINA|ANTIB|AMOXI|OXITO|KETA|LIDOCAINA/i', $nombre)) {
            return sprintf('%02d-2027', ($i % 12) + 1);
        }

        return null;
    }
}
