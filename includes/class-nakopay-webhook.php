<?php
/**
 * NakoPay webhook handler for EDD.
 *
 * Listens on: ?edd-listener=nakopay
 * Verifies HMAC-SHA256 signature, updates payment status.
 */

if (!defined('ABSPATH')) {
    exit;
}

class NakoPay_EDD_Webhook
{
    public function handle(): void
    {
        $rawBody   = file_get_contents('php://input');
        $sigHeader = $_SERVER['HTTP_X_NAKOPAY_SIGNATURE'] ?? '';

        $client = new NakoPay_EDD_Client();

        if (!$client->verifyWebhook($rawBody, $sigHeader)) {
            status_header(401);
            echo wp_json_encode(['error' => 'Invalid signature']);
            exit;
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            status_header(400);
            echo wp_json_encode(['error' => 'Invalid JSON']);
            exit;
        }

        $event_type = $payload['type'] ?? '';
        $invoice    = $payload['data'] ?? [];
        $invoice_id = $invoice['id'] ?? '';

        if ($invoice_id === '') {
            status_header(400);
            echo wp_json_encode(['error' => 'Missing invoice ID']);
            exit;
        }

        // Find the EDD payment by invoice ID
        $payment_id = $this->findPaymentByInvoice($invoice_id);
        if (!$payment_id) {
            status_header(404);
            echo wp_json_encode(['error' => 'Payment not found']);
            exit;
        }

        switch ($event_type) {
            case 'invoice.paid':
                $this->handlePaid($payment_id, $invoice);
                break;

            case 'invoice.expired':
                $this->handleExpired($payment_id);
                break;

            case 'invoice.canceled':
                $this->handleCanceled($payment_id);
                break;

            default:
                // Acknowledge unknown events without error
                break;
        }

        status_header(200);
        echo wp_json_encode(['ok' => true]);
        exit;
    }

    private function findPaymentByInvoice(string $invoice_id): ?int
    {
        global $wpdb;

        $payment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_nakopay_invoice_id' AND meta_value = %s LIMIT 1",
            $invoice_id
        ));

        return $payment_id ? (int) $payment_id : null;
    }

    private function handlePaid(int $payment_id, array $invoice): void
    {
        $payment = new EDD_Payment($payment_id);
        if ($payment->status === 'complete') {
            return; // Already completed, idempotent
        }

        // Store transaction data
        if (!empty($invoice['txid'])) {
            edd_update_payment_meta($payment_id, '_nakopay_txid', sanitize_text_field($invoice['txid']));
        }
        if (!empty($invoice['paid_amount'])) {
            edd_update_payment_meta($payment_id, '_nakopay_paid_amount', sanitize_text_field($invoice['paid_amount']));
        }
        if (!empty($invoice['coin'])) {
            edd_update_payment_meta($payment_id, '_nakopay_coin', sanitize_text_field($invoice['coin']));
        }

        edd_insert_payment_note($payment_id, sprintf(
            __('NakoPay payment confirmed. Invoice: %s, Txid: %s', 'nakopay-edd'),
            $invoice['id'] ?? 'n/a',
            $invoice['txid'] ?? 'n/a'
        ));

        edd_update_payment_status($payment_id, 'complete');
    }

    private function handleExpired(int $payment_id): void
    {
        $payment = new EDD_Payment($payment_id);
        if ($payment->status === 'complete') {
            return; // Don't expire a completed payment
        }

        edd_insert_payment_note($payment_id, __('NakoPay invoice expired - customer did not pay in time.', 'nakopay-edd'));
        edd_update_payment_status($payment_id, 'failed');
    }

    private function handleCanceled(int $payment_id): void
    {
        $payment = new EDD_Payment($payment_id);
        if ($payment->status === 'complete') {
            return;
        }

        edd_insert_payment_note($payment_id, __('NakoPay invoice was canceled.', 'nakopay-edd'));
        edd_update_payment_status($payment_id, 'failed');
    }
}
