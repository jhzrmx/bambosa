<?php
/**
 * IP address lookup for the admin "User Login Logs" → IP details page
 * (admin/ip.php), using the free ip-api.com JSON API.
 *
 * NOTES ABOUT THE SERVICE:
 *   - The free endpoint is HTTP only (HTTPS needs a paid key), so the IP
 *     address being looked up travels unencrypted to ip-api.com. Nothing
 *     else is sent (no user names, emails or sessions). To use HTTPS with a
 *     paid key, change IP_API_BASE (and append the key in lookup_ip()).
 *   - The free tier allows ~45 requests per minute per server and is for
 *     non-commercial use — see https://ip-api.com/docs.
 *   - The lookup runs on the SERVER (not in the browser): a browser on an
 *     HTTPS page may not call an HTTP API (mixed content), and it keeps the
 *     lookup admin-only.
 *   - Successful results are cached for 24 hours in the system temp folder
 *     so repeated clicks don't burn the rate limit.
 *
 * PHP 7.4-compatible.
 */

const IP_API_BASE = 'http://ip-api.com/json/';
const IP_API_FIELDS = 'status,message,continent,country,countryCode,region,regionName,city,district,zip,lat,lon,timezone,offset,currency,isp,org,as,asname,reverse,mobile,proxy,hosting,query';
const IP_LOOKUP_CACHE_TTL = 86400; // seconds

/** True for a syntactically valid IPv4/IPv6 address (public or not). */
function is_valid_ip(string $ip): bool
{
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

/**
 * True for a valid address that is publicly routable. Private ranges
 * (192.168.x.x, 10.x.x.x ...), loopback (127.0.0.1, ::1) and other reserved
 * ranges have no geolocation, so they're not sent to the API.
 */
function is_public_ip(string $ip): bool
{
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

/**
 * Looks up a PUBLIC IP address. Returns:
 *   ['ok' => true,  'data' => array, 'error' => null,   'cached' => bool]
 *   ['ok' => false, 'data' => null,  'error' => string, 'cached' => false]
 */
function lookup_ip(string $ip): array
{
    $fail = function ($message) {
        return ['ok' => false, 'data' => null, 'error' => $message, 'cached' => false];
    };

    if (!is_public_ip($ip)) {
        return $fail('Only public IP addresses can be looked up.');
    }

    // ---- cache ----
    $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bambosa_ip_' . sha1($ip) . '.json';
    if (is_file($cacheFile) && (time() - (int) filemtime($cacheFile)) < IP_LOOKUP_CACHE_TTL) {
        $cached = json_decode((string) @file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            return ['ok' => true, 'data' => $cached, 'error' => null, 'cached' => true];
        }
    }

    // ---- request ($ip is already validated, so it's safe in the URL) ----
    $url = IP_API_BASE . $ip . '?fields=' . IP_API_FIELDS;
    $body = false;
    $httpCode = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $context = stream_context_create(['http' => ['timeout' => 6, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $context);
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $httpCode = (int) $m[1];
        }
    }

    if ($httpCode === 429) {
        return $fail('The lookup service is rate-limited right now (free tier: about 45 requests per minute). Please wait a moment and try again.');
    }
    if ($body === false || $body === '') {
        return $fail('Could not reach the IP lookup service (ip-api.com). Check the server\'s internet connection and try again.');
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        return $fail('The IP lookup service returned an unexpected response.');
    }
    if (($data['status'] ?? '') !== 'success') {
        $reason = isset($data['message']) ? (string) $data['message'] : 'unknown reason';
        return $fail('No information available for this address (' . $reason . ').');
    }

    @file_put_contents($cacheFile, json_encode($data));
    return ['ok' => true, 'data' => $data, 'error' => null, 'cached' => false];
}

/** "UTC+08:00" style label from ip-api's offset in seconds. */
function format_utc_offset(int $seconds): string
{
    $sign = $seconds < 0 ? '-' : '+';
    $abs = abs($seconds);
    return sprintf('UTC%s%02d:%02d', $sign, intdiv($abs, 3600), intdiv($abs % 3600, 60));
}
