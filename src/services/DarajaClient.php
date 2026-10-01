<?php
/** Minimal Safaricom Daraja STK Push client. Credentials are environment-only. */
class DarajaClient
{
    private static function config(string $name): string
    {
        $value = trim((string)getenv($name));
        if ($value === '') throw new RuntimeException('M-Pesa configuration is incomplete.');
        return $value;
    }

    private static function baseUrl(): string
    {
        $environment = strtolower(trim((string)(getenv('MPESA_ENV') ?: 'sandbox')));
        if ($environment === 'sandbox') return 'https://sandbox.safaricom.co.ke';
        if ($environment === 'production') return 'https://api.safaricom.co.ke';
        throw new RuntimeException('Invalid MPESA_ENV value.');
    }

    private static function request(string $url, array $headers, ?string $body = null): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('The cURL PHP extension is required for M-Pesa.');
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($curl);
        $statusCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($response === false) throw new RuntimeException('M-Pesa connection failed: ' . $error);
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) throw new RuntimeException('M-Pesa returned an invalid response.');
        if ($statusCode < 200 || $statusCode >= 300) {
            error_log('Daraja HTTP failure: status=' . $statusCode . ' body=' . substr($response, 0, 1000));
            throw new RuntimeException($decoded['errorMessage'] ?? 'M-Pesa request was rejected.');
        }
        return $decoded;
    }

    private static function accessToken(): string
    {
        $cached = function_exists('apcu_fetch') ? apcu_fetch('daraja.oauth.token', $found) : false;
        if ($cached && !empty($found)) return $cached;
        $key = self::config('MPESA_CONSUMER_KEY');
        if (!function_exists('apcu_fetch')) {
            $cachePath = sys_get_temp_dir() . '/assignment-portal-daraja-token-' . hash('sha256', $key . self::baseUrl()) . '.json';
            $cachedFile = @file_get_contents($cachePath);
            $cacheData = $cachedFile ? json_decode($cachedFile, true) : null;
            if (is_array($cacheData) && !empty($cacheData['token']) && (int)($cacheData['expires'] ?? 0) > time()) {
                return (string)$cacheData['token'];
            }
        }
        $secret = self::config('MPESA_CONSUMER_SECRET');
        $url = self::baseUrl() . '/oauth/v1/generate?grant_type=client_credentials';
        $auth = base64_encode($key . ':' . $secret);
        $response = self::request($url, ['Authorization: Basic ' . $auth, 'Accept: application/json']);
        if (empty($response['access_token'])) throw new RuntimeException('M-Pesa did not provide an access token.');
        // Use APCu if available, otherwise a restrictive local cache file so
        // shared-hosting deployments do not request a token for every checkout.
        $ttl = max(30, min(3500, (int)($response['expires_in'] ?? 3600) - 60));
        if (function_exists('apcu_store')) {
            apcu_store('daraja.oauth.token', $response['access_token'], $ttl);
        } else {
            $cachePath = sys_get_temp_dir() . '/assignment-portal-daraja-token-' . hash('sha256', $key . self::baseUrl()) . '.json';
            $cache = json_encode(['token' => $response['access_token'], 'expires' => time() + $ttl], JSON_THROW_ON_ERROR);
            if (@file_put_contents($cachePath, $cache, LOCK_EX) !== false) @chmod($cachePath, 0600);
        }
        return $response['access_token'];
    }

    public static function initiateStkPush(string $phone, int $amount, int $paymentId): array
    {
        if ($amount < 1) throw new InvalidArgumentException('The course has no payable fee.');
        $shortcode = self::config('MPESA_SHORTCODE');
        $timestamp = date('YmdHis');
        $password = base64_encode($shortcode . self::config('MPESA_PASSKEY') . $timestamp);
        $callbackUrl = self::callbackUrl();
        $payload = [
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => $shortcode,
            'PhoneNumber' => $phone,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => 'COURSE' . $paymentId,
            'TransactionDesc' => 'Course access payment',
        ];
        if (strtolower((string)(getenv('MPESA_ENV') ?: 'sandbox')) === 'production'
            && !str_starts_with(strtolower($payload['CallBackURL']), 'https://')) {
            throw new RuntimeException('Production M-Pesa requires an HTTPS callback URL.');
        }
        return self::request(self::baseUrl() . '/mpesa/stkpush/v1/processrequest', [
            'Authorization: Bearer ' . self::accessToken(),
            'Content-Type: application/json',
            'Accept: application/json',
        ], json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private static function callbackUrl(): string
    {
        $url = self::config('MPESA_CALLBACK_URL');
        $secret = self::config('MPESA_CALLBACK_SECRET');
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['https', 'http'], true)) {
            throw new RuntimeException('Invalid M-Pesa callback URL.');
        }
        $query = [];
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
        $query['token'] = $secret;
        $parts = parse_url($url);
        $base = ($parts['scheme'] ?? '') . '://' . ($parts['host'] ?? '')
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . ($parts['path'] ?? '');
        return $base . '?' . http_build_query($query);
    }
}
