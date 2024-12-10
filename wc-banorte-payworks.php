<?php
/*
Plugin Name: WooCommerce Banorte Payworks Gateway
Plugin URI: https://github.com/Edenml264/wp-banorte-payworks
Description: Integración de la pasarela de pago Banorte Payworks para WooCommerce
Version: 1.0.0
Author: Eden Mendez
Author URI: edenmendez.com
Text Domain: wc-banorte-payworks
Domain Path: /languages
WC requires at least: 3.0.0
WC tested up to: 8.4.0
*/

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WC_BANORTE_VERSION', '1.0.0');
define('WC_BANORTE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_BANORTE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Ensure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// Initialize the gateway
add_action('plugins_loaded', 'init_banorte_payworks_gateway');

function init_banorte_payworks_gateway() {
    require_once WC_BANORTE_PLUGIN_DIR . 'includes/class-wc-banorte-payworks-gateway.php';
    add_filter('woocommerce_payment_gateways', 'add_banorte_payworks_gateway');
}

function add_banorte_payworks_gateway($gateways) {
    $gateways[] = 'WC_Banorte_Payworks_Gateway';
    return $gateways;
}

// Add settings link on plugin page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'banorte_payworks_settings_link');

function banorte_payworks_settings_link($links) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=banorte_payworks">' . __('Settings', 'wc-banorte-payworks') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}