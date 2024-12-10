<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Banorte_Payworks_API {
    private $merchant_id;
    private $terminal_id;
    private $user_id;
    private $password;
    private $test_mode;
    private $api_endpoint;

    public function __construct($merchant_id, $terminal_id, $user_id, $password, $test_mode = false) {
        $this->merchant_id = $merchant_id;
        $this->terminal_id = $terminal_id;
        $this->user_id = $user_id;
        $this->password = $password;
        $this->test_mode = $test_mode;
        $this->api_endpoint = $test_mode ? 'https://wppsandbox.mit.com.mx' : 'https://wpp.banorte.com';
    }

    /**
     * Procesa un pago con tarjeta
     * 
     * @param array $card_data Datos de la tarjeta
     * @param float $amount Monto a cobrar
     * @param string $order_id ID de la orden
     * @param string $currency Moneda (MXN por defecto)
     * @return array Respuesta del proceso
     */
    public function process_payment($card_data, $amount, $order_id, $currency = 'MXN') {
        $endpoint = $this->api_endpoint . '/PaymentWS/Payment';
        
        // Formatear el monto (debe tener 2 decimales)
        $amount = number_format($amount, 2, '.', '');
        
        // Preparar los datos para la transacción
        $transaction_data = array(
            'merchant_id' => $this->merchant_id,
            'terminal_id' => $this->terminal_id,
            'user' => $this->user_id,
            'password' => $this->password,
            'cmd_trans' => 'AUTH',  // AUTH para autorización
            'amount' => $amount,
            'mode' => 'RND',  // Modo de operación
            'customer_ref' => $order_id,
            'currency' => $currency,
            'card_number' => $card_data['number'],
            'card_exp' => $card_data['expiry'],
            'security_code' => $card_data['cvv'],
            'entry_mode' => 'MANUAL'
        );

        // Realizar la petición a la API
        $response = $this->make_request($endpoint, $transaction_data);
        
        return $this->process_response($response);
    }

    /**
     * Realiza un reembolso
     * 
     * @param string $transaction_id ID de la transacción original
     * @param float $amount Monto a reembolsar
     * @param string $order_id ID de la orden
     * @return array Respuesta del proceso
     */
    public function process_refund($transaction_id, $amount, $order_id) {
        $endpoint = $this->api_endpoint . '/PaymentWS/Payment';
        
        $amount = number_format($amount, 2, '.', '');
        
        $refund_data = array(
            'merchant_id' => $this->merchant_id,
            'terminal_id' => $this->terminal_id,
            'user' => $this->user_id,
            'password' => $this->password,
            'cmd_trans' => 'REVERSAL',  // REVERSAL para reembolso
            'amount' => $amount,
            'mode' => 'RND',
            'customer_ref' => $order_id,
            'reference' => $transaction_id
        );

        $response = $this->make_request($endpoint, $refund_data);
        
        return $this->process_response($response);
    }

    /**
     * Realiza la petición HTTP a la API de Banorte
     */
    private function make_request($endpoint, $data) {
        $args = array(
            'body' => json_encode($data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            'timeout' => 30,
            'sslverify' => true
        );

        $response = wp_remote_post($endpoint, $args);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    /**
     * Procesa la respuesta de la API
     */
    private function process_response($response) {
        if (empty($response)) {
            return array(
                'success' => false,
                'error_message' => __('No se recibió respuesta del servidor de Banorte', 'wc-banorte-payworks')
            );
        }

        // Códigos de respuesta exitosos de Banorte
        $success_codes = array('A01', 'A02', 'A03', 'A04', 'A05');

        if (isset($response['response_code']) && in_array($response['response_code'], $success_codes)) {
            return array(
                'success' => true,
                'transaction_id' => $response['folio'],
                'auth_code' => $response['auth_code'],
                'message' => $response['response_msg']
            );
        }

        return array(
            'success' => false,
            'error_code' => $response['response_code'],
            'error_message' => $response['response_msg']
        );
    }
}