<?php

namespace Tests\Unit\Storefront;

use App\Services\Storefront\PhoneValidationService;
use Tests\TestCase;

class PhoneValidationServiceTest extends TestCase
{
    private PhoneValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PhoneValidationService();
    }

    public function test_valid_egypt_mobile(): void
    {
        $result = $this->service->validate('+201012345678', '+20');

        $this->assertTrue($result['valid']);
        $this->assertSame('', $result['message']);
    }

    public function test_invalid_egypt_mobile_prefix(): void
    {
        $result = $this->service->validate('+201812345678', '+20');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Egypt', $result['message']);
    }

    public function test_invalid_egypt_mobile_length(): void
    {
        $result = $this->service->validate('+20101234567', '+20');

        $this->assertFalse($result['valid']);
    }

    public function test_valid_saudi_mobile(): void
    {
        $result = $this->service->validate('+966501234567', '+966');

        $this->assertTrue($result['valid']);
    }

    public function test_unknown_dial_code_skips_validation(): void
    {
        $result = $this->service->validate('+999123456789', '+999');

        $this->assertTrue($result['valid']);
    }

    public function test_canonical_national_digits_strips_dial_and_local_zero(): void
    {
        $this->assertSame(
            '1012345678',
            $this->service->canonicalNationalDigits('+201012345678', '+20')
        );
        $this->assertSame(
            '1012345678',
            $this->service->canonicalNationalDigits('01012345678')
        );
        $this->assertSame(
            '1012345678',
            $this->service->canonicalNationalDigits('1012345678')
        );
    }

    public function test_canonical_national_digits_match_across_formats(): void
    {
        $a = $this->service->canonicalNationalDigits('+201012345678', '+20');
        $b = $this->service->canonicalNationalDigits('01012345678');
        $this->assertSame($a, $b);
    }

    public function test_mobiles_match_with_or_without_country_code(): void
    {
        $stored = '+201062828881';

        $this->assertTrue($this->service->mobilesMatch($stored, '+201062828881'));
        $this->assertTrue($this->service->mobilesMatch($stored, '201062828881'));
        $this->assertTrue($this->service->mobilesMatch($stored, '01062828881'));
        $this->assertTrue($this->service->mobilesMatch($stored, '1062828881'));
        $this->assertFalse($this->service->mobilesMatch($stored, '01099999999'));
    }

    public function test_national_digit_needles_include_local_and_intl_forms(): void
    {
        $needles = $this->service->nationalDigitNeedles('01062828881');

        $this->assertContains('1062828881', $needles);
        $this->assertTrue($this->service->mobilesMatch('+201062828881', '01062828881'));
    }
}
