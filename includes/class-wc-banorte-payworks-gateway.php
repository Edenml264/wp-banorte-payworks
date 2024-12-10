<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/class-wc-banorte-payworks-api.php';

class WC_Banorte_Payworks_Gateway extends WC_Payment_Gateway {
    private $api;

    /** @var string */
    public $id;

    /** @var string */
    public $icon;

    /** @var bool */
    public $has_fields;

    /** @var string */
    public $method_title;

    /** @var string */
    public $method_description;

    /** @var array */
    public $supports;

    /** @var string */
    public $title;

    /** @var string */
    public $description;

    /** @var string */
    public $enabled;

    /** @var bool */
    public $testmode;

    /** @var bool */
    public $logging;

    /** @var string */
    public $merchant_id;

    /** @var string */
    public $terminal_id;

    /** @var string */
    public $user_id;

    /** @var string */
    public $password;

    /** @var string */
    public $nombre_comercio;

    /** @var string */
    public $ciudad_comercio;

    public function __construct() {
        $this->id = 'banorte_payworks';
        $this->icon = apply_filters('woocommerce_banorte_icon', '');
        $this->has_fields = true;
        $this->method_title = __('Banorte Payworks', 'wc-banorte-payworks');
        $this->method_description = __('Procesa pagos con tarjeta de crédito/débito a través de Banorte Payworks.', 'wc-banorte-payworks');

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
        $this->logging = 'yes' === $this->get_option('logging');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->terminal_id = $this->get_option('terminal_id');
        $this->user_id = $this->get_option('user_id');
        $this->password = $this->get_option('password');
        $this->nombre_comercio = $this->get_option('nombre_comercio');
        $this->ciudad_comercio = $this->get_option('ciudad_comercio');

        // Inicializar la API
        $this->api = new WC_Banorte_Payworks_API(
            $this->merchant_id,
            $this->terminal_id,
            $this->user_id,
            $this->password,
            $this->testmode,
            $this->nombre_comercio,
            $this->ciudad_comercio
        );

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('woocommerce_api_banorte_secure', array($this, 'handle_3d_secure_response'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_api_wc_banorte_payworks_gateway', array($this, 'webhook_handler'));
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
            'nombre_comercio' => array(
                'title'       => __('Nombre del comercio', 'wc-banorte-payworks'),
                'type'        => 'text',
                'description' => __('Nombre del comercio', 'wc-banorte-payworks'),
            ),
            'ciudad_comercio' => array(
                'title'       => __('Ciudad del comercio', 'wc-banorte-payworks'),
                'type'        => 'text',
                'description' => __('Ciudad del comercio', 'wc-banorte-payworks'),
            ),
        );
    }

    public function payment_scripts() {
        if (!is_checkout() || !$this->enabled) {
            return;
        }

        wp_enqueue_script(
            'wc-banorte-payworks',
            WC_BANORTE_PLUGIN_URL . 'assets/js/banorte-payworks.js',
            array('jquery'),
            WC_BANORTE_VERSION,
            true
        );

        wp_localize_script('wc-banorte-payworks', 'wc_banorte_params', array(
            'is_test_mode' => $this->testmode
        ));
    }

    public function payment_fields() {
        ?>
        <div id="banorte-payworks-form">
            <p class="form-row form-row-wide">
                <label for="cardNumber_banorte"><?php esc_html_e('Número de Tarjeta', 'wc-banorte-payworks'); ?> <span class="required">*</span></label>
                <input id="cardNumber_banorte" name="cardNumber_banorte" class="input-text wc-credit-card-form-card-number" type="text" maxlength="20" autocomplete="off" placeholder="•••• •••• •••• ••••" required />
            </p>

            <p class="form-row form-row-first">
                <label for="cardExpirationMonth_banorte"><?php esc_html_e('Fecha de Expiración (MM/YY)', 'wc-banorte-payworks'); ?> <span class="required">*</span></label>
                <input id="cardExpirationMonth_banorte" name="cardExpirationMonth_banorte" class="input-text wc-credit-card-form-card-expiry" type="text" autocomplete="off" placeholder="MM / YY" required />
            </p>

            <p class="form-row form-row-last">
                <label for="securityCode_banorte"><?php esc_html_e('Código de Seguridad', 'wc-banorte-payworks'); ?> <span class="required">*</span></label>
                <input id="securityCode_banorte" name="securityCode_banorte" class="input-text wc-credit-card-form-card-cvc" type="text" autocomplete="off" placeholder="CVC" maxlength="4" required />
            </p>

            <input type="hidden" name="tipo_tarjeta_banorte" id="tipo_tarjeta_banorte" value="" />
            <div class="clear"></div>
        </div>
        <?php
    }

    public function validate_fields() {
        if (empty($_POST['cardNumber_banorte']) ||
            empty($_POST['cardExpirationMonth_banorte']) ||
            empty($_POST['securityCode_banorte'])) {
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
                'number' => str_replace(' ', '', $_POST['cardNumber_banorte']),
                'expiry' => str_replace(' / ', '', $_POST['cardExpirationMonth_banorte']),
                'cvv' => $_POST['securityCode_banorte'],
                'tipo_tarjeta' => $_POST['tipo_tarjeta_banorte']
            );

            // Preparar los datos para 3D Secure
            $params_3d = array(
                'NUMERO_TARJETA' => $card_data['number'],
                'FECHA_EXP' => substr($card_data['expiry'], 0, 2) . '/' . substr($card_data['expiry'], -2),
                'MONTO' => $order->get_total(),
                'MARCA_TARJETA' => $card_data['tipo_tarjeta'],
                'ID_AFILIACION' => $this->merchant_id,
                'NOMBRE_COMERCIO' => $this->nombre_comercio,
                'CIUDAD_COMERCIO' => $this->ciudad_comercio,
                'URL_RESPUESTA' => add_query_arg('wc-api', 'banorte_secure_notificacion', home_url('/')),
                'CERTIFICACION_3D' => "03",
                'REFERENCIA3D' => $order_id,
                'CIUDAD' => $this->clean_characters($order->get_billing_city()),
                'PAIS' => $order->get_billing_country(),
                'CORREO' => $order->get_billing_email(),
                'NOMBRE' => $this->clean_characters($order->get_billing_first_name()),
                'APELLIDO' => $this->clean_characters($order->get_billing_last_name()),
                'CODIGO_POSTAL' => $order->get_billing_postcode(),
                'ESTADO' => $order->get_billing_state(),
                'CALLE' => $this->clean_characters(mb_strimwidth($order->get_billing_address_1(), 0, 49, "")),
                'VERSION_3D' => "2",
                'NUMERO_CELULAR' => str_replace(' ', '', $order->get_billing_phone()),
                'TIPO_TARJETA' => $card_data['tipo_tarjeta']
            );

            // Guardar los datos de la tarjeta de forma segura
            $this->save_card_data($order_id, $card_data);

            // Guardar los parámetros 3D Secure en la sesión
            WC()->session->set('parametros_3d_secure', $params_3d);

            // Redirigir a la página de autenticación 3D Secure
            return array(
                'result' => 'success',
                'redirect' => add_query_arg('wc-api', 'banorte_secure', home_url('/'))
            );

        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            return array(
                'result' => 'fail',
                'redirect' => ''
            );
        }
    }

    public function handle_3d_secure_response() {
        if (!isset($_POST['REFERENCIA3D'])) {
            wp_die('Solicitud no válida', 'Error', array('response' => 400));
        }

        $order_id = $_POST['REFERENCIA3D'];
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_die('Orden no encontrada', 'Error', array('response' => 404));
        }

        try {
            // Recuperar los datos de la tarjeta
            $card_data = $this->get_card_data($order_id);

            if (!$card_data) {
                throw new Exception('No se encontraron los datos de la tarjeta');
            }

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
                        __('Pago procesado exitosamente (ID de Transacción: %s)', 'wc-banorte-payworks'),
                        $payment_result['transaction_id']
                    )
                );

                // Limpiar datos sensibles
                $this->clear_card_data($order_id);

                // Redirigir al cliente
                wp_redirect($this->get_return_url($order));
                exit;
            } else {
                throw new Exception($payment_result['error_message']);
            }
        } catch (Exception $e) {
            $order->add_order_note('Error en el pago: ' . $e->getMessage());
            wp_redirect(wc_get_checkout_url());
            exit;
        }
    }

    private function save_card_data($order_id, $card_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'banorte_transacciones';
        
        $data = array(
            'id_order' => $order_id,
            'transaccion' => $this->encrypt_data($card_data['number']),
            'transaccion_caduca' => $this->encrypt_data($card_data['expiry']),
            'transaccion_digitos' => $this->encrypt_data($card_data['cvv'])
        );

        $wpdb->insert($table_name, $data);
    }

    private function get_card_data($order_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'banorte_transacciones';
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id_order = %d",
            $order_id
        ));

        if (!$row) {
            return false;
        }

        return array(
            'number' => $this->decrypt_data($row->transaccion),
            'expiry' => $this->decrypt_data($row->transaccion_caduca),
            'cvv' => $this->decrypt_data($row->transaccion_digitos)
        );
    }

    private function clear_card_data($order_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'banorte_transacciones';
        
        $wpdb->delete($table_name, array('id_order' => $order_id));
    }

    private function encrypt_data($data) {
        return openssl_encrypt(
            $data,
            'AES-256-CBC',
            wp_salt('auth'),
            0,
            substr(wp_salt('secure_auth'), 0, 16)
        );
    }

    private function decrypt_data($data) {
        return openssl_decrypt(
            $data,
            'AES-256-CBC',
            wp_salt('auth'),
            0,
            substr(wp_salt('secure_auth'), 0, 16)
        );
    }

    private function clean_characters($string) {
        $unwanted_array = array(
            'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
            'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
            'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y'
        );
        return strtr($string, $unwanted_array);
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