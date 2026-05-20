<?php

namespace Tests\Unit;

use App\Business;
use App\Utils\BusinessUtil;
use Tests\TestCase;

class EmailWatermarkSettingsTest extends TestCase
{
    public function test_default_email_settings_include_watermark_options()
    {
        $business_util = new BusinessUtil();
        $defaults = $business_util->defaultEmailSettings();

        $this->assertArrayHasKey('enable_email_watermark', $defaults);
        $this->assertArrayHasKey('email_watermark_type', $defaults);
        $this->assertSame(0, $defaults['enable_email_watermark']);
        $this->assertSame('business_name', $defaults['email_watermark_type']);
    }

    public function test_email_watermark_is_disabled_by_default()
    {
        $business_util = new BusinessUtil();
        $business = new Business(['name' => 'Games Spot']);

        $watermark = $business_util->getEmailWatermarkViewData([], $business);

        $this->assertFalse($watermark['enabled']);
    }

    public function test_email_watermark_uses_business_name_when_enabled()
    {
        $business_util = new BusinessUtil();
        $business = new Business(['name' => 'Games Spot']);
        $email_settings = [
            'enable_email_watermark' => 1,
            'email_watermark_type' => 'business_name',
        ];

        $watermark = $business_util->getEmailWatermarkViewData($email_settings, $business);

        $this->assertTrue($watermark['enabled']);
        $this->assertSame('business_name', $watermark['type']);
        $this->assertSame('Games Spot', $watermark['business_name']);
    }

    public function test_email_watermark_falls_back_to_business_name_when_logo_missing()
    {
        $business_util = new BusinessUtil();
        $business = new Business(['name' => 'Games Spot', 'logo' => null]);
        $email_settings = [
            'enable_email_watermark' => 1,
            'email_watermark_type' => 'logo',
        ];

        $watermark = $business_util->getEmailWatermarkViewData($email_settings, $business);

        $this->assertTrue($watermark['enabled']);
        $this->assertSame('business_name', $watermark['type']);
        $this->assertSame('Games Spot', $watermark['business_name']);
    }

    public function test_email_watermark_uses_logo_when_configured()
    {
        $business_util = new BusinessUtil();
        $business = new Business(['name' => 'Games Spot', 'logo' => 'logo.png']);
        $email_settings = [
            'enable_email_watermark' => 1,
            'email_watermark_type' => 'logo',
        ];

        $watermark = $business_util->getEmailWatermarkViewData($email_settings, $business);

        $this->assertTrue($watermark['enabled']);
        $this->assertSame('logo', $watermark['type']);
        $this->assertStringContainsString('logo.png', $watermark['logo_url']);
    }

    public function test_email_watermark_pattern_generates_staggered_grid()
    {
        $business_util = new BusinessUtil();
        $items = $business_util->getEmailWatermarkPatternItems(4, 2);

        $this->assertCount(8, $items);
        $this->assertArrayHasKey('left', $items[0]);
        $this->assertArrayHasKey('top', $items[0]);
        $this->assertNotSame($items[0]['left'], $items[1]['left']);
    }
}
