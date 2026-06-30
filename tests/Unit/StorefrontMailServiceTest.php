<?php

namespace Tests\Unit;

use App\Services\Storefront\StorefrontMailService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class StorefrontMailServiceTest extends TestCase
{
    public function test_resolve_from_never_returns_empty_address(): void
    {
        Config::set('mail.from.address', '');
        Config::set('mail.from.name', '');
        Config::set('mail.mailers.smtp.username', '');
        Config::set('app.url', 'https://posstaging.gamesspoteg.com');

        $from = app(StorefrontMailService::class)->resolveFrom(1);

        $this->assertNotSame('', $from['address']);
        $this->assertNotSame('', $from['name']);
        $this->assertStringContainsString('@', $from['address']);
    }
}
