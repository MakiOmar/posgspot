<?php

namespace App\Services\Storefront;

/**
 * Country and state lists for storefront forms.
 */
class GeoDataService
{
    public function __construct(private PhoneValidationService $phoneValidation)
    {
    }

    /**
     * @return array<int, array{code: string, name: string}>
     */
    public function getCountries(): array
    {
        $seen = [];
        $countries = [];

        foreach ($this->phoneValidation->getCountriesData() as $row) {
            $code = $row['country_code'] ?? '';
            if ($code === '' || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $countries[] = [
                'code' => $code,
                'name' => $row['name_en'] ?? $code,
            ];
        }

        usort($countries, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $countries;
    }

    /**
     * @return array<int, array{code: string, name: string}>
     */
    public function getStates(string $countryCode): array
    {
        $countryCode = strtoupper($countryCode);
        $path = resource_path('data/geo/countries-states.json');

        if (! is_readable($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        $states = $decoded['states'][$countryCode] ?? [];

        if (! is_array($states)) {
            return [];
        }

        $result = [];
        foreach ($states as $code => $name) {
            $result[] = ['code' => (string) $code, 'name' => (string) $name];
        }

        usort($result, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $result;
    }
}
