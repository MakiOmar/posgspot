<?php

return [
    /*
     * Comma-separated proxy IPs/CIDRs whose X-Forwarded-* headers are trusted
     * (e.g. Docker networks + Cloudflare ranges behind Traefik). Empty trusts
     * no proxy: X-Forwarded-* is ignored and the socket address is the client IP.
     */
    'proxies' => env('TRUSTED_PROXIES'),
];
