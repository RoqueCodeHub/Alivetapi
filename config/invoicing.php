<?php

return [
    'igv' => (float) env('INVOICE_IGV', 18),
    'tax_included' => (bool) env('INVOICE_TAX_INCLUDED', true),
    'currency' => env('INVOICE_CURRENCY', 'PEN'),
    'driver' => env('INVOICE_PSE_DRIVER', 'fake'),
    'boleta_without_doc_max' => (float) env('INVOICE_BOLETA_SIN_DOC_MAX', 700),
    'http' => [
        'url' => env('INVOICE_PSE_URL'),
        'token' => env('INVOICE_PSE_TOKEN'),
        'timeout' => (int) env('INVOICE_PSE_TIMEOUT', 20),
    ],
    'series' => env('INVOICE_SERIES', 'B001'),
    'issuer' => [
        'ruc' => env('INVOICE_ISSUER_RUC') ?: '20611213175',
        'razon_social' => env('INVOICE_ISSUER_NAME') ?: 'Veterinaria Ali-Vet',
        'address' => env('INVOICE_ISSUER_ADDRESS') ?: 'Iberia',
        'phone' => env('INVOICE_ISSUER_PHONE'),
        'ubigeo' => env('INVOICE_ISSUER_UBIGEO'),
        'urbanizacion' => env('INVOICE_ISSUER_URBANIZACION'),
        'distrito' => env('INVOICE_ISSUER_DISTRICT'),
        'provincia' => env('INVOICE_ISSUER_PROVINCE'),
        'departamento' => env('INVOICE_ISSUER_DEPARTMENT'),
    ],
];
