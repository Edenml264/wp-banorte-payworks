<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/class-wc-banorte-payworks-api.php';

class WC_Banorte_Payworks_Gateway extends WC_Payment_Gateway {
    /** @var WC_Banorte_Payworks_API */
    private $api;

    public function __construct() {
        $this->id = 'banorte_payworks';
        $this->icon = apply_filters('woocommerce_banorte_icon', '');
        $this->has_fields = true;
        $this->method_title = __('Banorte Payworks', 'wc-banorte-payworks');
        $this->method_description = __('Procesa pagos a través de Banorte Payworks', 'wc-banorte-payworks');
        
        $this->supports = array(
            'products',
            'refunds'
        );

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

        // Inicializar la API
        $this->api = new WC_Banorte_Payworks_API(
            $this->merchant_id,
            $this->terminal_id,
            $this->user_id,
            $this->password,
            $this->testmode
        );

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_api_wc_banorte_payworks_gateway', array($this, 'webhook_handler'));

        // Agregar scripts y estilos
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __('Activar/Desactivar', 'wc-banorte-payworks'),
                'label'       => __('Activar Banorte Payworks', 'wc-banorte-payworks'),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'title' => array(
                'title'       => __('Título', 'wc-banorte-payworks'),
                'type'        => 'text',
                'description' => __('Esto controla el título que el usuario ve durante el checkout.', 'wc-banorte-payworks'),
                'default'     => __('Pago con tarjeta (Banorte)', 'wc-banorte-payworks'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Descripción', 'wc-banorte-payworks'),
                'type'        => 'textarea',
                'description' => __('Esto controla la descripción que el usuario ve durante el checkout.', 'wc-banorte-payworks'),
                'default'     => __('Paga de forma segura usando tu tarjeta de crédito o débito a través de Banorte.', 'wc-banorte-payworks'),
            ),
            'testmode' => array(
                'title'       => __('Modo de pruebas', 'wc-banorte-payworks'),
                'label'       => __('Habilitar modo de pruebas', 'wc-banorte-payworks'),
                'type'        => 'checkbox',
                'description' => __('Coloca el gateway en modo de pruebas.', 'wc-banorte-payworks'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'merchant_id' => array(
                'title'       => __('Merchant ID', 'wc-banorte-payworks'),
                'type'        => 'text',
                'description' => __('Obtenido de Banorte Payworks', 'wc-banorte-payworks'),
            ),
            'terminal_id' => array(
                'title'       => __('Terminal ID', 'wc-banorte-payworks'),
                'type'        => 'text',
                'description' => __('Obtenido de Banorte Payworks', 'wc-banorte-payworks'),
            ),
            'user_id' => array(
                'title'       => __('User ID', 'wc-banorte-payworks'),
                'type'        => 'text',
                'description' => __('Obtenido de Banorte Payworks', 'wc-banorte-payworks'),
            ),
            'password' => array(
                'title'       => __('Password', 'wc-banorte-payworks'),
                'type'        => 'password',
                'description' => __('Obtenido de Banorte Payworks', 'wc-banorte-payworks'),
            ),
        );
    }
    public function payment_scripts() {
        if (!is_checkout() || 'no' === $this->enabled) {
            return;
        }

        // Enqueue JavaScript
        wp_enqueue_script(
            'wc-banorte-payworks',
            WC_BANORTE_PLUGIN_URL . 'assets/js/banorte-payworks.js',
            array('jquery'),
            WC_BANORTE_VERSION,
            true
        );

        // Enqueue CSS
        wp_enqueue_style(
            'wc-banorte-payworks',
            WC_BANORTE_PLUGIN_URL . 'assets/css/banorte-payworks.css',
            array(),
            WC_BANORTE_VERSION
        );

        wp_localize_script('wc-banorte-payworks', 'wc_banorte_params', array(
            'is_test_mode' => $this->testmode
        ));
    }

    public function payment_fields() {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }
        ?>
        <div id="banorte-payworks-form">
            <p class="form-row form-row-wide">
                <label for="banorte_payworks-card-number"><?php esc_html_e('Número de Tarjeta', 'wc-banorte-payworks'); ?> <span class="required">*</span></label>
                <input id="banorte_payworks-card-number" class="input-text" type="text" maxlength="20" autocomplete="off" placeholder="•••• •••• •••• ••••" />
            </p>

            <p class="form-row form-row-first">
                <label for="banorte_payworks-card-expiry"><?php esc_html_e('Fecha de Expiración (MM/YY)', 'wc-banorte-payworks'); ?> <span class="required">*</span></label>
                <input id="banorte_payworks-card-expiry" class="input-text" type="text" autocomplete="off" placeholder="MM / YY" />
            </p>

            <p class="form-row form-row-last">
                <label for="banorte_payworks-card-cvc"><?php esc_html_e('Código de Seguridad', 'wc-banorte-payworks'); ?> <span class="required">*</span></label>
                <input id="banorte_payworks-card-cvc" class="input-text" type="text" autocomplete="off" placeholder="CVC" maxlength="4" />
            </p>
            <div class="clear"></div>
        </div>
        <?php
    }

    public function validate_fields() {
        if (empty($_POST['banorte_payworks-card-number']) ||
            empty($_POST['banorte_payworks-card-expiry']) ||
            empty($_POST['banorte_payworks-card-cvc'])) {
            wc_add_notice(__('Por favor ingresa los datos de tu tarjeta.', 'wc-banorte-payworks'), 'error');
            return false;
        }
        return true;
    }

    public function process_payment($order_id) {
        global $woocommerce;
        $order = wc_get_order($order_id);

        try {
            // Obtener los datos de la tarjeta del POST
            $card_data = array(
                'number' => str_replace(' ', '', $_POST['banorte_payworks-card-number']),
                'expiry' => str_replace(' / ', '', $_POST['banorte_payworks-card-expiry']),
                'cvv' => $_POST['banorte_payworks-card-cvc']
            );

            // Procesar el pago a través de la API
            $payment_result = $this->api->process_payment(
                $card_data,
                $order->get_total(),
                $order_id,
                $order->get_currency()
            );
            
            if ($payment_result['success']) {
                // Pago exitoso
                $order->payment_complete($payment_result['transaction_id']);
                $order->add_order_note(
                    sprintf(
                        __('Pago procesado exitosamente via Banorte Payworks (ID: %s, Auth: %s)', 'wc-banorte-payworks'),
                        $payment_result['transaction_id'],
                        $payment_result['auth_code']
                    )
                );

                // Vaciar el carrito
                $woocommerce->cart->empty_cart();

                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            } else {
                throw new Exception($payment_result['error_message']);
            }
        } catch (Exception $e) {
            wc_add_notice(__('Error en el pago: ', 'wc-banorte-payworks') . $e->getMessage(), 'error');
            return array(
                'result' => 'fail',
                'redirect' => ''
            );
        }
    }

    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_Error('invalid_order', __('Orden inválida', 'wc-banorte-payworks'));
        }

        $transaction_id = $order->get_transaction_id();

        if (!$transaction_id) {
            return new WP_Error('invalid_transaction', __('No se encontró el ID de transacción', 'wc-banorte-payworks'));
        }

        try {
            $refund_result = $this->api->process_refund($transaction_id, $amount, $order_id);

            if ($refund_result['success']) {
                $order->add_order_note(
                    sprintf(
                        __('Reembolso procesado via Banorte Payworks por %s. ID de reembolso: %s', 'wc-banorte-payworks'),
                        wc_price($amount),
                        $refund_result['transaction_id']
                    )
                );
                return true;
            } else {
                return new WP_Error('refund_failed', $refund_result['error_message']);
            }
        } catch (Exception $e) {
            return new WP_Error('refund_failed', $e->getMessage());
        }
    }

    public function webhook_handler() {
        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);

        if (!$data) {
            status_header(400);
            exit('Invalid webhook payload');
        }

        // Verificar la autenticidad del webhook
        if (!$this->verify_webhook_signature($payload)) {
            status_header(401);
            exit('Invalid signature');
        }

        // Procesar la notificación
        $order_id = $data['customer_ref'] ?? '';
        $order = wc_get_order($order_id);

        if (!$order) {
            status_header(404);
            exit('Order not found');
        }

        // Actualizar el estado de la orden según la notificación
        $this->process_webhook_notification($order, $data);

        status_header(200);
        exit('Webhook processed successfully');
    }

    private function verify_webhook_signature($payload) {
        // Implementar la verificación de firma del webhook
        // según la documentación de Banorte
        return true;
    }

    private function process_webhook_notification($order, $data) {
        // Implementar el procesamiento de la notificación
        // según la documentación de Banorte
    }
}