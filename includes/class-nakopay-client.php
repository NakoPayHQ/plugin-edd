<?php
/**
 * NakoPay API client for EDD.
 *
 * Dual base URL strategy (per project memory: plugin-base-urls):
 *   PRIMARY   - https://daslrxpkbkqrbnjwouiq.supabase.co/functions/v1/
 *   FALLBACK  - https://api.nakopay.com/v1/ (reserved for future)
 */

if (!defined('ABSPATH')) {
    exit;
}

class NakoPay_EDD_Client
{
    const VERSION       = '0.1.0';
    const BASE_PRIMARY  = 'https://daslrxpkbkqrbnjwouiq.supabase.co/functions/v1/';
    const BASE_FALLBACK = 'https://api.nakopay.com/v1/';
    const SIG_TOLERANCE = 300;

    public function getBaseUrl(): string
    {
        if (defined('NAKOPAY_API_BASE') && is_string(NAKOPAY_API_BASE) && NAKOPAY_API_BASE !== '') {
            return rtrim(NAKOPAY_API_BASE, '/') . '/';
        }
        return self::BASE_PRIMARY;
    }

    /**
     * The v1 contract uses kebab-case pass-through paths
     * (`/v1/invoices-create`, `/v1/invoices-get`, ...).
     * Both the Supabase functions base and the future api.nakopay.com
     * base serve the exact same path - no translation required.
     */
    public function resolveEndpoint(string $name): string
    {
        return $name;
    }

    public function getApiKey(): string
    {
        return trim((string) edd_get_option('nakopay_api_key', ''));
    }

    public function getWebhookSecret(): string
    {
        return trim((string) edd_get_option('nakopay_webhook_secret', ''));
    }

    public function isTestMode(): bool
    {
        return edd_is_test_mode() || str_starts_with($this->getApiKey(), 'sk_test_');
    }

    private function request(string $method, string $endpoint, ?array $body = null): array
    {
        $apiKey = $this->getApiKey();
        if ($apiKey === '') {
            return ['_ok' => false, '_status' => 0, '_error' => 'NakoPay API key is not configured.'];
        }

        $url  = $this->getBaseUrl() . ltrim($this->resolveEndpoint($endpoint), '/');
        $args = [
            'method'  => strtoupper($method),
            'timeout' => 20,
            'headers' => [
                'Authorization'     => 'Bearer ' . $apiKey,
                'Accept'            => 'application/json',
                'User-Agent'        => 'NakoPay-EDD/' . self::VERSION,
                'X-NakoPay-Version' => '2025-04-20',
            ],
        ];
        if ($body !== null) {
            $args['headers']['Content-Type']  = 'application/json';
            $args['headers']['Idempotency-Key'] = 'idem_' . bin2hex(random_bytes(16));
            $args['body'] = wp_json_encode($body);
        }

        $resp = wp_remote_request($url, $args);
        if (is_wp_error($resp)) {
            return ['_ok' => false, '_status' => 0, '_error' => $resp->get_error_message()];
        }
        $status = (int) wp_remote_retrieve_response_code($resp);
        $raw    = (string) wp_remote_retrieve_body($resp);
        $json   = json_decode($raw, true);
        if (!is_array($json)) {
            return ['_ok' => false, '_status' => $status, '_error' => 'invalid json', '_raw' => $raw];
        }
        $json['_ok']     = $status >= 200 && $status < 300;
        $json['_status'] = $status;
        return $json;
    }

    public function createInvoice(array $args): array
    {
        return $this->request('POST', 'invoices-create', [
            'amount'         => (string) $args['amount'],
            'currency'       => strtoupper((string) ($args['currency'] ?? 'USD')),
            'coin'           => strtoupper((string) ($args['coin'] ?? 'BTC')),
            'description'    => (string) ($args['description'] ?? 'EDD purchase'),
            'customer_email' => (string) ($args['customer_email'] ?? ''),
            'metadata'       => array_filter([
                'edd_payment_id' => $args['edd_payment_id'] ?? null,
                'source'         => 'edd',
            ], fn($v) => $v !== null && $v !== ''),
        ]);
    }

    public function getInvoice(string $id): array
    {
        return $this->request('GET', 'invoices-get?id=' . rawurlencode($id));
    }

    public function verifyWebhook(string $rawBody, string $sigHeader): bool
    {
        $secret = $this->getWebhookSecret();
        if ($secret === '' || $sigHeader === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $sigHeader) as $kv) {
            $kv = trim($kv);
            if ($kv === '' || strpos($kv, '=') === false) continue;
            [$k, $v] = explode('=', $kv, 2);
            $parts[trim($k)] = trim($v);
        }
        if (empty($parts['t']) || empty($parts['v1'])) {
            return false;
        }

        $t = (int) $parts['t'];
        if (abs(time() - $t) > self::SIG_TOLERANCE) {
            return false;
        }

        $expected = hash_hmac('sha256', $t . '.' . $rawBody, $secret);
        return hash_equals($expected, $parts['v1']);
    }
}
