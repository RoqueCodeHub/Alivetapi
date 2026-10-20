<?php

use App\Http\Controllers\Api\V1\AlertController;
use App\Http\Controllers\Api\V1\AuditController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CashSessionController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ClinicController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InventoryImportController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\PetController;
use App\Http\Controllers\Api\V1\PortalController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\ReminderController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Api\V1\StockCountController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('portal')->group(function () {
    Route::post('login', [PortalController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [PortalController::class, 'me']);
        Route::get('pets', [PortalController::class, 'pets']);
        Route::get('pets/{pet}/carnet', [PortalController::class, 'carnet']);
        Route::get('appointments', [PortalController::class, 'appointments']);
        Route::post('appointments', [PortalController::class, 'requestAppointment']);
        Route::get('purchases', [PortalController::class, 'purchases']);
        Route::get('services', [PortalController::class, 'services']);
    });
});

Route::get('health', HealthController::class);

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::put('password', [AuthController::class, 'changePassword']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('roles', [UserController::class, 'roles']);
    Route::get('users', [UserController::class, 'index']);
    Route::post('users', [UserController::class, 'store']);
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::put('users/{user}', [UserController::class, 'update']);
    Route::patch('users/{user}/activate', [UserController::class, 'activate']);
    Route::patch('users/{user}/deactivate', [UserController::class, 'deactivate']);

    Route::get('categories', [CategoryController::class, 'index']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::put('categories/{categoria}', [CategoryController::class, 'update']);
    Route::delete('categories/{categoria}', [CategoryController::class, 'destroy']);

    Route::get('brands', [BrandController::class, 'index']);
    Route::post('brands', [BrandController::class, 'store']);
    Route::put('brands/{marca}', [BrandController::class, 'update']);

    Route::get('products', [ProductController::class, 'index']);
    Route::post('products', [ProductController::class, 'store']);
    Route::get('products/{producto}', [ProductController::class, 'show']);
    Route::put('products/{producto}', [ProductController::class, 'update']);
    Route::delete('products/{producto}', [ProductController::class, 'destroy']);
    Route::post('products/{producto}/variants', [ProductController::class, 'storeVariant']);
    Route::put('variants/{variante}', [ProductController::class, 'updateVariant']);

    Route::get('import/inventory/template', [InventoryImportController::class, 'template']);
    Route::post('import/inventory', [InventoryImportController::class, 'store']);
    Route::get('inventory/pending-review', [InventoryImportController::class, 'pending']);
    Route::put('inventory/pending-review/{variante}', [InventoryImportController::class, 'resolve']);

    Route::get('stock', [StockController::class, 'index']);
    Route::get('stock/export', [StockController::class, 'export']);
    Route::post('stock/receipts', [StockController::class, 'receive']);
    Route::post('stock/adjustments', [StockController::class, 'adjust']);
    Route::post('stock/open-box', [StockController::class, 'openBox']);
    Route::get('stock/counts', [StockCountController::class, 'index']);
    Route::post('stock/counts', [StockCountController::class, 'store']);
    Route::get('stock/counts/{stockCount}', [StockCountController::class, 'show']);
    Route::put('stock/counts/{stockCount}/lines', [StockCountController::class, 'record']);
    Route::post('stock/counts/{stockCount}/approve', [StockCountController::class, 'approve']);
    Route::get('stock/{variante}', [StockController::class, 'show']);
    Route::get('variants/{variante}/kardex', [StockController::class, 'kardex']);
    Route::get('variants/{variante}/kardex/export', [StockController::class, 'exportKardex']);
    Route::get('variants/{variante}/fefo', [StockController::class, 'fefo']);
    Route::get('alerts/expiring', [AlertController::class, 'expiring']);
    Route::get('alerts/low-stock', [AlertController::class, 'lowStock']);

    Route::get('suppliers', [SupplierController::class, 'index']);
    Route::post('suppliers', [SupplierController::class, 'store']);
    Route::put('suppliers/{proveedor}', [SupplierController::class, 'update']);

    Route::get('purchases/payables', [PurchaseController::class, 'payables']);
    Route::get('purchases', [PurchaseController::class, 'index']);
    Route::post('purchases', [PurchaseController::class, 'store']);
    Route::get('purchases/{purchase}', [PurchaseController::class, 'show']);
    Route::put('purchases/{purchase}', [PurchaseController::class, 'update']);
    Route::post('purchases/{purchase}/confirm', [PurchaseController::class, 'confirm']);
    Route::post('purchases/{purchase}/payments', [PurchaseController::class, 'pay']);
    Route::get('variants/{variante}/cost-history', [PurchaseController::class, 'costHistory']);

    Route::get('pos/search', [SaleController::class, 'search']);
    Route::get('cash-sessions/current', [CashSessionController::class, 'current']);
    Route::post('cash-sessions/open', [CashSessionController::class, 'open']);
    Route::post('cash-sessions/{cashSession}/close', [CashSessionController::class, 'close']);
    Route::get('sales', [SaleController::class, 'index']);
    Route::post('sales', [SaleController::class, 'store']);
    Route::post('sales/{sale}/pay', [SaleController::class, 'pay']);
    Route::get('sales/{sale}', [SaleController::class, 'show']);
    Route::get('sales/{sale}/receipt', [SaleController::class, 'receipt']);
    Route::post('sales/{sale}/void', [SaleController::class, 'void']);
    Route::post('sales/{sale}/returns', [SaleController::class, 'returnSale']);

    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::get('invoices/series', [InvoiceController::class, 'series']);
    Route::get('invoices/requirements', [InvoiceController::class, 'requirements']);
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::post('sales/{sale}/invoices', [InvoiceController::class, 'emit']);
    Route::post('invoices/{invoice}/retry', [InvoiceController::class, 'retry']);

    Route::get('customers', [CustomerController::class, 'index']);
    Route::post('customers', [CustomerController::class, 'store']);
    Route::get('customers/{cliente}', [CustomerController::class, 'show']);
    Route::put('customers/{cliente}', [CustomerController::class, 'update']);
    Route::post('customers/{cliente}/portal', [PortalController::class, 'enable']);
    Route::get('customers/{cliente}/purchases', [CustomerController::class, 'sales']);
    Route::post('customers/{cliente}/pets', [CustomerController::class, 'storePet']);
    Route::get('pets/{pet}', [PetController::class, 'show']);
    Route::put('pets/{pet}', [PetController::class, 'update']);
    Route::post('pets/{pet}/photo', [PetController::class, 'photo']);

    Route::get('reports/dashboard', [ReportController::class, 'dashboard']);
    Route::get('reports/sales-by-day', [ReportController::class, 'salesByDay']);
    Route::get('reports/top-products', [ReportController::class, 'topProducts']);
    Route::get('reports/by-category', [ReportController::class, 'byCategory']);
    Route::get('reports/by-cashier', [ReportController::class, 'byCashier']);
    Route::get('reports/profit', [ReportController::class, 'profit']);
    Route::get('reports/slow-movers', [ReportController::class, 'slowMovers']);
    Route::get('reports/inventory-value', [ReportController::class, 'inventoryValue']);
    Route::get('reports/export', [ReportController::class, 'export']);

    Route::get('services', [ClinicController::class, 'services']);
    Route::post('services', [ClinicController::class, 'storeService']);
    Route::put('services/{vetService}', [ClinicController::class, 'updateService']);
    Route::get('appointments', [ClinicController::class, 'appointments']);
    Route::post('appointments', [ClinicController::class, 'storeAppointment']);
    Route::get('appointments/{appointment}', [ClinicController::class, 'showAppointment']);
    Route::put('appointments/{appointment}', [ClinicController::class, 'updateAppointment']);
    Route::post('appointments/{appointment}/cancel', [ClinicController::class, 'cancelAppointment']);
    Route::post('appointments/{appointment}/attend', [ClinicController::class, 'attend']);
    Route::post('appointments/{appointment}/consumptions', [ClinicController::class, 'consume']);
    Route::post('appointments/{appointment}/charge', [ClinicController::class, 'charge']);
    Route::get('pets/{pet}/clinical-history', [ClinicController::class, 'petHistory']);
    Route::post('pets/{pet}/medical-records', [ClinicController::class, 'storeRecord']);
    Route::get('medical-records/{medicalRecord}', [ClinicController::class, 'showRecord']);
    Route::put('medical-records/{medicalRecord}', [ClinicController::class, 'updateRecord']);
    Route::post('medical-records/{medicalRecord}/attachments', [ClinicController::class, 'attachRecord']);
    Route::post('pets/{pet}/vaccinations', [ClinicController::class, 'storeVaccination']);
    Route::put('vaccinations/{vaccination}', [ClinicController::class, 'updateVaccination']);
    Route::post('pets/{pet}/dewormings', [ClinicController::class, 'storeDeworming']);
    Route::put('dewormings/{deworming}', [ClinicController::class, 'updateDeworming']);

    Route::get('reminders', [ReminderController::class, 'index']);
    Route::post('reminders/{reminder}/dismiss', [ReminderController::class, 'dismiss']);

    Route::get('audit-logs', [AuditController::class, 'logs']);
    Route::get('login-logs', [AuditController::class, 'logins']);
    Route::get('backups', [AuditController::class, 'backups']);
    Route::get('security-status', [AuditController::class, 'security']);
});
