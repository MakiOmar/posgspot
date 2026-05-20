<?php

namespace Tests\Unit;

use App\Business;
use App\Utils\BusinessUtil;
use Tests\TestCase;

class BusinessLogoHelperTest extends TestCase
{
    public function test_get_business_logo_url_returns_false_when_logo_missing()
    {
        $business_util = new BusinessUtil();
        $business = new Business(['logo' => null]);

        $this->assertFalse($business_util->getBusinessLogoUrl($business));
    }

    public function test_get_document_logo_url_respects_show_logo_setting()
    {
        $business_util = new BusinessUtil();
        $business = new Business(['logo' => 'logo.png']);
        $invoice_layout = (object) ['show_logo' => 0];

        $this->assertFalse($business_util->getDocumentLogoUrl($business, $invoice_layout));
    }
}
