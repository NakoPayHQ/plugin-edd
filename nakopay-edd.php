<?php
/**
 * Plugin Name: NakoPay for Easy Digital Downloads
 * Plugin URI:  https://nakopay.com/integrations/edd
 * Description: Accept Bitcoin and crypto for EDD downloads. Non-custodial, wallet-to-wallet.
 * Version: 0.2.0
 * Author:      NakoPay
 * Author URI:  https://nakopay.com
 * License:     MIT
 * Text Domain: nakopay-edd
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('NAKOPAY_EDD_VERSION', '0.1.0');
define('NAKOPAY_EDD_DIR', plugin_dir_path(__FILE__));
define('NAKOPAY_EDD_URL', plugin_dir_url(__FILE__));
define('NAKOPAY_EDD_FILE', __FILE__);

require_once NAKOPAY_EDD_DIR . 'includes/bootstrap.php';
