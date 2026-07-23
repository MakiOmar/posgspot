<?php

namespace Tests\Unit\Storefront;

use App\Services\Storefront\StorefrontSettingService;
use Tests\TestCase;

class StorefrontFaviconSettingsTest extends TestCase
{
    public function test_normalize_favicon_prefers_uploaded_image_over_url(): void
    {
        $service = app(StorefrontSettingService::class);
        $row = $service->normalizeFavicon([
            'image' => 'icon.png',
            'url' => 'https://cdn.example.com/other.ico',
        ]);

        $this->assertSame('icon.png', $row['image']);
        $this->assertSame('', $row['url']);
    }

    public function test_normalize_favicon_accepts_https_url(): void
    {
        $service = app(StorefrontSettingService::class);
        $row = $service->normalizeFavicon([
            'image' => null,
            'url' => 'https://cdn.example.com/fav.png',
        ]);

        $this->assertNull($row['image']);
        $this->assertSame('https://cdn.example.com/fav.png', $row['url']);
        $this->assertSame(
            'https://cdn.example.com/fav.png',
            $service->faviconPublicUrl($row)
        );
    }

    public function test_normalize_favicon_rejects_javascript_url(): void
    {
        $service = app(StorefrontSettingService::class);
        $row = $service->normalizeFavicon([
            'url' => 'javascript:alert(1)',
        ]);

        $this->assertNull($row['image']);
        $this->assertSame('', $row['url']);
        $this->assertNull($service->faviconPublicUrl($row));
    }
}
