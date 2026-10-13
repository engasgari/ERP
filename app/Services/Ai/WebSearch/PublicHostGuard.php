<?php

namespace App\Services\Ai\WebSearch;

/**
 * SSRF guard: only hosts that resolve exclusively to public IP addresses may be fetched.
 */
class PublicHostGuard
{
    public function isPublicHost(string $host): bool
    {
        $host = trim($host, '[]');

        if ($host === '' || strtolower($host) === 'localhost') {
            return false;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
}
