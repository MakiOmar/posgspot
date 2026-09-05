<?php

namespace App\Services\Storefront;

/**
 * Country-specific phone validation using resources/data/countries-codes-and-flags.json.
 */
class PhoneValidationService
{
    /** @var array<int, array<string, string>>|null */
    private ?array $countries = null;

    /**
     * @return array<int, array<string, string>>
     */
    public function getCountriesData(): array
    {
        if ($this->countries !== null) {
            return $this->countries;
        }

        $path = resource_path('data/countries-codes-and-flags.json');
        if (! is_readable($path)) {
            $this->countries = [];

            return $this->countries;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        $this->countries = is_array($decoded) ? $decoded : [];

        return $this->countries;
    }

    /**
     * @return array{valid: bool, message: string}
     */
    public function validate(string $fullPhone, string $dialCode): array
    {
        $countries = $this->getCountriesData();

        if ($countries === []) {
            return ['valid' => true, 'message' => ''];
        }

        $matching = null;
        foreach ($countries as $country) {
            if (($country['dial_code'] ?? '') === $dialCode) {
                $matching = $country;
                break;
            }
        }

        if ($matching === null) {
            return ['valid' => true, 'message' => ''];
        }

        $pattern = $matching['validation_pattern'] ?? '';
        if ($pattern === '') {
            return ['valid' => true, 'message' => ''];
        }

        if (preg_match('/'.$pattern.'/', $fullPhone) === 1) {
            return ['valid' => true, 'message' => ''];
        }

        $countryName = $matching['name_en'] ?? 'this country';
        $example = $this->exampleMessage($dialCode);

        return [
            'valid' => false,
            'message' => sprintf(
                'Invalid phone number for %s. You entered: %s. %s',
                $countryName,
                $fullPhone,
                $example
            ),
        ];
    }

    public function exampleMessage(string $dialCode): string
    {
        $examples = [
            '+20' => 'Example: +201012345678 (must start with 10, 11, 12, 15, or 16)',
            '+966' => 'Example: +966501234567 (must start with 5)',
            '+971' => 'Example: +971501234567 (must start with 5)',
            '+1' => 'Example: +11234567890 (10 digits after country code)',
            '+44' => 'Example: +441234567890 (10-11 digits after country code)',
            '+91' => 'Example: +919012345678 (must start with 6-9)',
            '+33' => 'Example: +33612345678 (9 digits after country code)',
            '+49' => 'Example: +4915012345678 (10-12 digits after country code)',
            '+81' => 'Example: +819012345678 (10-11 digits after country code)',
            '+86' => 'Example: +8613012345678 (must start with 1)',
            '+962' => 'Example: +962791234567 (must start with 7)',
            '+964' => 'Example: +964790123456 (must start with 7)',
            '+961' => 'Example: +9613123456 (must start with 3, 7, 8, or 9)',
            '+212' => 'Example: +212612345678 (must start with 6 or 7)',
            '+974' => 'Example: +97431234567 (must start with 3, 5, 6, or 7)',
            '+968' => 'Example: +96871234567 (must start with 7 or 9)',
            '+965' => 'Example: +96551234567 (must start with 5, 6, or 9)',
            '+973' => 'Example: +97331234567 (must start with 3 or 6)',
            '+970' => 'Example: +970591234567 (must start with 5)',
        ];

        if (isset($examples[$dialCode])) {
            return $examples[$dialCode];
        }

        return sprintf('Please enter a valid mobile number for this country starting with %s', $dialCode);
    }

    public function hintForDialCode(string $dialCode): string
    {
        return $this->exampleMessage($dialCode);
    }

    /**
     * Normalize a stored or submitted mobile to comparable national digits (no dial code, no local leading zero).
     */
    public function canonicalNationalDigits(string $mobile, ?string $preferredDialCode = null): string
    {
        $digits = preg_replace('/\D/', '', $mobile) ?? '';
        if ($digits === '') {
            return '';
        }

        $hasExplicitInternational = str_contains($mobile, '+');

        if ($preferredDialCode || $hasExplicitInternational) {
            $dialCode = $preferredDialCode ?: $this->inferDialCodeFromMobile($mobile);
            $dialDigits = preg_replace('/\D/', '', $dialCode) ?? '';
            if ($dialDigits !== '' && str_starts_with($digits, $dialDigits)) {
                $digits = substr($digits, strlen($dialDigits));
            }
        }

        while (str_starts_with($digits, '0') && strlen($digits) > 1) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    public function inferDialCodeFromMobile(string $mobile): string
    {
        $countries = $this->getCountriesData();
        usort($countries, function ($a, $b) {
            return strlen($b['dial_code'] ?? '') <=> strlen($a['dial_code'] ?? '');
        });

        foreach ($countries as $country) {
            $dial = $country['dial_code'] ?? '';
            if ($dial !== '' && str_starts_with($mobile, $dial)) {
                return $dial;
            }
        }

        return '+20';
    }

    /**
     * Candidate national-digit forms for loose matching (with/without country code or leading 0).
     *
     * @return list<string>
     */
    public function nationalDigitNeedles(string $mobile, ?string $preferredDialCode = null): array
    {
        $needles = [];
        $push = function (string $value) use (&$needles): void {
            if ($value !== '') {
                $needles[$value] = true;
            }
        };

        $push($this->canonicalNationalDigits($mobile, $preferredDialCode));
        $push($this->canonicalNationalDigits($mobile));

        $digits = preg_replace('/\D/', '', $mobile) ?? '';
        if ($digits === '') {
            return array_keys($needles);
        }

        // Digits alone (e.g. 201062828881) should still strip a known dial code.
        $push($this->canonicalNationalDigits('+'.$digits));

        $dialCodes = array_values(array_unique(array_filter([
            $preferredDialCode,
            $this->inferDialCodeFromMobile('+'.$digits),
            '+20',
        ])));

        foreach ($dialCodes as $dial) {
            $push($this->canonicalNationalDigits($mobile, $dial));
            $push($this->canonicalNationalDigits($digits, $dial));
        }

        return array_keys($needles);
    }

    public function mobilesMatch(string $stored, string $search, ?string $preferredDialCode = null): bool
    {
        $searchNeedles = $this->nationalDigitNeedles($search, $preferredDialCode);
        if ($searchNeedles === []) {
            return false;
        }

        $storedNeedles = $this->nationalDigitNeedles($stored, $preferredDialCode);

        return count(array_intersect($searchNeedles, $storedNeedles)) > 0;
    }
}
