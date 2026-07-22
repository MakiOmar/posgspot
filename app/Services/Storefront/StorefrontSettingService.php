<?php

namespace App\Services\Storefront;

use App\BusinessLocation;
use App\Services\Storefront\Homepage\HomepageSectionService;
use App\StorefrontSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reads and persists storefront settings from the database.
 */
class StorefrontSettingService
{
    private const CACHE_KEY = 'storefront_settings_';

    /** Blobs larger than this are treated as poisoned (usually leftover inline SVG) and scrubbed. */
    private const OVERSIZED_BLOB_BYTES = 1_000_000;

    /** Reject homepage section POST bodies larger than this (library paths are tiny). */
    public const MAX_HOMEPAGE_SECTIONS_POST_BYTES = 512_000;

    public function defaults(): array
    {
        return [
            'selling_location_ids' => [],
            'default_fulfillment_location_id' => null,
            'cod_enabled' => true,
            'gateway' => [
                'provider' => null,
                'api_key' => null,
                'enabled' => false,
                'fawry' => [
                    'merchant_code' => '',
                    'security_key' => null,
                    'staging' => false,
                ],
            ],
            'shipping' => [
                'flat_rate' => 0,
                'free_shipping_threshold' => 0,
                'hide_rates_until_address' => true,
            ],
            'couriers' => [
                'bosta' => [
                    'enabled' => false,
                    'api_key' => null,
                    'staging' => false,
                ],
            ],
            'digital' => [
                'enabled' => true,
                'accounts_store_profile_id' => 17,
                'primary_product_id' => null,
                'secondary_product_id' => null,
                'gift_card_product_id' => null,
                // sell = final POS sale (default); quotation = draft quotation (Accounts workflow)
                'pos_document_type' => 'sell',
                // When false: credentials only on staff_note (not sell line / account / email / invoice description)
                'expose_credentials_to_customer' => true,
            ],
            'contact' => [
                'phone' => '',
                'email' => '',
                'whatsapp' => '',
            ],
            'social' => [
                'facebook' => '',
                'instagram' => '',
                'tiktok' => '',
                'youtube' => '',
            ],
            'announcement' => [
                'message' => [
                    'en' => '',
                    'ar' => '',
                ],
                'link' => '',
                'enabled' => false,
            ],
            'theme' => [
                'accent_color' => '#00d4aa',
            ],
            'sale_badge' => [
                'mode' => 'percent',
                'text' => [
                    'en' => 'Sale',
                    'ar' => 'تخفيض',
                ],
            ],
            'catalog' => [
                'show_availability_on_cards' => true,
            ],
            'maintenance_mode' => false,
            'reward_points' => [
                'name' => [
                    'en' => 'Reward Points',
                    'ar' => 'نقاط المكافآت',
                ],
            ],
            'turnstile' => [
                'site_key' => '',
                'secret_key' => null,
            ],
            'promo_codes' => [
                'enabled_at_checkout' => true,
                'allow_stacking' => false,
            ],
            // Footer payment method icons (label + uploaded file or external URL).
            'payment_icons' => [],
            // Homepage / category promotional banners (image + link).
            'banners' => [],
            // Ordered homepage section builder (see Homepage\SectionTypeRegistry).
            'homepage_sections' => [],
            'newsletter' => [
                'enabled' => false,
                'provider' => null,
                'double_opt_in' => true,
                'mailchimp' => [
                    'api_key' => null,
                    'audience_id' => '',
                ],
                'mailerlite' => [
                    'api_token' => null,
                    'group_id' => '',
                ],
                'aweber' => [
                    'access_token' => null,
                    'account_id' => '',
                    'list_id' => '',
                ],
            ],
        ];
    }

    public function get(int $businessId): array
    {
        return Cache::remember(self::CACHE_KEY.$businessId, 300, function () use ($businessId) {
            return $this->loadSettingsArray($businessId);
        });
    }

    /**
     * Persist homepage sections without Eloquent JSON cast round-trips.
     * Oversized blobs (legacy inline SVG) are surgically replaced so PHP never
     * json_encode()s multi‑MB markup (OOM at Casts/Json.php).
     *
     * @param  array<int, mixed>  $sections
     * @return array<int, array<string, mixed>> normalized sections
     */
    public function saveHomepageSections(int $businessId, array $sections): array
    {
        $normalized = $this->homepageSections()->normalizeSections($sections, $businessId);
        $encodedSections = json_encode($normalized, JSON_UNESCAPED_UNICODE);
        if ($encodedSections === false) {
            throw new \RuntimeException('Could not encode homepage sections.');
        }

        $raw = DB::table('storefront_settings')->where('business_id', $businessId)->value('value');
        $blobBytes = $this->measureBlobBytes($raw);

        Log::warning('storefront.homepage_sections.save.start', [
            'business_id' => $businessId,
            'blob_bytes' => $blobBytes,
            'sections_bytes' => strlen($encodedSections),
            'mem_bytes' => memory_get_usage(true),
            'peak_bytes' => memory_get_peak_usage(true),
        ]);

        if (is_array($raw)) {
            // Hydrated JSON array — clear poison before encode (never Eloquent-cast 900MB).
            $this->stripInlineSvgFromArray($raw);
            $this->stripOversizedStringsFromArray($raw, 50_000);
            $raw['homepage_sections'] = $normalized;
            $merged = array_replace_recursive($this->defaults(), $raw);
            $merged['homepage_sections'] = $normalized;
            $this->stripOversizedStringsFromArray($merged, 50_000);
            $payload = json_encode($merged, JSON_UNESCAPED_UNICODE);
            if (is_string($payload) && strlen($payload) >= self::OVERSIZED_BLOB_BYTES) {
                $fresh = $this->defaults();
                $fresh['homepage_sections'] = $normalized;
                $payload = json_encode($fresh, JSON_UNESCAPED_UNICODE);
            }
        } elseif (is_string($raw) && $raw !== '') {
            if (strlen($raw) >= self::OVERSIZED_BLOB_BYTES) {
                // Avoid json_decode of multi‑MB poisoned blobs — swap homepage_sections in-place.
                $payload = $this->replaceJsonKeyValue($raw, 'homepage_sections', $encodedSections);
                $decodedOk = false;
                if (is_string($payload)) {
                    json_decode($payload);
                    $decodedOk = json_last_error() === JSON_ERROR_NONE;
                }
                if (! $decodedOk) {
                    Log::error('storefront.homepage_sections.save.surgical_replace_failed', [
                        'business_id' => $businessId,
                        'json_error' => json_last_error_msg(),
                    ]);
                    // Last resort: keep defaults + new sections (preserves nothing from poison blob).
                    $fresh = $this->defaults();
                    $fresh['homepage_sections'] = $normalized;
                    $payload = json_encode($fresh, JSON_UNESCAPED_UNICODE);
                } elseif (strlen($payload) >= self::OVERSIZED_BLOB_BYTES) {
                    $payload = $this->stripInlineSvgKeysFromJsonString($payload) ?? $payload;
                }
            } else {
                $data = json_decode($raw, true);
                if (! is_array($data)) {
                    $data = [];
                }
                $this->stripInlineSvgFromArray($data);
                $data['homepage_sections'] = $normalized;
                $payload = json_encode(array_replace_recursive($this->defaults(), $data), JSON_UNESCAPED_UNICODE);
            }
        } else {
            $fresh = $this->defaults();
            $fresh['homepage_sections'] = $normalized;
            $payload = json_encode($fresh, JSON_UNESCAPED_UNICODE);
        }

        if (! is_string($payload) || $payload === '') {
            throw new \RuntimeException('Could not build storefront settings payload.');
        }

        Log::warning('storefront.homepage_sections.save.write', [
            'business_id' => $businessId,
            'payload_bytes' => strlen($payload),
            'mem_bytes' => memory_get_usage(true),
            'peak_bytes' => memory_get_peak_usage(true),
        ]);

        $this->writeValueJsonString($businessId, $payload);
        Cache::forget(self::CACHE_KEY.$businessId);

        return $normalized;
    }

    /**
     * One-shot scrub of oversized / inline-SVG junk from the settings blob (ops / artisan).
     *
     * @return array{
     *   business_id: int,
     *   before_bytes: int,
     *   after_bytes: int,
     *   removed_keys: int,
     *   cleared_strings: array<string, int>,
     *   reset_homepage_sections: bool,
     *   raw_type: string
     * }
     */
    public function scrubInlineSvgFromStoredSettings(int $businessId): array
    {
        $raw = DB::table('storefront_settings')->where('business_id', $businessId)->value('value');
        $before = $this->measureBlobBytes($raw);
        $removed = 0;
        $cleared = [];
        $resetHomepage = false;
        $rawType = get_debug_type($raw);

        if (is_array($raw)) {
            // PDO/MySQL often returns JSON columns as arrays — string surgical replace never runs.
            $removed = $this->stripInlineSvgFromArray($raw);
            $cleared = $this->stripOversizedStringsFromArray($raw, 50_000);
            $raw['homepage_sections'] = [];
            $resetHomepage = true;
            $data = array_replace_recursive($this->defaults(), $raw);
            $data['homepage_sections'] = [];
            $cleared = array_merge($cleared, $this->stripOversizedStringsFromArray($data, 50_000));
            $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        } elseif (is_string($raw) && $raw !== '') {
            $working = $raw;
            if (strlen($working) >= self::OVERSIZED_BLOB_BYTES) {
                $stripped = $this->stripInlineSvgKeysFromJsonString($working);
                if (is_string($stripped)) {
                    $working = $stripped;
                }
            }
            if (strlen($working) >= self::OVERSIZED_BLOB_BYTES) {
                $replaced = $this->replaceJsonKeyValue($working, 'homepage_sections', '[]');
                if (is_string($replaced)) {
                    $working = $replaced;
                    $resetHomepage = true;
                }
            }

            // Still huge after surgical edits — do not json_decode poison; write lean defaults.
            if (strlen($working) >= self::OVERSIZED_BLOB_BYTES) {
                Log::warning('storefront.settings.scrub_nuclear_reset', [
                    'business_id' => $businessId,
                    'before_bytes' => $before,
                    'working_bytes' => strlen($working),
                ]);
                $data = $this->defaults();
                $data['homepage_sections'] = [];
                $resetHomepage = true;
                $cleared['(nuclear_reset)'] = $before;
                $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
            } else {
                $data = json_decode($working, true);
                if (! is_array($data)) {
                    $data = $this->defaults();
                } else {
                    $removed = $this->stripInlineSvgFromArray($data);
                    $cleared = $this->stripOversizedStringsFromArray($data, 50_000);
                    $data = array_replace_recursive($this->defaults(), $data);
                }
                if ($resetHomepage || $before >= self::OVERSIZED_BLOB_BYTES) {
                    $data['homepage_sections'] = [];
                    $resetHomepage = true;
                } elseif (isset($data['homepage_sections'])) {
                    $data['homepage_sections'] = $this->homepageSections()->normalizeSections(
                        $data['homepage_sections'],
                        $businessId
                    );
                }
                $cleared = array_merge($cleared, $this->stripOversizedStringsFromArray($data, 50_000));
                $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
            }
        } else {
            return [
                'business_id' => $businessId,
                'before_bytes' => 0,
                'after_bytes' => 0,
                'removed_keys' => 0,
                'cleared_strings' => [],
                'reset_homepage_sections' => false,
                'raw_type' => $rawType,
            ];
        }

        if (! is_string($payload)) {
            throw new \RuntimeException('Scrub failed to encode settings.');
        }

        // Never write another multi‑MB blob back.
        if (strlen($payload) >= self::OVERSIZED_BLOB_BYTES) {
            Log::warning('storefront.settings.scrub_payload_still_oversized', [
                'business_id' => $businessId,
                'payload_bytes' => strlen($payload),
            ]);
            $payload = json_encode(array_merge($this->defaults(), ['homepage_sections' => []]), JSON_UNESCAPED_UNICODE);
            $resetHomepage = true;
            $cleared['(final_nuclear_reset)'] = $before;
        }

        $this->writeValueJsonString($businessId, $payload);
        Cache::forget(self::CACHE_KEY.$businessId);

        return [
            'business_id' => $businessId,
            'before_bytes' => $before,
            'after_bytes' => is_string($payload) ? strlen($payload) : 0,
            'removed_keys' => $removed,
            'cleared_strings' => $cleared,
            'reset_homepage_sections' => $resetHomepage,
            'raw_type' => $rawType,
        ];
    }

    /**
     * Inspect largest string values in the settings blob (no write).
     *
     * @return array{business_id: int, blob_bytes: int, raw_type: string, largest: array<string, int>}
     */
    public function inspectSettingsBlob(int $businessId, int $limit = 25): array
    {
        $raw = DB::table('storefront_settings')->where('business_id', $businessId)->value('value');
        $blobBytes = $this->measureBlobBytes($raw);
        $rawType = get_debug_type($raw);
        $largest = [];

        if (is_array($raw)) {
            $largest = $this->collectLargestStrings($raw, $limit);
        } elseif (is_string($raw) && $raw !== '') {
            foreach (['homepage_sections', 'banners', 'payment_icons'] as $key) {
                $probe = $this->replaceJsonKeyValue($raw, $key, '[]');
                if (is_string($probe)) {
                    $largest[$key] = max(0, strlen($raw) - strlen($probe));
                }
            }
            arsort($largest);
            $largest = array_slice($largest, 0, $limit, true);
        }

        return [
            'business_id' => $businessId,
            'blob_bytes' => $blobBytes,
            'raw_type' => $rawType,
            'largest' => $largest,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadSettingsArray(int $businessId): array
    {
        $raw = DB::table('storefront_settings')->where('business_id', $businessId)->value('value');
        if ($raw === null || $raw === '' || $raw === []) {
            return $this->homepageSections()->ensureSections($this->defaults());
        }

        $blobBytes = $this->measureBlobBytes($raw);
        if ($blobBytes >= self::OVERSIZED_BLOB_BYTES) {
            Log::warning('storefront.settings.oversized_blob_auto_scrub', [
                'business_id' => $businessId,
                'blob_bytes' => $blobBytes,
                'mem_bytes' => memory_get_usage(true),
            ]);
            // Heal DB so subsequent requests stay small.
            try {
                $this->scrubInlineSvgFromStoredSettings($businessId);
                $raw = DB::table('storefront_settings')->where('business_id', $businessId)->value('value');
            } catch (\Throwable $e) {
                Log::error('storefront.settings.auto_scrub_failed', [
                    'business_id' => $businessId,
                    'error' => $e->getMessage(),
                ]);
                // Fall through with surgical empty homepage_sections if still a string.
                if (is_string($raw)) {
                    $raw = $this->replaceJsonKeyValue($raw, 'homepage_sections', '[]') ?? '{}';
                }
            }
        }

        if (is_array($raw)) {
            $this->stripInlineSvgFromArray($raw);
            $data = $raw;
        } else {
            $data = json_decode((string) $raw, true);
            if (! is_array($data)) {
                $data = [];
            }
            $this->stripInlineSvgFromArray($data);
        }

        return $this->homepageSections()->ensureSections(
            $this->normalizeLocalized(array_replace_recursive($this->defaults(), $data))
        );
    }

    private function writeValueJsonString(int $businessId, string $payload): void
    {
        $now = now();
        $exists = DB::table('storefront_settings')->where('business_id', $businessId)->exists();
        if ($exists) {
            DB::table('storefront_settings')->where('business_id', $businessId)->update([
                'value' => $payload,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('storefront_settings')->insert([
                'business_id' => $businessId,
                'value' => $payload,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function measureBlobBytes(mixed $raw): int
    {
        if (is_string($raw)) {
            return strlen($raw);
        }
        if (is_array($raw)) {
            // Do not json_encode huge hydrated arrays (would double memory).
            return $this->estimateArrayBytes($raw);
        }

        return 0;
    }

    /**
     * @param  array<mixed>  $data
     */
    private function estimateArrayBytes(array $data): int
    {
        $bytes = 0;
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $bytes += strlen($key);
            }
            if (is_string($value)) {
                $bytes += strlen($value);
            } elseif (is_array($value)) {
                $bytes += $this->estimateArrayBytes($value);
            } else {
                $bytes += 8;
            }
        }

        return $bytes;
    }

    /**
     * Recursively remove inline SVG fields from a settings array (in place).
     *
     * @param  array<mixed>  $data
     */
    private function stripInlineSvgFromArray(array &$data): int
    {
        $removed = 0;
        foreach ($data as $key => &$value) {
            if (($key === 'svg_markup' || $key === 'svg_markup_b64') && is_string($value)) {
                unset($data[$key]);
                $removed++;
                continue;
            }
            if (is_array($value)) {
                $removed += $this->stripInlineSvgFromArray($value);
            }
        }
        unset($value);

        return $removed;
    }

    /**
     * Empty any string values over $maxBytes (in place). Returns path => former byte size.
     *
     * @param  array<mixed>  $data
     * @return array<string, int>
     */
    private function stripOversizedStringsFromArray(array &$data, int $maxBytes, string $path = ''): array
    {
        $cleared = [];
        foreach ($data as $key => &$value) {
            $p = $path === '' ? (string) $key : $path.'.'.$key;
            if (is_string($value)) {
                $len = strlen($value);
                if ($len > $maxBytes) {
                    $cleared[$p] = $len;
                    $value = '';
                }
            } elseif (is_array($value)) {
                $cleared = array_merge($cleared, $this->stripOversizedStringsFromArray($value, $maxBytes, $p));
            }
        }
        unset($value);

        return $cleared;
    }

    /**
     * @param  array<mixed>  $data
     * @return array<string, int>
     */
    private function collectLargestStrings(array $data, int $limit, string $path = ''): array
    {
        $found = [];
        foreach ($data as $key => $value) {
            $p = $path === '' ? (string) $key : $path.'.'.$key;
            if (is_string($value)) {
                $len = strlen($value);
                if ($len > 1024) {
                    $found[$p] = $len;
                }
            } elseif (is_array($value)) {
                $found = array_merge($found, $this->collectLargestStrings($value, $limit, $p));
            }
        }
        arsort($found);

        return array_slice($found, 0, $limit, true);
    }

    /**
     * Remove "svg_markup" / "svg_markup_b64" string properties from a JSON document
     * without json_decode (avoids allocating the huge string values as PHP arrays).
     */
    public function stripInlineSvgKeysFromJsonString(string $json): ?string
    {
        $keys = ['svg_markup_b64', 'svg_markup'];
        foreach ($keys as $key) {
            $json = $this->stripJsonStringProperty($json, $key);
            if ($json === null) {
                return null;
            }
        }
        // Clean dangling commas left by removals.
        $cleaned = preg_replace('/,\s*([}\]])/', '$1', $json);

        return is_string($cleaned) ? $cleaned : $json;
    }

    private function stripJsonStringProperty(string $json, string $key): ?string
    {
        // Require `":` after the key so "svg_markup" does not match inside "svg_markup_b64".
        $needle = '"'.$key.'":';
        $offset = 0;
        $out = '';
        $len = strlen($json);

        while (($pos = strpos($json, $needle, $offset)) !== false) {
            $i = $pos + strlen($needle);
            while ($i < $len && ctype_space($json[$i])) {
                $i++;
            }
            if ($i >= $len || $json[$i] !== '"') {
                // Non-string value — skip past the key name only.
                $out .= substr($json, $offset, ($pos + strlen('"'.$key.'"')) - $offset);
                $offset = $pos + strlen('"'.$key.'"');
                continue;
            }
            // Parse JSON string value.
            $i++;
            while ($i < $len) {
                $c = $json[$i];
                if ($c === '\\') {
                    $i += 2;
                    continue;
                }
                if ($c === '"') {
                    $i++;
                    break;
                }
                $i++;
            }
            while ($i < $len && ctype_space($json[$i])) {
                $i++;
            }
            $tookTrailingComma = false;
            if ($i < $len && $json[$i] === ',') {
                $i++;
                $tookTrailingComma = true;
            }

            // Drop a preceding comma only when there was no trailing comma.
            $start = $pos;
            if (! $tookTrailingComma) {
                $j = $pos - 1;
                while ($j >= $offset && ctype_space($json[$j])) {
                    $j--;
                }
                if ($j >= $offset && $json[$j] === ',') {
                    $start = $j;
                }
            }

            $out .= substr($json, $offset, $start - $offset);
            $offset = $i;
        }

        $out .= substr($json, $offset);

        return $out;
    }

    /**
     * Replace a top-level JSON array/object value for $key without full decode.
     * Returns null on failure.
     */
    public function replaceJsonKeyValue(string $json, string $key, string $newValueJson): ?string
    {
        $needle = '"'.$key.'"';
        $pos = strpos($json, $needle);
        if ($pos === false) {
            $json = rtrim($json);
            if (! str_ends_with($json, '}')) {
                return null;
            }

            return substr($json, 0, -1).','.$needle.':'.$newValueJson.'}';
        }

        $colon = strpos($json, ':', $pos + strlen($needle));
        if ($colon === false) {
            return null;
        }
        $i = $colon + 1;
        $len = strlen($json);
        while ($i < $len && ctype_space($json[$i])) {
            $i++;
        }
        if ($i >= $len) {
            return null;
        }

        $start = $i;
        $c = $json[$i];
        if ($c === '{' || $c === '[') {
            $open = $c;
            $close = $c === '{' ? '}' : ']';
            $depth = 0;
            $inString = false;
            $escape = false;
            for (; $i < $len; $i++) {
                $ch = $json[$i];
                if ($inString) {
                    if ($escape) {
                        $escape = false;
                        continue;
                    }
                    if ($ch === '\\') {
                        $escape = true;
                        continue;
                    }
                    if ($ch === '"') {
                        $inString = false;
                    }
                    continue;
                }
                if ($ch === '"') {
                    $inString = true;
                    continue;
                }
                if ($ch === $open) {
                    $depth++;
                } elseif ($ch === $close) {
                    $depth--;
                    if ($depth === 0) {
                        $i++;
                        break;
                    }
                }
            }
        } elseif ($c === '"') {
            $i++;
            while ($i < $len) {
                $ch = $json[$i];
                if ($ch === '\\') {
                    $i += 2;
                    continue;
                }
                if ($ch === '"') {
                    $i++;
                    break;
                }
                $i++;
            }
        } else {
            while ($i < $len && ! in_array($json[$i], [',', '}', ']'], true)) {
                $i++;
            }
        }

        return substr($json, 0, $start).$newValueJson.substr($json, $i);
    }

    /**
     * Migrate legacy single-string fields to locale maps.
     */
    private function normalizeLocalized(array $settings): array
    {
        if (isset($settings['announcement']['message']) && is_string($settings['announcement']['message'])) {
            $settings['announcement']['message'] = [
                'en' => $settings['announcement']['message'],
                'ar' => '',
            ];
        }

        if (isset($settings['sale_badge']['text']) && is_string($settings['sale_badge']['text'])) {
            $settings['sale_badge']['text'] = [
                'en' => $settings['sale_badge']['text'],
                'ar' => '',
            ];
        }

        if (isset($settings['reward_points']['name']) && is_string($settings['reward_points']['name'])) {
            $settings['reward_points']['name'] = [
                'en' => $settings['reward_points']['name'],
                'ar' => '',
            ];
        }

        return $settings;
    }

    public function save(int $businessId, array $settings): StorefrontSetting
    {
        $merged = array_replace_recursive($this->defaults(), $settings);

        // Numeric lists must replace wholesale — array_replace_recursive cannot clear rows.
        if (array_key_exists('payment_icons', $settings)) {
            $merged['payment_icons'] = $this->normalizePaymentIcons($settings['payment_icons']);
        }

        if (array_key_exists('banners', $settings)) {
            $merged['banners'] = $this->normalizeBanners($settings['banners']);
        }

        if (array_key_exists('homepage_sections', $settings)) {
            $merged['homepage_sections'] = $this->homepageSections()->normalizeSections(
                $settings['homepage_sections'],
                $businessId
            );
        } else {
            // Main settings form does not post homepage_sections (saved via dedicated AJAX).
            // Preserve existing rows — do not reseed defaults from an empty merge.
            $existing = $this->getRaw($businessId);
            $merged['homepage_sections'] = is_array($existing['homepage_sections'] ?? null)
                ? $existing['homepage_sections']
                : [];
            $merged = $this->homepageSections()->ensureSections($merged);
        }

        if (! empty($settings['gateway']['api_key'])) {
            $merged['gateway']['api_key'] = Crypt::encryptString($settings['gateway']['api_key']);
        } else {
            $existing = $this->getRaw($businessId);
            $merged['gateway']['api_key'] = $existing['gateway']['api_key'] ?? null;
        }

        if (! empty($settings['gateway']['fawry']['security_key'])) {
            $merged['gateway']['fawry']['security_key'] = Crypt::encryptString($settings['gateway']['fawry']['security_key']);
        } else {
            $existing = $existing ?? $this->getRaw($businessId);
            $merged['gateway']['fawry']['security_key'] = $existing['gateway']['fawry']['security_key'] ?? null;
        }

        if (! empty($settings['turnstile']['secret_key'])) {
            $merged['turnstile']['secret_key'] = Crypt::encryptString($settings['turnstile']['secret_key']);
        } else {
            $existing = $existing ?? $this->getRaw($businessId);
            $merged['turnstile']['secret_key'] = $existing['turnstile']['secret_key'] ?? null;
        }

        if (! empty($settings['couriers']['bosta']['api_key'])) {
            $merged['couriers']['bosta']['api_key'] = Crypt::encryptString($settings['couriers']['bosta']['api_key']);
        } else {
            $existing = $existing ?? $this->getRaw($businessId);
            $merged['couriers']['bosta']['api_key'] = $existing['couriers']['bosta']['api_key'] ?? null;
        }

        $existing = $existing ?? $this->getRaw($businessId);
        $merged['newsletter'] = $this->mergeNewsletterSecrets(
            $merged['newsletter'] ?? [],
            $settings['newsletter'] ?? [],
            $existing['newsletter'] ?? []
        );

        $this->stripInlineSvgFromArray($merged);

        // Write via raw JSON string to avoid Eloquent cast allocating a second huge copy.
        $payload = json_encode($merged, JSON_UNESCAPED_UNICODE);
        if (! is_string($payload)) {
            throw new \RuntimeException('Could not encode storefront settings.');
        }
        $this->writeValueJsonString($businessId, $payload);

        $row = StorefrontSetting::where('business_id', $businessId)->first()
            ?? StorefrontSetting::make(['business_id' => $businessId, 'value' => $merged]);

        $this->syncSellingLocations($businessId, $merged['selling_location_ids'] ?? []);
        Cache::forget(self::CACHE_KEY.$businessId);

        // Zoning tree depends on API key / staging — refresh on courier save.
        try {
            app(\App\Services\Storefront\Shipping\Carriers\BostaApiClient::class)
                ->flushZoningCache($businessId);
        } catch (\Throwable $e) {
            // Ignore if container cannot resolve during early boot/tests.
        }

        return $row;
    }

    public function getSellingLocationIds(int $businessId): array
    {
        $ids = $this->get($businessId)['selling_location_ids'] ?? [];

        return array_values(array_filter(array_map('intval', (array) $ids)));
    }

    /** Envelope format marker for JSON import/export. */
    public const EXPORT_FORMAT = 'storefront_settings';

    /** Current export schema version. */
    public const EXPORT_VERSION = 1;

    /**
     * Build a downloadable JSON envelope for storefront settings.
     * Secrets are redacted (never leave the server as ciphertext or plaintext).
     *
     * @return array{format: string, version: int, exported_at: string, settings: array<string, mixed>}
     */
    public function exportEnvelope(int $businessId): array
    {
        $settings = $this->redactSecretsForExport($this->get($businessId));

        return [
            'format' => self::EXPORT_FORMAT,
            'version' => self::EXPORT_VERSION,
            'exported_at' => now()->toIso8601String(),
            'settings' => $settings,
        ];
    }

    /**
     * Import settings from an export envelope or a raw settings object.
     * Blank/missing secrets preserve existing encrypted values via save().
     *
     * @param  array<string, mixed>  $payload
     * @return array{imported_keys: list<string>}
     *
     * @throws \InvalidArgumentException
     */
    public function importFromPayload(int $businessId, array $payload): array
    {
        $settings = $this->extractImportSettings($payload);
        $settings = $this->sanitizeImportSettings($businessId, $settings);

        if ($settings === []) {
            throw new \InvalidArgumentException('Import file contains no recognized storefront settings.');
        }

        $this->save($businessId, $settings);

        return [
            'imported_keys' => array_keys($settings),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function redactSecretsForExport(array $settings): array
    {
        foreach ($this->secretPaths() as $path) {
            $this->setNestedValue($settings, $path, null);
        }

        return $settings;
    }

    /**
     * @return list<list<string>>
     */
    private function secretPaths(): array
    {
        return [
            ['gateway', 'api_key'],
            ['gateway', 'fawry', 'security_key'],
            ['turnstile', 'secret_key'],
            ['couriers', 'bosta', 'api_key'],
            ['newsletter', 'mailchimp', 'api_key'],
            ['newsletter', 'mailerlite', 'api_token'],
            ['newsletter', 'aweber', 'access_token'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function extractImportSettings(array $payload): array
    {
        // Full envelope from exportEnvelope().
        if (isset($payload['settings']) && is_array($payload['settings'])) {
            $format = $payload['format'] ?? null;
            if ($format !== null && $format !== self::EXPORT_FORMAT) {
                throw new \InvalidArgumentException('Unrecognized import format.');
            }

            $version = $payload['version'] ?? null;
            if ($version !== null && (int) $version > self::EXPORT_VERSION) {
                throw new \InvalidArgumentException(
                    'Import file version '.((int) $version).' is newer than this app supports ('.self::EXPORT_VERSION.').'
                );
            }

            return $payload['settings'];
        }

        // Raw settings object (keys match defaults()).
        $knownKeys = array_keys($this->defaults());
        $hasKnown = false;
        foreach ($knownKeys as $key) {
            if (array_key_exists($key, $payload)) {
                $hasKnown = true;
                break;
            }
        }

        if (! $hasKnown) {
            throw new \InvalidArgumentException('Import file is not a storefront settings export.');
        }

        return $payload;
    }

    /**
     * Keep only known top-level keys; drop secrets that are empty placeholders;
     * filter location IDs to this business.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function sanitizeImportSettings(int $businessId, array $settings): array
    {
        $allowed = array_flip(array_keys($this->defaults()));
        $clean = [];

        foreach ($settings as $key => $value) {
            if (! is_string($key) || ! isset($allowed[$key])) {
                continue;
            }
            $clean[$key] = $value;
        }

        // Empty secret placeholders must not overwrite existing encrypted values.
        foreach ($this->secretPaths() as $path) {
            $current = $this->getNestedValue($clean, $path);
            if ($current === null || $current === '') {
                $this->unsetNestedValue($clean, $path);
            }
        }

        if (array_key_exists('selling_location_ids', $clean)) {
            $validIds = BusinessLocation::where('business_id', $businessId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $validLookup = array_flip($validIds);
            $clean['selling_location_ids'] = array_values(array_filter(
                array_map('intval', (array) $clean['selling_location_ids']),
                fn (int $id) => isset($validLookup[$id])
            ));
        }

        if (array_key_exists('default_fulfillment_location_id', $clean)) {
            $fulfillmentId = $clean['default_fulfillment_location_id'];
            if ($fulfillmentId !== null && $fulfillmentId !== '') {
                $fulfillmentId = (int) $fulfillmentId;
                $belongs = BusinessLocation::where('business_id', $businessId)
                    ->where('id', $fulfillmentId)
                    ->exists();
                $clean['default_fulfillment_location_id'] = $belongs ? $fulfillmentId : null;
            } else {
                $clean['default_fulfillment_location_id'] = null;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $path
     */
    private function getNestedValue(array $data, array $path): mixed
    {
        $cursor = $data;
        foreach ($path as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $path
     */
    private function setNestedValue(array &$data, array $path, mixed $value): void
    {
        $cursor = &$data;
        $last = array_pop($path);
        foreach ($path as $segment) {
            if (! isset($cursor[$segment]) || ! is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor = &$cursor[$segment];
        }
        $cursor[$last] = $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $path
     */
    private function unsetNestedValue(array &$data, array $path): void
    {
        $cursor = &$data;
        $last = array_pop($path);
        foreach ($path as $segment) {
            if (! isset($cursor[$segment]) || ! is_array($cursor[$segment])) {
                return;
            }
            $cursor = &$cursor[$segment];
        }
        unset($cursor[$last]);
    }

    public function decryptGatewayApiKey(array $settings): ?string
    {
        $key = $settings['gateway']['api_key'] ?? null;
        if (empty($key)) {
            return null;
        }

        try {
            return Crypt::decryptString($key);
        } catch (\Throwable) {
            return $key;
        }
    }

    public function decryptFawrySecurityKey(array $settings): ?string
    {
        $key = $settings['gateway']['fawry']['security_key'] ?? null;
        if (empty($key)) {
            return $this->decryptGatewayApiKey($settings);
        }

        try {
            return Crypt::decryptString($key);
        } catch (\Throwable) {
            return $key;
        }
    }

    public function decryptTurnstileSecretKey(array $settings): ?string
    {
        $key = $settings['turnstile']['secret_key'] ?? null;
        if (empty($key)) {
            return null;
        }

        try {
            return Crypt::decryptString($key);
        } catch (\Throwable) {
            return $key;
        }
    }

    /**
     * Return newsletter settings with provider secrets decrypted (for outbound API calls).
     *
     * @param  array<string, mixed>  $newsletter
     * @return array<string, mixed>
     */
    public function withDecryptedNewsletterSecrets(array $newsletter): array
    {
        foreach (['mailchimp' => 'api_key', 'mailerlite' => 'api_token', 'aweber' => 'access_token'] as $provider => $secretField) {
            $raw = $newsletter[$provider][$secretField] ?? null;
            if (empty($raw)) {
                $newsletter[$provider][$secretField] = null;
                continue;
            }
            try {
                $newsletter[$provider][$secretField] = Crypt::decryptString($raw);
            } catch (\Throwable) {
                $newsletter[$provider][$secretField] = $raw;
            }
        }

        return $newsletter;
    }

    /**
     * Encrypt new newsletter secrets; keep existing when form fields are blank.
     *
     * @param  array<string, mixed>  $merged
     * @param  array<string, mixed>  $incoming
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function mergeNewsletterSecrets(array $merged, array $incoming, array $existing): array
    {
        $secretPaths = [
            ['mailchimp', 'api_key'],
            ['mailerlite', 'api_token'],
            ['aweber', 'access_token'],
        ];

        foreach ($secretPaths as [$provider, $field]) {
            $newValue = $incoming[$provider][$field] ?? null;
            if (! empty($newValue)) {
                $merged[$provider][$field] = Crypt::encryptString((string) $newValue);
            } else {
                $merged[$provider][$field] = $existing[$provider][$field] ?? null;
            }
        }

        return $merged;
    }

    private function getRaw(int $businessId): array
    {
        // Intentionally bypasses cache so secret-merge paths see the latest DB row.
        return $this->loadSettingsArray($businessId);
    }

    private function homepageSections(): HomepageSectionService
    {
        return app(HomepageSectionService::class);
    }

    /**
     * Normalize payment icon rows for persistence.
     * Each row: label + either uploaded filename (image) or external url.
     *
     * @param  mixed  $icons
     * @return array<int, array{label: string, image: string|null, url: string}>
     */
    public function normalizePaymentIcons($icons): array
    {
        if (! is_array($icons)) {
            return [];
        }

        $normalized = [];
        foreach ($icons as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $image = trim((string) ($row['image'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));

            if ($label === '' && $image === '' && $url === '') {
                continue;
            }

            if ($label === '') {
                $label = $image !== '' ? pathinfo($image, PATHINFO_FILENAME) : 'Payment';
            }

            $normalized[] = [
                'label' => mb_substr($label, 0, 80),
                'image' => $image !== '' ? $image : null,
                'url' => $image === '' ? mb_substr($url, 0, 500) : '',
            ];
        }

        return array_values($normalized);
    }

    /**
     * Absolute public URL for a stored payment icon row.
     */
    public function paymentIconPublicUrl(array $row): ?string
    {
        $image = trim((string) ($row['image'] ?? ''));
        if ($image !== '') {
            return asset('uploads/storefront_payment_icons/'.$image);
        }

        $url = trim((string) ($row['url'] ?? ''));
        if ($url === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        // Allow relative paths under /uploads or site root.
        if (str_starts_with($url, '/')) {
            return url($url);
        }

        return asset($url);
    }

    /**
     * Normalize promotional banner rows for persistence.
     *
     * @param  mixed  $banners
     * @return array<int, array{id: string, placement: string, category_slug: string, title: array{en: string, ar: string}, link: string, image: string|null, url: string, enabled: bool, sort_order: int}>
     */
    public function normalizeBanners($banners): array
    {
        if (! is_array($banners)) {
            return [];
        }

        $normalized = [];
        foreach ($banners as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $image = trim((string) ($row['image'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            $titleEn = trim((string) (is_array($row['title'] ?? null) ? ($row['title']['en'] ?? '') : ($row['title_en'] ?? '')));
            $titleAr = trim((string) (is_array($row['title'] ?? null) ? ($row['title']['ar'] ?? '') : ($row['title_ar'] ?? '')));
            $link = trim((string) ($row['link'] ?? ''));
            $placement = ($row['placement'] ?? 'home') === 'category' ? 'category' : 'home';
            $categorySlug = trim((string) ($row['category_slug'] ?? ''));
            $enabled = filter_var($row['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $sortOrder = (int) ($row['sort_order'] ?? $index);
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                $id = 'bn_'.bin2hex(random_bytes(4));
            }

            if ($image === '' && $url === '') {
                continue;
            }

            if ($placement === 'category' && $categorySlug === '') {
                continue;
            }

            $normalized[] = [
                'id' => mb_substr($id, 0, 40),
                'placement' => $placement,
                'category_slug' => $placement === 'category' ? mb_substr($categorySlug, 0, 191) : '',
                'title' => [
                    'en' => mb_substr($titleEn, 0, 120),
                    'ar' => mb_substr($titleAr, 0, 120),
                ],
                'link' => mb_substr($link, 0, 500),
                'image' => $image !== '' ? $image : null,
                'url' => $image === '' ? mb_substr($url, 0, 500) : '',
                'enabled' => $enabled,
                'sort_order' => $sortOrder,
            ];
        }

        usort($normalized, fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);

        return array_values($normalized);
    }

    /**
     * Absolute public URL for a stored banner image row.
     */
    public function bannerPublicUrl(array $row): ?string
    {
        $image = trim((string) ($row['image'] ?? ''));
        if ($image !== '') {
            return asset('uploads/storefront_banners/'.$image);
        }

        $url = trim((string) ($row['url'] ?? ''));
        if ($url === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            return url($url);
        }

        return asset($url);
    }

    /**
     * Sync sells_online flags on business_locations from the settings selection.
     */
    private function syncSellingLocations(int $businessId, array $locationIds): void
    {
        $locationIds = array_map('intval', $locationIds);

        BusinessLocation::where('business_id', $businessId)
            ->update(['sells_online' => false]);

        if (! empty($locationIds)) {
            BusinessLocation::where('business_id', $businessId)
                ->whereIn('id', $locationIds)
                ->update(['sells_online' => true]);
        }
    }
}
