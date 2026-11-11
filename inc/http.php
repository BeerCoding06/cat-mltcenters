<?php

/**
 * GET a URL from a public API / feed. Returns the body, or throws.
 * Only http(s), at most 3 redirects, 2 MB, and a descriptive User-Agent (Wikimedia asks for one).
 */
function http_get(string $url, array $headers = [], int $timeout = 20): string
{
    if (!is_http_url($url)) {
        throw new RuntimeException('Not an http(s) URL: ' . $url);
    }
    $site = (string) config('site_url', '') ?: 'local development';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => 'DealTasMaew/1.0 (' . $site . ')',
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_ENCODING       => '',
        CURLOPT_NOPROGRESS     => false,
        CURLOPT_PROGRESSFUNCTION => function ($ch, $downTotal, $downDone, $upTotal, $upDone) {
            return $downDone > 2 * 1024 * 1024 ? 1 : 0; // abort anything over 2 MB
        },
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        throw new RuntimeException('Request failed: ' . $error);
    }
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('HTTP ' . $status . ' from ' . parse_url($url, PHP_URL_HOST));
    }
    return (string) $body;
}

function http_get_json(string $url, array $headers = []): array
{
    $data = json_decode(http_get($url, array_merge(['Accept: application/json'], $headers)), true);
    if (!is_array($data)) {
        throw new RuntimeException('Not JSON: ' . $url);
    }
    return $data;
}

/**
 * POST a form to an API and decode the JSON reply. Returns [status, data]; does not throw on HTTP errors,
 * since APIs such as Facebook's explain the problem in the body.
 */
/** POSTs $payload as a JSON body and returns the HTTP status (the body is not needed by callers). */
function http_post_raw_json(string $url, array $payload, int $timeout = 20, array $headers = []): int
{
    if (!is_http_url($url)) {
        throw new RuntimeException('Not an http(s) URL: ' . $url);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json; charset=utf-8'], $headers),
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        throw new RuntimeException('Request failed: ' . $error);
    }
    return $status;
}

function http_post_json(string $url, array $fields, int $timeout = 20): array
{
    if (!is_http_url($url)) {
        throw new RuntimeException('Not an http(s) URL: ' . $url);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        throw new RuntimeException('Request failed: ' . $error);
    }
    $data = json_decode((string) $body, true);
    return [$status, is_array($data) ? $data : []];
}
