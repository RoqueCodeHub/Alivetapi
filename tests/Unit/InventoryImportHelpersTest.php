<?php

namespace Tests\Unit;

use App\Support\InventoryDateParser;
use App\Support\ProductVariantNormalizer;
use PHPUnit\Framework\TestCase;

class InventoryImportHelpersTest extends TestCase
{
    public function test_dates_resolve_to_last_day_of_month(): void
    {
        $this->assertSame('2028-08-31', InventoryDateParser::lastDayOfMonth('agosto-2028'));
        $this->assertSame('2028-07-31', InventoryDateParser::lastDayOfMonth('07-2028'));
        $this->assertSame('2028-08-31', InventoryDateParser::lastDayOfMonth('agosto 2028'));
        $this->assertSame('2028-08-31', InventoryDateParser::lastDayOfMonth('ago-2028'));
        $this->assertSame('2028-12-31', InventoryDateParser::lastDayOfMonth('dic-2028'));
    }

    public function test_splits_product_and_variant(): void
    {
        $split = ProductVariantNormalizer::split('Biomec 1% L.A. — 100 mL', '');

        $this->assertSame('Biomec 1%', $split['nombre']);
        $this->assertSame('L.A. — 100 mL', $split['presentation']);
    }

    public function test_splits_presentation_and_size_from_name(): void
    {
        $shampoo = \App\Support\ProductNameSplitter::split('SHAMPOO 250 ML');
        $this->assertSame('Shampoo', $shampoo['nombre']);
        $this->assertSame('250 ml', $shampoo['presentacion']);
        $this->assertSame('ml', $shampoo['unidad_medida']);

        $correa = \App\Support\ProductNameSplitter::split('CORREA TALLA L');
        $this->assertSame('Correa', $correa['nombre']);
        $this->assertSame('L', $correa['talla']);

        $this->assertSame('2027-03-31', InventoryDateParser::lastDayOfMonth('03-2027'));
        $this->assertSame('2026-08-31', InventoryDateParser::lastDayOfMonth('08-2026'));
    }
}
