<?php

namespace Modules\InstallmentCredit\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    public function superadmin_package()
    {
        return [
            [
                'name' => 'installment_credit_module',
                'label' => __('installmentcredit::lang.installment_credit_module'),
                'default' => false,
            ],
        ];
    }

    public function user_permissions()
    {
        return [
            [
                'value' => 'installment.view',
                'label' => __('installmentcredit::lang.permission_view'),
                'default' => false,
            ],
            [
                'value' => 'installment.companies',
                'label' => __('installmentcredit::lang.permission_companies'),
                'default' => false,
            ],
            [
                'value' => 'installment.settle',
                'label' => __('installmentcredit::lang.permission_settle'),
                'default' => false,
            ],
            [
                'value' => 'installment.reports',
                'label' => __('installmentcredit::lang.permission_reports'),
                'default' => false,
            ],
            [
                'value' => 'installment.import',
                'label' => __('installmentcredit::lang.permission_import'),
                'default' => false,
            ],
        ];
    }

    public function modifyAdminMenu()
    {
        $moduleUtil = new ModuleUtil();
        if (! $moduleUtil->isModuleInstalled('InstallmentCredit')) {
            return;
        }

        if (! (auth()->user()->can('superadmin')
            || auth()->user()->can('installment.view')
            || auth()->user()->can('installment.companies')
            || auth()->user()->can('installment.settle')
            || auth()->user()->can('installment.reports'))) {
            return;
        }

        Menu::modify('admin-sidebar-menu', function ($menu) {
            $menu->dropdown(
                __('installmentcredit::lang.installment_credit'),
                function ($sub) {
                    if (auth()->user()->can('superadmin') || auth()->user()->can('installment.view') || auth()->user()->can('installment.settle')) {
                        $sub->url(
                            action([\Modules\InstallmentCredit\Http\Controllers\ReceivableController::class, 'index']),
                            __('installmentcredit::lang.pending_receivables'),
                            ['icon' => 'fa fas fa-list', 'active' => request()->segment(1) == 'installment-credit' && request()->segment(2) == 'receivables']
                        );
                    }
                    if (auth()->user()->can('superadmin') || auth()->user()->can('installment.companies')) {
                        $sub->url(
                            action([\Modules\InstallmentCredit\Http\Controllers\CompanyController::class, 'index']),
                            __('installmentcredit::lang.companies'),
                            ['icon' => 'fa fas fa-building', 'active' => request()->segment(1) == 'installment-credit' && request()->segment(2) == 'companies']
                        );
                    }
                    if (auth()->user()->can('superadmin') || auth()->user()->can('installment.settle')) {
                        $sub->url(
                            action([\Modules\InstallmentCredit\Http\Controllers\SettlementController::class, 'index']),
                            __('installmentcredit::lang.settlements'),
                            ['icon' => 'fa fas fa-handshake', 'active' => request()->segment(1) == 'installment-credit' && request()->segment(2) == 'settlements']
                        );
                    }
                    if (auth()->user()->can('superadmin') || auth()->user()->can('installment.reports')) {
                        $sub->url(
                            action([\Modules\InstallmentCredit\Http\Controllers\ReportController::class, 'index']),
                            __('installmentcredit::lang.reports'),
                            ['icon' => 'fa fas fa-chart-bar', 'active' => request()->segment(1) == 'installment-credit' && request()->segment(2) == 'reports']
                        );
                    }
                    if (auth()->user()->can('superadmin') || auth()->user()->can('installment.import')) {
                        $sub->url(
                            action([\Modules\InstallmentCredit\Http\Controllers\ImportController::class, 'index']),
                            __('installmentcredit::lang.import_excel'),
                            ['icon' => 'fa fas fa-file-excel', 'active' => request()->segment(1) == 'installment-credit' && request()->segment(2) == 'import']
                        );
                    }
                    if (auth()->user()->can('superadmin') || auth()->user()->can('installment.view')) {
                        $sub->url(
                            action([\Modules\InstallmentCredit\Http\Controllers\DashboardController::class, 'index']),
                            __('installmentcredit::lang.dashboard'),
                            ['icon' => 'fa fas fa-tachometer-alt', 'active' => request()->segment(1) == 'installment-credit' && (request()->segment(2) == 'dashboard' || request()->segment(2) == null)]
                        );
                    }
                },
                [
                    'icon' => '<svg aria-hidden="true" class="tw-size-5 tw-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                        <path d="M17 8v-3a1 1 0 0 0 -1 -1h-10a2 2 0 0 0 0 4h12a1 1 0 0 1 1 1v3m0 4v3a1 1 0 0 1 -1 1h-12a2 2 0 0 1 -2 -2v-12"></path>
                        <path d="M20 12v4"></path>
                      </svg>',
                    'active' => request()->segment(1) == 'installment-credit',
                ]
            )->order(26);
        });
    }

    /**
     * Widget for home dashboard (when widget slots are enabled in the theme).
     */
    public function dashboard_widget()
    {
        $moduleUtil = new ModuleUtil();
        if (! $moduleUtil->isModuleInstalled('InstallmentCredit')) {
            return null;
        }
        if (! (auth()->user()->can('superadmin') || auth()->user()->can('installment.view'))) {
            return null;
        }

        return [
            'position' => 'after_dashboard_reports',
            'widget' => view('installmentcredit::dashboard.widget')->render(),
        ];
    }
}
