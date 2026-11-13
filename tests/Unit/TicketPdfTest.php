<?php

namespace Tests\Unit;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Persona;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Services\TicketPdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class TicketPdfTest extends TestCase
{
    public function test_boleta_keeps_series_totals_and_payment(): void
    {
        $previousPhone = config('invoicing.issuer.phone');
        config(['invoicing.issuer.phone' => '965447762']);

        try {
            $venta = $this->venta();
            $emitida = $venta->created_at->timezone('America/Lima');
            $tickets = new TicketPdf;
            $pdf = $tickets->bytes($tickets->fromVenta($venta));
        } finally {
            config(['invoicing.issuer.phone' => $previousPhone]);
        }

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
        $this->assertStringContainsString('BOLETA DE VENTA', $pdf);
        $this->assertStringContainsString('B001-000003', $pdf);
        $this->assertStringContainsString('74645698', $pdf);
        $this->assertStringContainsString("MEJ\xCDA", $pdf);
        $this->assertStringContainsString("EMISI\xD3N", $pdf);
        $this->assertStringContainsString($emitida->format('d/m/Y'), $pdf);
        $this->assertStringContainsString($emitida->format('H:i:s'), $pdf);
        $this->assertStringContainsString("DESCRIPCI\xD3N", $pdf);
        $this->assertStringContainsString('P.U.', $pdf);
        $this->assertStringContainsString('Alimento', $pdf);
        $this->assertStringContainsString('Alimentos', $pdf);
        $this->assertStringContainsString('S/ 167.80', $pdf);
        $this->assertStringContainsString('S/ 30.20', $pdf);
        $this->assertStringContainsString('S/ 198.00', $pdf);
        $this->assertStringContainsString('FORMAS DE PAGO', $pdf);
        $this->assertStringContainsString('Yape - S/ 198.00', $pdf);
        $this->assertStringContainsString('965447762', $pdf);
        $this->assertStringContainsString('SON: CIENTO NOVENTA Y OCHO CON 00/100 SOLES', $pdf);
    }

    public function test_logo_background_is_white(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD no está disponible.');
        }

        $source = imagecreatetruecolor(40, 40);
        imagealphablending($source, false);
        imagesavealpha($source, true);
        $transparent = imagecolorallocatealpha($source, 0, 0, 0, 127);
        imagefilledrectangle($source, 0, 0, 39, 39, $transparent);
        $black = imagecolorallocatealpha($source, 0, 0, 0, 0);
        imagefilledrectangle($source, 0, 0, 39, 6, $black);
        $blue = imagecolorallocatealpha($source, 0, 62, 139, 0);
        imagefilledrectangle($source, 15, 15, 24, 24, $blue);

        $flat = (new ReflectionMethod(TicketPdf::class, 'flattenOnWhite'))->invoke(new TicketPdf, $source);
        $this->assertSame([255, 255, 255], $this->rgb($flat, 0, 0));
        $this->assertSame([255, 255, 255], $this->rgb($flat, 0, 39));

        $ink = $this->rgb($flat, 20, 20);
        $this->assertGreaterThan(100, $ink[2]);
        $this->assertGreaterThan($ink[0], $ink[2]);

        ob_start();
        imagejpeg($flat, null, 85);
        $jpeg = ob_get_clean();
        $decoded = imagecreatefromstring($jpeg);
        $corner = $this->rgb($decoded, 0, 0);
        $this->assertGreaterThan(250, $corner[0]);
        $this->assertGreaterThan(250, $corner[1]);
        $this->assertGreaterThan(250, $corner[2]);

        imagedestroy($source);
        imagedestroy($flat);
        imagedestroy($decoded);

        $real = (new ReflectionMethod(TicketPdf::class, 'logoJpeg'))->invoke(new TicketPdf);
        if (! is_string($real)) {
            return;
        }
        $logo = imagecreatefromstring($real);
        $edge = $this->rgb($logo, 2, 2);
        $this->assertGreaterThan(250, $edge[0]);
        $this->assertGreaterThan(250, $edge[1]);
        $this->assertGreaterThan(250, $edge[2]);
        imagedestroy($logo);
    }

    private function venta(): Venta
    {
        $persona = new Persona([
            'nombres' => 'JOSE',
            'apellidos' => 'MEJÍA',
            'tipo_documento' => 'DNI',
            'numero_documento' => '74645698',
            'direccion' => 'Barrio Nápoles',
        ]);
        $cliente = new Cliente;
        $cliente->setRelation('persona', $persona);

        $producto = new Producto(['nombre' => 'Alimento adulto pollo 15 kg']);
        $producto->setRelation('categoria', new Categoria(['nombre' => 'Alimentos']));
        $detalle = new VentaDetalle([
            'cantidad' => 1,
            'precio_unitario' => 198,
            'subtotal' => 198,
        ]);
        $detalle->setRelation('producto', $producto);

        $venta = new Venta([
            'total' => 198,
            'descuento' => 0,
            'estado' => 'completada',
            'metodo_pago' => 'yape',
            'monto_pagado' => 198,
        ]);
        $venta->id = 3;
        $venta->created_at = Carbon::parse('2026-11-01 20:23:09', 'America/Lima');
        $venta->setRelation('cliente', $cliente);
        $venta->setRelation('detalles', new Collection([$detalle]));

        return $venta;
    }

    private function rgb(\GdImage $image, int $x, int $y): array
    {
        $rgba = imagecolorat($image, $x, $y);

        return [($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, $rgba & 0xFF];
    }
}
