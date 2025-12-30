<?php

namespace Modules\Woocommerce\Console;

use App\Business;
use DB;
use Illuminate\Console\Command;
use Modules\Woocommerce\Utils\WoocommerceUtil;
use Symfony\Component\Console\Input\InputArgument;

class WooCommerceForceSyncOrder extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'pos:WooCommerceForceSyncOrder {business_id} {woocommerce_order_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Force sync a specific order from WooCommerce to POS';

    /**
     * All Utils instance.
     */
    protected $woocommerceUtil;

    /**
     * Create a new command instance.
     *
     * @param  WoocommerceUtil  $woocommerceUtil
     * @return void
     */
    public function __construct(WoocommerceUtil $woocommerceUtil)
    {
        parent::__construct();

        $this->woocommerceUtil = $woocommerceUtil;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            $business_id = $this->argument('business_id');
            $woocommerce_order_id = $this->argument('woocommerce_order_id');

            $business = Business::findOrFail($business_id);
            $owner_id = $business->owner_id;

            //Set timezone to business timezone
            $timezone = $business->time_zone;
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);

            $this->info("Force syncing WooCommerce order #{$woocommerce_order_id} for business #{$business_id}...");

            $result = $this->woocommerceUtil->forceSyncOrder($business_id, $owner_id, $woocommerce_order_id);

            if ($result['success']) {
                $this->info($result['msg']);
                return 0;
            } else {
                $this->error($result['msg']);
                if (isset($result['error'])) {
                    $this->error(json_encode($result['error']));
                }
                return 1;
            }

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            return 1;
        }
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['business_id', InputArgument::REQUIRED, 'ID of the business'],
            ['woocommerce_order_id', InputArgument::REQUIRED, 'WooCommerce Order ID to force sync'],
        ];
    }
}

