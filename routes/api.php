<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\ProveedorController;
use App\Http\Controllers\Api\MovimientoStockController;
use App\Http\Controllers\Api\VentaController;
use App\Http\Controllers\Api\TrabajadorController;
use App\Http\Controllers\Api\CategoriaController;
use App\Http\Controllers\Api\ClienteController;

// Rutas públicas
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Rutas protegidas con JWT
Route::middleware('auth:api')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });

    // Categorías
    Route::prefix('categorias')->group(function () {
        Route::get('/', [CategoriaController::class, 'index']);
        Route::post('/', [CategoriaController::class, 'store']);
        Route::get('/{id}', [CategoriaController::class, 'show']);
        Route::put('/{id}', [CategoriaController::class, 'update']);
        Route::delete('/{id}', [CategoriaController::class, 'destroy']);
    });

    // Clientes
    Route::prefix('clientes')->group(function () {
        Route::get('/', [ClienteController::class, 'index']);
        Route::post('/', [ClienteController::class, 'store']);
        Route::get('/{id}', [ClienteController::class, 'show']);
        Route::put('/{id}', [ClienteController::class, 'update']);
        Route::delete('/{id}', [ClienteController::class, 'destroy']);
    });

    // Trabajadores
    Route::prefix('trabajadores')->group(function () {
        Route::get('/', [TrabajadorController::class, 'index']);
        Route::post('/', [TrabajadorController::class, 'store']);
        Route::get('/{id}', [TrabajadorController::class, 'show']);
        Route::put('/{id}', [TrabajadorController::class, 'update']);
        Route::delete('/{id}', [TrabajadorController::class, 'destroy']);
    });

    // Proveedores
    Route::prefix('proveedores')->group(function () {
        Route::get('/', [ProveedorController::class, 'index']);
        Route::post('/', [ProveedorController::class, 'store']);
        Route::get('/{id}', [ProveedorController::class, 'show']);
        Route::put('/{id}', [ProveedorController::class, 'update']);
        Route::delete('/{id}', [ProveedorController::class, 'destroy']);
    });

    // Movimientos de stock
    Route::prefix('movimientos-stock')->group(function () {
        Route::get('/', [MovimientoStockController::class, 'index']);
        Route::post('/', [MovimientoStockController::class, 'store']);
        Route::get('/producto/{id}', [MovimientoStockController::class, 'porProducto']);
        Route::get('/{id}', [MovimientoStockController::class, 'show']);
    });

    // Ventas
    Route::prefix('ventas')->group(function () {
        Route::get('/', [VentaController::class, 'index']);
        Route::post('/', [VentaController::class, 'store']);
        Route::get('/{id}', [VentaController::class, 'show']);
        Route::patch('/{id}/cobrar', [VentaController::class, 'cobrar']);
        Route::patch('/{id}/anular', [VentaController::class, 'anular']);
    });

    // Productos
    Route::prefix('productos')->group(function () {
        Route::get('/', [ProductoController::class, 'index']);
        Route::post('/', [ProductoController::class, 'store']);
        Route::get('/stock-bajo', [ProductoController::class, 'stockBajo']);
        Route::get('/por-vencer', [ProductoController::class, 'porVencer']);
        Route::get('/{id}', [ProductoController::class, 'show']);
        Route::put('/{id}', [ProductoController::class, 'update']);
        Route::delete('/{id}', [ProductoController::class, 'destroy']);
    });

});
