<?php

namespace App\Utils;

use App\Barcode;
use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\Currency;
use App\InvoiceLayout;
use App\InvoiceScheme;
use App\NotificationTemplate;
use App\Printer;
use App\Unit;
use App\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\VariationLocationDetails;


class BusinessUtil extends Util
{
    /**
     * Adds a default settings/resources for a new business
     *
     * @param  int  $business_id
     * @param  int  $user_id
     * @return bool
     */
    public function newBusinessDefaultResources($business_id, $user_id)
    {
        $user = User::find($user_id);

        //create Admin role and assign to user
        $role = Role::create(['name' => 'Admin#'.$business_id,
            'business_id' => $business_id,
            'guard_name' => 'web', 'is_default' => 1,
        ]);
        $user->assignRole($role->name);

        //Create Cashier role for a new business
        $cashier_role = Role::create(['name' => 'Cashier#'.$business_id,
            'business_id' => $business_id,
            'guard_name' => 'web',
        ]);
        $cashier_role->syncPermissions(['sell.view', 'sell.create', 'sell.update', 'sell.delete', 'access_all_locations', 'view_cash_register', 'close_cash_register']);

        $business = Business::findOrFail($business_id);

        //Update reference count
        $ref_count = $this->setAndGetReferenceCount('contacts', $business_id);
        $contact_id = $this->generateReferenceNumber('contacts', $ref_count, $business_id);

        //Add Default/Walk-In Customer for new business
        $customer = [
            'business_id' => $business_id,
            'type' => 'customer',
            'name' => 'Walk-In Customer',
            'created_by' => $user_id,
            'is_default' => 1,
            'contact_id' => $contact_id,
            'credit_limit' => 0,
        ];
        Contact::create($customer);

        //create default invoice setting for new business
        InvoiceScheme::create(['name' => 'Default',
            'scheme_type' => 'blank',
            'prefix' => '',
            'start_number' => 1,
            'total_digits' => 4,
            'is_default' => 1,
            'business_id' => $business_id,
        ]);
        //create default invoice layour for new business
        InvoiceLayout::create(['name' => 'Default',
            'header_text' => null,
            'invoice_no_prefix' => 'Invoice No.',
            'invoice_heading' => 'Invoice',
            'sub_total_label' => 'Subtotal',
            'discount_label' => 'Discount',
            'tax_label' => 'Tax',
            'total_label' => 'Total',
            'show_landmark' => 1,
            'show_city' => 1,
            'show_state' => 1,
            'show_zip_code' => 1,
            'show_country' => 1,
            'highlight_color' => '#000000',
            'footer_text' => '',
            'is_default' => 1,
            'business_id' => $business_id,
            'invoice_heading_not_paid' => '',
            'invoice_heading_paid' => '',
            'total_due_label' => 'Total Due',
            'paid_label' => 'Total Paid',
            'show_payments' => 1,
            'show_customer' => 1,
            'customer_label' => 'Customer',
            'table_product_label' => 'Product',
            'table_qty_label' => 'Quantity',
            'table_unit_price_label' => 'Unit Price',
            'table_subtotal_label' => 'Subtotal',
            'date_label' => 'Date',
        ]);

        //create default barcode setting for new business
        // Barcode::create(['name' => 'Default',
        //                 'description' => '',
        //                 'width' => 37.29,
        //                 'height' => 25.93,
        //                 'top_margin' => 5,
        //                 'left_margin' => 5,
        //                 'row_distance' => 1,
        //                 'col_distance' => 1,
        //                 'stickers_in_one_row' => 4,
        //                 'is_default' => 1,
        //                 'business_id' => $business_id
        //             ]);

        //Add Default Unit for new business
        $unit = [
            'business_id' => $business_id,
            'actual_name' => 'Pieces',
            'short_name' => 'Pc(s)',
            'allow_decimal' => 0,
            'created_by' => $user_id,
        ];
        Unit::create($unit);

        //Create default notification templates
        $notification_templates = NotificationTemplate::defaultNotificationTemplates($business_id);
        foreach ($notification_templates as $notification_template) {
            NotificationTemplate::create($notification_template);
        }

        return true;
    }

    /**
     * Gives a list of all currencies
     *
     * @return array
     */
    public function allCurrencies()
    {
        $currencies = Currency::select('id', DB::raw("concat(country, ' - ',currency, '(', code, ') ') as info"))
                ->orderBy('country')
                ->pluck('info', 'id');

        return $currencies;
    }

    /**
     * Gives a list of all timezone
     *
     * @return array
     */
    public function allTimeZones()
    {
        $datetime = new \DateTimeZone('EDT');

        $timezones = $datetime->listIdentifiers();
        $timezone_list = [];
        foreach ($timezones as $timezone) {
            $timezone_list[$timezone] = $timezone;
        }

        return $timezone_list;
    }

    /**
     * Gives a list of all accouting methods
     *
     * @return array
     */
    public function allAccountingMethods()
    {
        return [
            'fifo' => __('business.fifo'),
            'lifo' => __('business.lifo'),
        ];
    }

    /**
     * Creates new business with default settings.
     *
     * @return array
     */
    public function createNewBusiness($business_details)
    {
        $business_details['sell_price_tax'] = 'includes';

        $business_details['default_profit_percent'] = 25;

        //Add POS shortcuts
        $business_details['keyboard_shortcuts'] = '{"pos":{"express_checkout":"shift+e","pay_n_ckeckout":"shift+p","draft":"shift+d","cancel":"shift+c","edit_discount":"shift+i","edit_order_tax":"shift+t","add_payment_row":"shift+r","finalize_payment":"shift+f","recent_product_quantity":"f2","add_new_product":"f4"}}';

        //Add prefixes
        $business_details['ref_no_prefixes'] = [
            'purchase' => 'PO',
            'stock_transfer' => 'ST',
            'stock_adjustment' => 'SA',
            'sell_return' => 'CN',
            'expense' => 'EP',
            'contacts' => 'CO',
            'purchase_payment' => 'PP',
            'sell_payment' => 'SP',
            'business_location' => 'BL',
        ];

        //Disable inline tax editing
        $business_details['enable_inline_tax'] = 0;

        $business = Business::create_business($business_details);

        return $business;
    }

    /**
     * Gives details for a business
     *
     * @return object
     */
    public function getDetails($business_id)
    {
        $details = Business::leftjoin('tax_rates AS TR', 'business.default_sales_tax', 'TR.id')
                        ->leftjoin('currencies AS cur', 'business.currency_id', 'cur.id')
                        ->select(
                            'business.*',
                            'cur.code as currency_code',
                            'cur.symbol as currency_symbol',
                            'thousand_separator',
                            'decimal_separator',
                            'TR.amount AS tax_calculation_amount',
                            'business.default_sales_discount'
                        )
                        ->where('business.id', $business_id)
                        ->first();

        return $details;
    }

    /**
     * Resolve the filesystem path for a business logo file.
     *
     * @param  string|null  $logo_name
     * @return string|null
     */
    public function getBusinessLogoPath($logo_name)
    {
        if (empty($logo_name)) {
            return null;
        }

        $paths = [
            public_path('uploads/business_logos/'.$logo_name),
            public_path('storage/business_logos/'.$logo_name),
            storage_path('app/business_logos/'.$logo_name),
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Get public URL for the business logo from settings.
     *
     * @param  \App\Business|object|array|string|null  $business
     * @param  bool  $respect_show_logo
     * @param  int  $show_logo_flag
     * @return string|false
     */
    public function getBusinessLogoUrl($business, $respect_show_logo = false, $show_logo_flag = 1)
    {
        if ($respect_show_logo && empty($show_logo_flag)) {
            return false;
        }

        $logo_name = $this->resolveBusinessLogoName($business);

        if (empty($logo_name)) {
            return false;
        }

        $logo_path = $this->getBusinessLogoPath($logo_name);

        if (empty($logo_path)) {
            return false;
        }

        if (str_contains($logo_path, public_path('storage'.DIRECTORY_SEPARATOR.'business_logos'))) {
            return url('storage/business_logos/'.$logo_name);
        }

        return asset('uploads/business_logos/'.$logo_name);
    }

    /**
     * Get invoice/document logo URL using business settings logo.
     *
     * @param  \App\Business|object|array|null  $business
     * @param  object|null  $invoice_layout
     * @return string|false
     */
    public function getDocumentLogoUrl($business, $invoice_layout = null)
    {
        $show_logo = ! empty($invoice_layout) ? ($invoice_layout->show_logo ?? 0) : 1;

        return $this->getBusinessLogoUrl($business, true, $show_logo);
    }

    /**
     * Build HTML img tag for business logo tag replacements.
     *
     * @param  \App\Business|object|array|null  $business
     * @return string
     */
    public function getBusinessLogoHtml($business)
    {
        $logo_url = $this->getBusinessLogoUrl($business);

        if (empty($logo_url)) {
            return '';
        }

        return '<img src="'.$logo_url.'" alt="Business Logo">';
    }

    /**
     * @param  mixed  $business
     * @return string|null
     */
    protected function resolveBusinessLogoName($business)
    {
        if (is_string($business)) {
            return $business;
        }

        if (is_array($business)) {
            return $business['logo'] ?? null;
        }

        if (is_object($business)) {
            return $business->logo ?? null;
        }

        return null;
    }

    /**
     * Gives current financial year
     *
     * @return array
     */
    public function getCurrentFinancialYear($business_id)
    {
        $business = Business::where('id', $business_id)->first();
        $start_month = $business->fy_start_month;
        $end_month = $start_month - 1;
        if ($start_month == 1) {
            $end_month = 12;
        }

        $start_year = date('Y');
        //if current month is less than start month change start year to last year
        if (date('n') < $start_month) {
            $start_year = $start_year - 1;
        }

        $end_year = date('Y');
        //if current month is greater than end month change end year to next year
        if (date('n') > $end_month) {
            $end_year = $start_year + 1;
        }
        $start_date = $start_year.'-'.str_pad($start_month, 2, 0, STR_PAD_LEFT).'-01';
        $end_date = $end_year.'-'.str_pad($end_month, 2, 0, STR_PAD_LEFT).'-01';
        $end_date = date('Y-m-t', strtotime($end_date));

        $output = [
            'start' => $start_date,
            'end' => $end_date,
        ];

        return $output;
    }

    /**
     * Adds a new location to a business
     *
     * @param  int  $business_id
     * @param  array  $location_details
     * @param  int  $invoice_layout_id default null
     * @return location object
     */
    public function addLocation($business_id, $location_details, $invoice_scheme_id = null, $invoice_layout_id = null)
    {
        if (empty($invoice_scheme_id)) {
            $layout = InvoiceLayout::where('is_default', 1)
                                    ->where('business_id', $business_id)
                                    ->first();
            $invoice_layout_id = $layout->id;
        }

        if (empty($invoice_scheme_id)) {
            $scheme = InvoiceScheme::where('is_default', 1)
                                    ->where('business_id', $business_id)
                                    ->first();
            $invoice_scheme_id = $scheme->id;
        }

        //Update reference count
        $ref_count = $this->setAndGetReferenceCount('business_location', $business_id);
        $location_id = $this->generateReferenceNumber('business_location', $ref_count, $business_id);

        //Enable all payment methods by default
        $payment_types = $this->payment_types();
        $location_payment_types = [];
        foreach ($payment_types as $key => $value) {
            $location_payment_types[$key] = [
                'is_enabled' => 1,
                'account' => null,
            ];
        }
        $location = BusinessLocation::create(['business_id' => $business_id,
            'name' => $location_details['name'],
            'landmark' => $location_details['landmark'],
            'city' => $location_details['city'],
            'state' => $location_details['state'],
            'zip_code' => $location_details['zip_code'],
            'country' => $location_details['country'],
            'invoice_scheme_id' => $invoice_scheme_id,
            'invoice_layout_id' => $invoice_layout_id,
            'sale_invoice_layout_id' => $invoice_layout_id,
            'mobile' => ! empty($location_details['mobile']) ? $location_details['mobile'] : '',
            'alternate_number' => ! empty($location_details['alternate_number']) ? $location_details['alternate_number'] : '',
            'website' => ! empty($location_details['website']) ? $location_details['website'] : '',
            'email' => '',
            'location_id' => $location_id,
            'default_payment_accounts' => json_encode($location_payment_types),
        ]);

        return $location;
    }

    /**
     * Return the invoice layout details
     *
     * @param  int  $business_id
     * @param  array  $layout_id = null
     * @return location object
     */
    public function invoiceLayout($business_id, $layout_id = null)
    {
        $layout = null;
        if (! empty($layout_id)) {
            $layout = InvoiceLayout::find($layout_id);
        }

        //If layout is not found (deleted) then get the default layout for the business
        if (empty($layout)) {
            $layout = InvoiceLayout::where('business_id', $business_id)
                        ->where('is_default', 1)
                        ->first();
        }
        //$output = []
        return $layout;
    }

    /**
     * Return the printer configuration
     *
     * @param  int  $business_id
     * @param  int  $printer_id
     * @return array
     */
    public function printerConfig($business_id, $printer_id)
    {
        $printer = Printer::where('business_id', $business_id)
                    ->find($printer_id);

        $output = [];

        if (! empty($printer)) {
            $output['connection_type'] = $printer->connection_type;
            $output['capability_profile'] = $printer->capability_profile;
            $output['char_per_line'] = $printer->char_per_line;
            $output['ip_address'] = $printer->ip_address;
            $output['port'] = $printer->port;
            $output['path'] = $printer->path;
            $output['server_url'] = $printer->server_url;
        }

        return $output;
    }

    /**
     * Return the date range for which editing of transaction for a business is allowed.
     *
     * @param  int  $business_id
     * @param  char  $edit_transaction_period
     * @return array
     */
    public function editTransactionDateRange($business_id, $edit_transaction_period)
    {
        if (is_numeric($edit_transaction_period)) {
            return ['start' => \Carbon::today()
                ->subDays($edit_transaction_period),
                'end' => \Carbon::today(),
            ];
        } elseif ($edit_transaction_period == 'fy') {
            //Editing allowed for current financial year
            return $this->getCurrentFinancialYear($business_id);
        }

        return false;
    }

    /**
     * Return the default setting for the pos screen.
     *
     * @return array
     */
    public function defaultPosSettings()
    {
        return ['disable_pay_checkout' => 0, 'disable_draft' => 0, 'disable_express_checkout' => 0, 'hide_product_suggestion' => 0, 'hide_recent_trans' => 0, 'disable_discount' => 0, 'disable_order_tax' => 0, 'is_pos_subtotal_editable' => 0];
    }

    /**
     * Return the default setting for the email.
     *
     * @return array
     */
    public function defaultEmailSettings()
    {
        return [
            'mail_host' => '',
            'mail_port' => '',
            'mail_username' => '',
            'mail_password' => '',
            'mail_encryption' => '',
            'mail_from_address' => '',
            'mail_from_name' => '',
            'enable_email_watermark' => 0,
            'email_watermark_type' => 'business_name',
        ];
    }

    /**
     * Build view data for the email watermark overlay.
     *
     * @param  array|null  $email_settings
     * @param  \App\Business|null  $business
     * @return array
     */
    public function getEmailWatermarkViewData($email_settings = null, $business = null)
    {
        if (empty($business)) {
            $business_id = session('user.business_id');
            if (! empty($business_id)) {
                $business = Business::find($business_id);
            }
        }

        if ($email_settings === null) {
            $email_settings = ! empty($business) && ! empty($business->email_settings)
                ? array_merge($this->defaultEmailSettings(), $business->email_settings)
                : $this->defaultEmailSettings();
        } elseif (empty($email_settings)) {
            $email_settings = $this->defaultEmailSettings();
        }

        if (empty($email_settings['enable_email_watermark'])) {
            return ['enabled' => false];
        }

        $watermark_type = $email_settings['email_watermark_type'] ?? 'business_name';

        if ($watermark_type === 'logo' && ! empty($business->logo)) {
            $logo_path = $this->getBusinessLogoPath($business->logo);
            $watermark = [
                'enabled' => true,
                'type' => 'logo',
                'logo_url' => $this->getBusinessLogoUrl($business),
                'logo_path' => $logo_path,
                'business_name' => $business->name ?? '',
                'business_id' => $business->id,
            ];
        } else {
            $watermark = [
                'enabled' => true,
                'type' => 'business_name',
                'business_name' => $business->name ?? '',
                'business_id' => $business->id,
            ];
        }

        $tile = $this->getOrCreateEmailWatermarkTile($watermark);
        $watermark['tile'] = $tile;
        $watermark['background_url'] = ! empty($tile['url']) ? $tile['url'] : null;
        $watermark['items'] = $this->getDocumentWatermarkPatternItems();

        return $watermark;
    }

    /**
     * Build staggered positions for HTML document/print watermarks.
     *
     * @param  int  $rows
     * @param  int  $cols
     * @return array
     */
    public function getDocumentWatermarkPatternItems($rows = 10, $cols = 3)
    {
        $items = [];

        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                $stagger = ($row % 2 === 1) ? 17 : 0;

                $items[] = [
                    'left' => min(78, ($col * 34) + $stagger),
                    'top' => min(90, $row * 15),
                ];
            }
        }

        return $items;
    }

    /**
     * Generate (or reuse) a PNG tile for email clients that block SVG data URIs.
     *
     * @param  array  $watermark
     * @return array|null  ['url' => string, 'path' => string]
     */
    public function getOrCreateEmailWatermarkTile(array $watermark)
    {
        if (empty($watermark['enabled']) || ! extension_loaded('gd')) {
            return null;
        }

        $business_id = $watermark['business_id'] ?? 0;
        $dir = public_path('uploads/email_watermarks');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $logo_mtime = '';
        if (! empty($watermark['logo_path']) && file_exists($watermark['logo_path'])) {
            $logo_mtime = (string) filemtime($watermark['logo_path']);
        }

        $signature = md5(json_encode([
            $watermark['type'] ?? '',
            $watermark['business_name'] ?? '',
            $logo_mtime,
            'sparse_v3',
        ]));

        $filename = 'wm_'.$business_id.'_'.$signature.'.png';
        $path = $dir.DIRECTORY_SEPARATOR.$filename;

        if (! file_exists($path)) {
            $this->generateEmailWatermarkTilePng($watermark, $path);
        }

        if (! file_exists($path)) {
            return null;
        }

        return [
            'url' => url('uploads/email_watermarks/'.$filename),
            'path' => $path,
        ];
    }

    /**
     * Draw a repeating PNG watermark tile.
     *
     * @param  array  $watermark
     * @param  string  $path
     * @return void
     */
    protected function generateEmailWatermarkTilePng(array $watermark, $path)
    {
        $width = 300;
        $height = 200;
        $canvas = imagecreatetruecolor($width, $height);

        if ($canvas === false) {
            return;
        }

        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefill($canvas, 0, 0, $transparent);

        // Two staggered marks per tile — repeats cleanly without crowding.
        $positions = [
            [35, 25],
            [175, 115],
        ];

        if (! empty($watermark['type']) && $watermark['type'] === 'logo' && ! empty($watermark['logo_path']) && file_exists($watermark['logo_path'])) {
            $logo = $this->loadImageFromPath($watermark['logo_path']);

            if ($logo !== false) {
                $logo = imagescale($logo, 60, 60);

                foreach ($positions as $position) {
                    $this->mergeRotatedImage($canvas, $logo, $position[0], $position[1], -45, 16);
                }

                imagedestroy($logo);
            }
        } else {
            $label = $this->truncateWatermarkLabel($watermark['business_name'] ?? '');

            foreach ($positions as $position) {
                $this->drawWatermarkText($canvas, $label, $position[0], $position[1], -45);
            }
        }

        imagepng($canvas, $path);
        imagedestroy($canvas);
    }

    /**
     * @param  string  $path
     * @return resource|false
     */
    protected function loadImageFromPath($path)
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'png') {
            return imagecreatefrompng($path);
        }

        if (in_array($extension, ['jpg', 'jpeg'])) {
            return imagecreatefromjpeg($path);
        }

        if ($extension === 'gif') {
            return imagecreatefromgif($path);
        }

        return false;
    }

    /**
     * @param  resource  $canvas
     * @param  resource  $source
     * @param  int  $dest_x
     * @param  int  $dest_y
     * @param  int  $angle
     * @param  int  $opacity
     * @return void
     */
    protected function mergeRotatedImage($canvas, $source, $dest_x, $dest_y, $angle, $opacity = 20)
    {
        $transparent = imagecolorallocatealpha($source, 255, 255, 255, 127);
        $rotated = imagerotate($source, $angle, $transparent);

        if ($rotated === false) {
            return;
        }

        imagecopymerge(
            $canvas,
            $rotated,
            $dest_x,
            $dest_y,
            0,
            0,
            imagesx($rotated),
            imagesy($rotated),
            $opacity
        );
        imagedestroy($rotated);
    }

    /**
     * @param  resource  $canvas
     * @param  string  $text
     * @param  int  $dest_x
     * @param  int  $dest_y
     * @param  int  $angle
     * @return void
     */
    protected function drawWatermarkText($canvas, $text, $dest_x, $dest_y, $angle)
    {
        $text_width = max(100, strlen($text) * 11);
        $text_height = 40;
        $text_image = imagecreatetruecolor($text_width, $text_height);
        imagesavealpha($text_image, true);
        $transparent = imagecolorallocatealpha($text_image, 255, 255, 255, 127);
        imagefill($text_image, 0, 0, $transparent);
        $grey = imagecolorallocate($text_image, 170, 170, 170);
        imagestring($text_image, 4, 6, 14, $text, $grey);
        $this->mergeRotatedImage($canvas, $text_image, $dest_x, $dest_y, $angle, 22);
        imagedestroy($text_image);
    }

    /**
     * @param  string  $label
     * @param  int  $max
     * @return string
     */
    protected function truncateWatermarkLabel($label, $max = 24)
    {
        $label = mb_strtoupper(trim($label));

        if (mb_strlen($label) <= $max) {
            return $label;
        }

        return mb_substr($label, 0, $max);
    }

    /**
     * Inline CSS for applying the tiled watermark background.
     *
     * @param  array  $watermark
     * @return string
     */
    public function getEmailWatermarkBackgroundStyle(array $watermark, $for_pdf = false)
    {
        if (empty($watermark['enabled'])) {
            return '';
        }

        $background_ref = null;

        if ($for_pdf && ! empty($watermark['tile']['path'])) {
            $background_ref = str_replace('\\', '/', $watermark['tile']['path']);
        } elseif (! empty($watermark['background_url'])) {
            $background_ref = $watermark['background_url'];
        }

        if (empty($background_ref)) {
            return '';
        }

        return "background-image: url('".$background_ref."'); background-repeat: repeat; background-size: 300px 200px;";
    }

    /**
     * Wrap HTML with a repeating watermark background container.
     *
     * @param  string  $html
     * @param  \App\Business  $business
     * @param  bool  $for_pdf
     * @param  array|null  $email_settings
     * @return string
     */
    public function wrapHtmlWithDocumentWatermark($html, $business, $for_pdf = false, $email_settings = null)
    {
        if (empty($business)) {
            return $html;
        }

        $watermark = $this->getEmailWatermarkViewData($email_settings, $business);

        if (empty($watermark['enabled'])) {
            return $html;
        }

        $background_style = $this->getEmailWatermarkBackgroundStyle($watermark, $for_pdf);

        return view('emails.partials.watermark_document_wrapper', [
            'html' => $html,
            'background_style' => $background_style,
            'watermark_background_url' => $watermark['background_url'] ?? '',
            'watermark' => $watermark,
            'for_pdf' => $for_pdf,
        ])->render();
    }

    /**
     * Wrap HTML with a repeating watermark background container.
     *
     * @param  string  $html
     * @param  \App\Business  $business
     * @param  array|null  $email_settings
     * @return string
     */
    public function wrapHtmlWithEmailWatermark($html, $business, $email_settings = null)
    {
        return $this->wrapHtmlWithDocumentWatermark($html, $business, true, $email_settings);
    }

    /**
     * Prepend tiled watermark markup to HTML used for PDF generation.
     *
     * @param  string  $html
     * @param  \App\Business  $business
     * @param  array|null  $email_settings
     * @return string
     */
    public function prependEmailWatermarkToHtml($html, $business, $email_settings = null)
    {
        return $this->wrapHtmlWithEmailWatermark($html, $business, $email_settings);
    }

    /**
     * Return the default setting for the email.
     *
     * @return array
     */
    public function defaultSmsSettings()
    {
        return ['url' => '', 'send_to_param_name' => 'to', 'msg_param_name' => 'text', 'request_method' => 'post', 'param_1' => '', 'param_val_1' => '', 'param_2' => '', 'param_val_2' => '', 'param_3' => '', 'param_val_3' => '', 'param_4' => '', 'param_val_4' => '', 'param_5' => '', 'param_val_5' => ''];
    }

}
