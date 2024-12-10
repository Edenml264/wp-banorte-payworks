<?php
class WC_Banorte_Payworks_Gateway extends WC_Payment_Gateway {
    public function __construct() {
        $this->id = 'banorte_payworks';
        $this->icon = ''; // URL to the icon that will be displayed on checkout
        $this->has_fields = true;
        $this->method_title = 'Banorte Payworks';
        $this->method_description = 'Procesa pagos a través de Banorte Payworks';

        // Load the form fields
        $this->init_form_fields();
        
        // Load the settings
        $this->init_settings();
        
        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = 'yes' === $this->get_option('testmode');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->terminal_id = $this->get_option('terminal_id');
        $this->user_id = $this->get_option('user_id');
        $this->password = $this->get_option('password');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => 'Activar/Desactivar',
                'label'       => 'Activar Banorte Payworks',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'title' => array(
                'title'       => 'Título',
                'type'        => 'text',
                'description' => 'Esto controla el título que el usuario ve durante el checkout.',
                'default'     => 'Pago con tarjeta (Banorte)',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Descripción',
                'type'        => 'textarea',
                'description' => 'Esto controla la descripción que el usuario ve durante el checkout.',
                'default'     => 'Paga de forma segura usando tu tarjeta de crédito o débito a través de Banorte.',
            ),
            'testmode' => array(
                'title'       => 'Modo de pruebas',
                'label'       => 'Habilitar modo de pruebas',
                'type'        => 'checkbox',
                'description' => 'Coloca el gateway en modo de pruebas.',
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'merchant_id' => array(
                'title'       => 'Merchant ID',
                'type'        => 'text',
                'description' => 'Obtenido de Banorte Payworks',
            ),
            'terminal_id' => array(
                'title'       => 'Terminal ID',
                'type'        => 'text',
                'description' => 'Obtenido de Banorte Payworks',
            ),
            'user_id' => array(
                'title'       => 'User ID',
                'type'        => 'text',
                'description' => 'Obtenido de Banorte Payworks',
            ),
            'password' => array(
                'title'       => 'Password',
                'type'        => 'password',
                'description' => 'Obtenido de Banorte Payworks',
            ),
        );
    }

    public function process_payment($order_id) {
        // Aquí irá la lógica de procesamiento del pago
        // Por implementar
    }
}