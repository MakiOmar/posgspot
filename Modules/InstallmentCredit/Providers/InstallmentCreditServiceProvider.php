<?php

namespace Modules\InstallmentCredit\Providers;

use App\Events\TransactionPaymentAdded;
use App\Events\TransactionPaymentDeleted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\InstallmentCredit\Listeners\CancelReceivableOnPaymentDeleted;
use Modules\InstallmentCredit\Listeners\CreateReceivableFromPayment;

class InstallmentCreditServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Event::listen(TransactionPaymentAdded::class, CreateReceivableFromPayment::class);
        Event::listen(TransactionPaymentDeleted::class, CancelReceivableOnPaymentDeleted::class);
    }

    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerConfig()
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/config.php', 'installmentcredit');
    }

    public function registerViews()
    {
        $sourcePath = __DIR__.'/../Resources/views';
        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path.'/modules/installmentcredit';
        }, config('view.paths')), [$sourcePath]), 'installmentcredit');
    }

    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/installmentcredit');
        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'installmentcredit');
        } else {
            $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'installmentcredit');
        }
    }
}
