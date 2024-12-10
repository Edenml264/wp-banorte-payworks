<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Banorte_Payworks_Activator {
    public static function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'banorte_transacciones';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            id_order bigint(20) NOT NULL,
            transaccion text NOT NULL,
            transaccion_caduca text NOT NULL,
            transaccion_digitos text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY id_order (id_order)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function deactivate() {
        // No eliminamos la tabla en la desactivación para preservar los datos
    }

    public static function uninstall() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'banorte_transacciones';
        $sql = "DROP TABLE IF EXISTS $table_name;";
        $wpdb->query($sql);
    }
}