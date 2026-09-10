<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\BusinessDashboardController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceInstallmentController;
use App\Http\Controllers\InvoiceItemWriterController;
use App\Http\Controllers\OwnershipController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PersonalExpenseController;
use App\Http\Controllers\PersonalIncomeEntryController;
use App\Http\Controllers\PersonalIncomeSourceController;
use App\Http\Controllers\PersonalOverviewController;
use App\Http\Controllers\PortfolioDashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfitDistributionController;
use App\Http\Controllers\ProfitPeriodController;
use App\Http\Controllers\ProfitWithdrawalController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectProfitApprovalController;
use App\Http\Controllers\ProjectRefundController;
use App\Http\Controllers\ProjectWriterAssignmentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\SignatureController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WriterController;
use App\Http\Controllers\WriterPaymentController;
use App\Http\Controllers\WriterSelfController;
use Illuminate\Support\Facades\Route;

Route::get('/public/businesses/{business}/logo', [BusinessController::class, 'logo']);
Route::get('/public/users/{user}/signature', [SignatureController::class, 'show']);

Route::prefix('auth')->middleware('throttle:10,1')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::post('/profile/signature', [SignatureController::class, 'store']);
    Route::delete('/profile/signature', [SignatureController::class, 'destroy']);

    Route::get('/portfolio/dashboard', PortfolioDashboardController::class);
    Route::get('/businesses', [BusinessController::class, 'index']);
    Route::post('/businesses', [BusinessController::class, 'store']);

    Route::get('/writer/dashboard', [WriterSelfController::class, 'dashboard']);

    Route::prefix('personal')->group(function (): void {
        Route::get('/overview', PersonalOverviewController::class);
        Route::get('/income-sources', [PersonalIncomeSourceController::class, 'index']);
        Route::post('/income-sources', [PersonalIncomeSourceController::class, 'store']);
        Route::patch('/income-sources/{source}', [PersonalIncomeSourceController::class, 'update']);
        Route::delete('/income-sources/{source}', [PersonalIncomeSourceController::class, 'destroy']);
        Route::get('/income-entries', [PersonalIncomeEntryController::class, 'index']);
        Route::post('/income-entries', [PersonalIncomeEntryController::class, 'store']);
        Route::delete('/income-entries/{entry}', [PersonalIncomeEntryController::class, 'destroy']);
        Route::get('/expenses', [PersonalExpenseController::class, 'index']);
        Route::post('/expenses', [PersonalExpenseController::class, 'store']);
        Route::patch('/expenses/{expense}', [PersonalExpenseController::class, 'update']);
        Route::delete('/expenses/{expense}', [PersonalExpenseController::class, 'destroy']);
    });

    Route::prefix('businesses/{business}')
        ->middleware('business.access')
        ->scopeBindings()
        ->group(function (): void {
            Route::get('/', [BusinessController::class, 'show']);
            Route::patch('/', [BusinessController::class, 'update']);
            Route::delete('/', [BusinessController::class, 'destroy']);
            Route::post('/branding/logo', [BusinessController::class, 'uploadLogo']);
            Route::delete('/branding/logo', [BusinessController::class, 'destroyLogo']);
            Route::get('/dashboard', BusinessDashboardController::class);

            Route::apiResource('customers', CustomerController::class);
            Route::apiResource('products', ProductController::class)->except(['show']);
            Route::apiResource('writers', WriterController::class);
            Route::post('/writers/{writer}/payments', [WriterPaymentController::class, 'store']);

            Route::get('/invoices', [InvoiceController::class, 'index']);
            Route::get('/invoices/export', [InvoiceController::class, 'export']);
            Route::post('/invoices', [InvoiceController::class, 'store']);
            Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
            Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue']);
            Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);
            Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy']);
            Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store']);
            Route::get('/invoices/{invoice}/installments', [InvoiceInstallmentController::class, 'index']);
            Route::put('/invoices/{invoice}/installments', [InvoiceInstallmentController::class, 'store']);
            Route::get('/invoices/{invoice}/items/{item}/writers', [InvoiceItemWriterController::class, 'index']);
            Route::post('/invoices/{invoice}/items/{item}/writers', [InvoiceItemWriterController::class, 'store']);

            Route::apiResource('projects', ProjectController::class)->except(['show']);
            Route::get('/projects/{project}', [ProjectController::class, 'show']);
            Route::post('/projects/{project}/profit-approvals', [ProjectProfitApprovalController::class, 'store']);
            Route::post('/projects/{project}/refunds', [ProjectRefundController::class, 'store']);
            Route::get('/projects/{project}/writer', [ProjectWriterAssignmentController::class, 'index']);
            Route::post('/projects/{project}/writer', [ProjectWriterAssignmentController::class, 'store']);

            Route::get('/expenses', [ExpenseController::class, 'index']);
            Route::get('/expenses/export', [ExpenseController::class, 'export']);
            Route::post('/expenses', [ExpenseController::class, 'store']);
            Route::patch('/expenses/{expense}', [ExpenseController::class, 'update']);
            Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);
            Route::patch('/expenses/{expense}/status', [ExpenseController::class, 'updateStatus']);

            Route::get('/team', [TeamController::class, 'index']);
            Route::get('/team/export', [TeamController::class, 'export']);
            Route::post('/team', [TeamController::class, 'store']);
            Route::get('/team/{membership}', [TeamController::class, 'show']);
            Route::patch('/team/{membership}', [TeamController::class, 'update']);
            Route::delete('/team/{membership}', [TeamController::class, 'destroy']);
            Route::post('/team/{membership}/reset-password', [TeamController::class, 'resetPassword']);
            Route::get('/team/{membership}/salary', [SalaryController::class, 'index']);
            Route::post('/team/{membership}/salary/payments', [SalaryController::class, 'pay']);
            Route::patch('/team/{membership}/salary/payments/{payment}', [SalaryController::class, 'update'])->withoutScopedBindings();
            Route::delete('/team/{membership}/salary/payments/{payment}', [SalaryController::class, 'destroy'])->withoutScopedBindings();
            Route::post('/team/{membership}/salary/write-off', [SalaryController::class, 'writeOffLoan']);
            Route::get('/my-salary', [SalaryController::class, 'mine']);
            Route::get('/payroll-payments', [SalaryController::class, 'businessIndex']);

            Route::get('/ownerships', [OwnershipController::class, 'index']);
            Route::post('/ownerships', [OwnershipController::class, 'store']);

            Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss']);
            Route::get('/profit-periods', [ProfitPeriodController::class, 'index']);
            Route::post('/profit-periods', [ProfitPeriodController::class, 'store']);
            Route::get('/profit-distributions', [ProfitDistributionController::class, 'index']);
            Route::post('/profit-distributions', [ProfitDistributionController::class, 'store']);
            Route::get('/profit-withdrawals', [ProfitWithdrawalController::class, 'index']);
            Route::post('/profit-withdrawals', [ProfitWithdrawalController::class, 'store']);
        });
});
