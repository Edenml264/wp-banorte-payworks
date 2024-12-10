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
Requires PHP: 7.2
WooCommerce: true
*/

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WC_BANORTE_VERSION', '1.0.0');
define('WC_BANORTE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_BANORTE_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once plugin_dir_path(__FILE__) . 'includes/class-wc-banorte-payworks-activator.php';

// Hooks de activación/desactivación
register_activation_hook(__FILE__, array('WC_Banorte_Payworks_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('WC_Banorte_Payworks_Activator', 'deactivate'));
register_uninstall_hook(__FILE__, array('WC_Banorte_Payworks_Activator', 'uninstall'));

/**
 * Verificar que todos los archivos necesarios existen
 */
function wc_banorte_payworks_check_files() {
    $required_files = array(
        'includes/class-wc-banorte-payworks-logger.php',
        'includes/class-wc-banorte-payworks-api.php',
        'includes/class-wc-banorte-payworks-gateway.php'
    );

    $missing_files = array();
    foreach ($required_files as $file) {
        if (!file_exists(WC_BANORTE_PLUGIN_DIR . $file)) {
            $missing_files[] = $file;
        }
    }

    if (!empty($missing_files)) {
        add_action('admin_notices', function() use ($missing_files) {
            $class = 'notice notice-error';
            $message = sprintf(
                __('Banorte Payworks Gateway: Faltan archivos requeridos: %s', 'wc-banorte-payworks'),
                implode(', ', $missing_files)
            );
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
        });
        return false;
    }

    return true;
}

/**
 * Verificar que WooCommerce está instalado y activo
 */
function wc_banorte_payworks_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            $class = 'notice notice-error';
            $message = sprintf(
                __('Banorte Payworks Gateway requiere que WooCommerce esté instalado y activo. Puedes descargar %s aquí.', 'wc-banorte-payworks'),
                '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
            );
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
        });
        return false;
    }
    return true;
}

/**
 * Inicializar el plugin
 */
function init_banorte_payworks_gateway() {
    // Verificar requisitos
    if (!wc_banorte_payworks_check_woocommerce() || !wc_banorte_payworks_check_files()) {
        return;
    }

    try {
        // Cargar archivos necesarios
        require_once WC_BANORTE_PLUGIN_DIR . 'includes/class-wc-banorte-payworks-logger.php';
        require_once WC_BANORTE_PLUGIN_DIR . 'includes/class-wc-banorte-payworks-api.php';
        require_once WC_BANORTE_PLUGIN_DIR . 'includes/class-wc-banorte-payworks-gateway.php';

        // Registrar el gateway
        add_filter('woocommerce_payment_gateways', 'add_banorte_payworks_gateway');
    } catch (Exception $e) {
        add_action('admin_notices', function() use ($e) {
            $class = 'notice notice-error';
            $message = sprintf(
                __('Error al inicializar Banorte Payworks Gateway: %s', 'wc-banorte-payworks'),
                $e->getMessage()
            );
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
        });
    }
}

function add_banorte_payworks_gateway($gateways) {
    if (class_exists('WC_Banorte_Payworks_Gateway')) {
        $gateways[] = 'WC_Banorte_Payworks_Gateway';
    }
    return $gateways;
}

// Declarar compatibilidad con características de WooCommerce
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

// Inicializar el plugin cuando todos los plugins estén cargados
add_action('plugins_loaded', 'init_banorte_payworks_gateway');

// Agregar enlace de configuración
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'banorte_payworks_settings_link');

function banorte_payworks_settings_link($links) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=banorte_payworks">' . __('Settings', 'wc-banorte-payworks') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}