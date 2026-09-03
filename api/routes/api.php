<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\BusinessDashboardController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OwnershipController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PortfolioDashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfitDistributionController;
use App\Http\Controllers\ProfitPeriodController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/public/businesses/{business}/logo', [BusinessController::class, 'logo']);

Route::prefix('auth')->middleware('throttle:10,1')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/portfolio/dashboard', PortfolioDashboardController::class);
    Route::get('/businesses', [BusinessController::class, 'index']);
    Route::post('/businesses', [BusinessController::class, 'store']);

    Route::prefix('businesses/{business}')
        ->middleware('business.access')
        ->scopeBindings()
        ->group(function (): void {
            Route::get('/', [BusinessController::class, 'show']);
            Route::patch('/', [BusinessController::class, 'update']);
            Route::post('/branding/logo', [BusinessController::class, 'uploadLogo']);
            Route::delete('/branding/logo', [BusinessController::class, 'destroyLogo']);
            Route::get('/dashboard', BusinessDashboardController::class);

            Route::apiResource('customers', CustomerController::class)->except(['show']);
            Route::apiResource('products', ProductController::class)->except(['show']);

            Route::get('/invoices', [InvoiceController::class, 'index']);
            Route::post('/invoices', [InvoiceController::class, 'store']);
            Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
            Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue']);
            Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);
            Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store']);

            Route::get('/expenses', [ExpenseController::class, 'index']);
            Route::post('/expenses', [ExpenseController::class, 'store']);
            Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);
            Route::patch('/expenses/{expense}/status', [ExpenseController::class, 'updateStatus']);

            Route::get('/team', [TeamController::class, 'index']);
            Route::post('/team', [TeamController::class, 'store']);
            Route::patch('/team/{membership}', [TeamController::class, 'update']);

            Route::get('/ownerships', [OwnershipController::class, 'index']);
            Route::post('/ownerships', [OwnershipController::class, 'store']);

            Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss']);
            Route::get('/profit-periods', [ProfitPeriodController::class, 'index']);
            Route::post('/profit-periods', [ProfitPeriodController::class, 'store']);
            Route::get('/profit-distributions', [ProfitDistributionController::class, 'index']);
            Route::post('/profit-distributions', [ProfitDistributionController::class, 'store']);
        });
});
