<?php

use Illuminate\Support\Facades\Route;
use Modules\InstallmentCredit\Http\Controllers\CompanyController;
use Modules\InstallmentCredit\Http\Controllers\DashboardController;
use Modules\InstallmentCredit\Http\Controllers\ImportController;
use Modules\InstallmentCredit\Http\Controllers\InstallController;
use Modules\InstallmentCredit\Http\Controllers\ReceivableController;
use Modules\InstallmentCredit\Http\Controllers\ReportController;
use Modules\InstallmentCredit\Http\Controllers\SettlementController;

Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('installment-credit')
    ->group(function () {
        Route::get('/install', [InstallController::class, 'index']);
        Route::post('/install', [InstallController::class, 'install']);

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('installment-credit.dashboard');

        Route::post('/companies/seed-defaults', [CompanyController::class, 'seedDefaults']);
        Route::post('/companies/remap-payments', [CompanyController::class, 'remapPayments']);
        Route::resource('companies', CompanyController::class)->except(['show']);

        Route::get('/receivables', [ReceivableController::class, 'index']);
        Route::get('/receivables/create', [ReceivableController::class, 'create']);
        Route::post('/receivables', [ReceivableController::class, 'store']);
        Route::get('/receivables/create-settlement', [ReceivableController::class, 'createSettlement']);
        Route::post('/receivables/settle', [ReceivableController::class, 'storeSettlement']);

        Route::get('/settlements', [SettlementController::class, 'index']);
        Route::get('/settlements/{id}', [SettlementController::class, 'show']);

        Route::get('/reports', [ReportController::class, 'index']);
        Route::get('/reports/branch-company', [ReportController::class, 'branchCompany']);
        Route::get('/reports/aging', [ReportController::class, 'aging']);
        Route::get('/reports/export-pending', [ReportController::class, 'exportPending']);

        Route::get('/import', [ImportController::class, 'index']);
        Route::post('/import', [ImportController::class, 'store']);
        Route::get('/import/template', [ImportController::class, 'template']);
        Route::get('/import/template-xlsx', [ImportController::class, 'templateXlsx']);
        Route::post('/import/ids', [ImportController::class, 'storeIds']);
        Route::get('/import/ids-template', [ImportController::class, 'idsTemplate']);
        Route::get('/import/ids-template-xlsx', [ImportController::class, 'idsTemplateXlsx']);
    });
