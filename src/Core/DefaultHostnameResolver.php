<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelSso\Core;

use CreativeCrafts\LaravelSso\Contracts\Core\HostnameResolver;

final class DefaultHostnameResolver implements HostnameResolver
{
    public function resolve(string $hostname): array
    {
        $hostname = trim($hostname, '[]');

        if ($hostname === '') {
            return [];
        }

        if (filter_var($hostname, FILTER_VALIDATE_IP) !== false) {
            return [$hostname];
        }

        $addresses = [];

        $aRecords = @dns_get_record($hostname, DNS_A);
        if (is_array($aRecords)) {
            foreach ($aRecords as $record) {
                $ip = $record['ip'] ?? null;

                if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                    $addresses[] = $ip;
                }
            }
        }

        $aaaaRecords = @dns_get_record($hostname, DNS_AAAA);
        if (is_array($aaaaRecords)) {
            foreach ($aaaaRecords as $record) {
                $ipv6 = $record['ipv6'] ?? null;

                if (is_string($ipv6) && filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                    $addresses[] = $ipv6;
                }
            }
        }

        $hostAddresses = @gethostbynamel($hostname);
        if (is_array($hostAddresses)) {
            foreach ($hostAddresses as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                    $addresses[] = $ip;
                }
            }
        }

        return array_values(array_unique($addresses));
    }
}
