<?php
/**
 * EDD Gateway - process purchase flow.
 *
 * Flow:
 *   1) Customer selects NakoPay at checkout
 *   2) nakopay_edd_process_purchase() creates a pending payment
 *   3) Calls NakoPay API to create an invoice
 *   4) Redirects customer to NakoPay hosted checkout
 *   5) Webhook fires on payment, completes the EDD payment
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Process the purchase - called when customer submits checkout with NakoPay.
 */
function nakopay_edd_process_purchase(array $purchase_data): void
{
    $client = new NakoPay_EDD_Client();

    if ($client->getApiKey() === '') {
        edd_set_error('nakopay_error', __('NakoPay is not configured. Please contact the store owner.', 'nakopay-edd'));
        edd_send_back_to_checkout('?payment-mode=nakopay');
        return;
    }

    // Create pending payment in EDD
    $payment_data = [
        'price'        => $purchase_data['price'],
        'date'         => $purchase_data['date'],
        'user_email'   => $purchase_data['user_email'],
        'purchase_key'  => $purchase_data['purchase_key'],
        'currency'     => edd_get_currency(),
        'downloads'    => $purchase_data['downloads'],
        'cart_details' => $purchase_data['cart_details'],
        'user_info'    => $purchase_data['user_info'],
        'status'       => 'pending',
        'gateway'      => 'nakopay',
    ];

    $payment_id = edd_insert_payment($payment_data);
    if (!$payment_id) {
        edd_set_error('nakopay_error', __('Could not create payment record. Please try again.', 'nakopay-edd'));
        edd_send_back_to_checkout('?payment-mode=nakopay');
        return;
    }

    // Create NakoPay invoice
    $result = $client->createInvoice([
        'amount'         => $purchase_data['price'],
        'currency'       => edd_get_currency(),
        'description'    => sprintf('EDD Order #%d', $payment_id),
        'customer_email' => $purchase_data['user_email'],
        'edd_payment_id' => $payment_id,
    ]);

    if (!($result['_ok'] ?? false) || empty($result['id'])) {
        edd_update_payment_status($payment_id, 'failed');
        $msg = $result['_error'] ?? $result['message'] ?? 'Unknown error';
        edd_set_error('nakopay_error', sprintf(
            __('Could not create NakoPay invoice: %s', 'nakopay-edd'),
            esc_html($msg)
        ));
        edd_send_back_to_checkout('?payment-mode=nakopay');
        return;
    }

    // Store invoice ID on the payment
    edd_update_payment_meta($payment_id, '_nakopay_invoice_id', sanitize_text_field($result['id']));

    if (!empty($result['checkout_url'])) {
        edd_update_payment_meta($payment_id, '_nakopay_checkout_url', esc_url_raw($result['checkout_url']));
    }

    // Empty the cart
    edd_empty_cart();

    // Redirect to NakoPay hosted checkout
    $redirect = $result['checkout_url'] ?? '';
    if ($redirect === '') {
        // Fallback to EDD purchase confirmation page
        $redirect = edd_get_success_page_uri();
    }

    wp_redirect($redirect);
    exit;
}
