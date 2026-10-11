<?php

return [
    /*
     * Comma-separated proxy IPs/CIDRs whose X-Forwarded-* headers are trusted
     * (e.g. Docker networks + Cloudflare ranges behind Traefik). Empty keeps
     * the legacy behaviour of trusting the immediate caller only.
     */
    'proxies' => env('TRUSTED_PROXIES'),
];
