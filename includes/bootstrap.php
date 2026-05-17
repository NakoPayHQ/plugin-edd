<?php
/**
 * Bootstrap - register the NakoPay gateway with EDD.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check EDD is active before registering.
 */
function nakopay_edd_init(): void
{
    if (!class_exists('Easy_Digital_Downloads')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('NakoPay for EDD requires Easy Digital Downloads to be installed and active.', 'nakopay-edd');
            echo '</p></div>';
        });
        return;
    }

    require_once NAKOPAY_EDD_DIR . 'includes/class-nakopay-client.php';
    require_once NAKOPAY_EDD_DIR . 'includes/class-edd-gateway-nakopay.php';
    require_once NAKOPAY_EDD_DIR . 'includes/class-nakopay-webhook.php';

    // Register gateway
    add_filter('edd_payment_gateways', 'nakopay_edd_register_gateway');

    // Gateway settings
    add_filter('edd_settings_gateways', 'nakopay_edd_gateway_settings');

    // Process purchase
    add_action('edd_gateway_nakopay', 'nakopay_edd_process_purchase');

    // Remove CC form - we use hosted checkout
    add_action('edd_nakopay_cc_form', '__return_false');

    // Webhook listener
    add_action('init', 'nakopay_edd_listen_for_webhook');
}
add_action('plugins_loaded', 'nakopay_edd_init', 20);

/**
 * Register NakoPay as an EDD gateway.
 */
function nakopay_edd_register_gateway(array $gateways): array
{
    $gateways['nakopay'] = [
        'admin_label'    => __('NakoPay (Bitcoin)', 'nakopay-edd'),
        'checkout_label' => __('Bitcoin via NakoPay', 'nakopay-edd'),
        'supports'       => ['buy_now'],
    ];
    return $gateways;
}

/**
 * Gateway settings fields.
 */
function nakopay_edd_gateway_settings(array $settings): array
{
    $nakopay_settings = [
        'nakopay_settings' => [
            'id'   => 'nakopay_settings',
            'name' => '<strong>' . __('NakoPay Settings', 'nakopay-edd') . '</strong>',
            'type' => 'header',
        ],
        'nakopay_api_key' => [
            'id'   => 'nakopay_api_key',
            'name' => __('API Key', 'nakopay-edd'),
            'desc' => __('Enter your NakoPay Secret key (sk_test_* or sk_live_*). Get it at nakopay.com/dashboard/api-keys.', 'nakopay-edd'),
            'type' => 'text',
            'size' => 'regular',
        ],
        'nakopay_webhook_secret' => [
            'id'   => 'nakopay_webhook_secret',
            'name' => __('Webhook Signing Secret', 'nakopay-edd'),
            'desc' => __('Starts with whsec_. Get it at nakopay.com/dashboard/webhooks.', 'nakopay-edd'),
            'type' => 'text',
            'size' => 'regular',
        ],
        'nakopay_webhook_url_info' => [
            'id'   => 'nakopay_webhook_url_info',
            'name' => __('Webhook URL', 'nakopay-edd'),
            'desc' => sprintf(
                __('Set this URL in your NakoPay dashboard: %s', 'nakopay-edd'),
                '<code>' . esc_url(home_url('/?edd-listener=nakopay')) . '</code>'
            ),
            'type' => 'descriptive_text',
        ],
    ];

    return array_merge($settings, ['nakopay' => $nakopay_settings]);
}

/**
 * Listen for incoming NakoPay webhooks.
 */
function nakopay_edd_listen_for_webhook(): void
{
    if (!isset($_GET['edd-listener']) || $_GET['edd-listener'] !== 'nakopay') {
        return;
    }

    $webhook = new NakoPay_EDD_Webhook();
    $webhook->handle();
    exit;
}
