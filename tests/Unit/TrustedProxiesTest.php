<?php

namespace Tests\Unit;

use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Behind Cloudflare -> Traefik the visitor IP must resolve from X-Forwarded-For.
 */
class TrustedProxiesTest extends TestCase
{
    protected function tearDown(): void
    {
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        parent::tearDown();
    }

    private function clientIpThroughMiddleware(string $remoteAddr, string $forwardedFor): string
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => $remoteAddr,
            'HTTP_X_FORWARDED_FOR' => $forwardedFor,
        ]);

        return app(TrustProxies::class)->handle($request, fn (Request $r) => response($r->ip()))->getContent();
    }

    public function test_configured_cloudflare_and_docker_ranges_resolve_the_visitor_ip(): void
    {
        config(['trustedproxy.proxies' => '172.16.0.0/12,173.245.48.0/20']);

        $this->assertSame('41.33.10.5', $this->clientIpThroughMiddleware('172.18.0.4', '41.33.10.5, 173.245.48.10'));
    }

    public function test_spoofed_header_from_untrusted_caller_is_ignored(): void
    {
        config(['trustedproxy.proxies' => '172.16.0.0/12,173.245.48.0/20']);

        $this->assertSame('8.8.8.8', $this->clientIpThroughMiddleware('8.8.8.8', '1.2.3.4'));
    }

    public function test_without_config_the_immediate_caller_is_trusted_as_before(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->assertSame('41.33.10.5', $this->clientIpThroughMiddleware('172.18.0.4', '41.33.10.5'));
    }
}
