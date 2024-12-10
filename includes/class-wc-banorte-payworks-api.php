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
    private $logger;
    private $nombre_comercio;
    private $ciudad_comercio;

    public function __construct($merchant_id, $terminal_id, $user_id, $password, $test_mode = false, $nombre_comercio = '', $ciudad_comercio = '') {
        $this->merchant_id = $merchant_id;
        $this->terminal_id = $terminal_id;
        $this->user_id = $user_id;
        $this->password = $password;
        $this->test_mode = $test_mode;
        $this->api_endpoint = $test_mode ? 'https://via.banorte.com/payw2' : 'https://via.banorte.com/payw2';
        $this->logger = new WC_Banorte_Payworks_Logger();
        $this->nombre_comercio = $nombre_comercio;
        $this->ciudad_comercio = $ciudad_comercio;
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
        $endpoint = $this->api_endpoint;
        
        // Formatear el monto (debe tener 2 decimales)
        $amount = number_format($amount, 2, '.', '');
        
        // Obtener la orden
        $order = wc_get_order($order_id);
        
        // Formatear la fecha de expiración
        $expiry = str_replace([' ', '/', '-'], '', $card_data['expiry']);
        $expiry_month = str_pad(substr($expiry, 0, 2), 2, "0", STR_PAD_LEFT);
        $expiry_year = substr($expiry, -2);
        
        // Obtener el tipo de tarjeta
        $card_type = $this->get_card_type($card_data['number']);
        
        // Preparar los datos para la transacción
        $transaction_data = array(
            'MERCHANT_ID' => $this->merchant_id,
            'TERMINAL_ID' => $this->terminal_id,
            'USER' => $this->user_id,
            'PWD' => $this->password,
            'CMD_TRANS' => 'AUTH',
            'AMOUNT' => $amount,
            'MODO_ENTRADA' => 'MANUAL',
            'NUMERO_CONTROL' => $order_id,
            'NUMERO_TARJETA' => str_replace(' ', '', $card_data['number']),
            'FECHA_EXP' => $expiry_month . '/' . $expiry_year,
            'CVV2' => $card_data['cvv'],
            'TIPO_TARJETA' => $card_type,
            'MODO' => $this->test_mode ? 'PRD' : 'PRD',
            'MONEDA' => 'MXN',
            'IDIOMA_RESPUESTA' => 'ES',
            'NOMBRE_COMERCIO' => $this->nombre_comercio,
            'CIUDAD_COMERCIO' => $this->ciudad_comercio,
            'CIUDAD' => $this->clean_characters($order->get_billing_city()),
            'PAIS' => $order->get_billing_country(),
            'CORREO' => $order->get_billing_email(),
            'NOMBRE' => $this->clean_characters($order->get_billing_first_name()),
            'APELLIDO' => $this->clean_characters($order->get_billing_last_name()),
            'CODIGO_POSTAL' => $order->get_billing_postcode(),
            'ESTADO' => $order->get_billing_state(),
            'CALLE' => $this->clean_characters(mb_strimwidth($order->get_billing_address_1(), 0, 49, "")),
            'NUMERO_CELULAR' => str_replace(' ', '', $order->get_billing_phone())
        );

        $this->logger->info(sprintf('Iniciando proceso de pago para orden #%s', $order_id));
        
        try {
            // Realizar la petición a la API
            $response = $this->make_request($endpoint, $transaction_data, $order_id);
            $this->logger->log_transaction($order_id, 'payment', $response);
            
            // Verificar si la transacción fue exitosa
            if (isset($response['RESULTADO_PAYW']) && $response['RESULTADO_PAYW'] === 'A') {
                return array(
                    'success' => true,
                    'transaction_id' => $response['REFERENCIA'],
                    'auth_code' => $response['CODIGO_AUT']
                );
            } else {
                $error_message = isset($response['TEXTO']) ? $response['TEXTO'] : 'Error desconocido';
                throw new Exception($error_message);
            }
        } catch (Exception $e) {
            $this->logger->error(sprintf('Error en proceso de pago para orden #%s: %s', $order_id, $e->getMessage()));
            throw $e;
        }
    }

    /**
     * Limpia caracteres especiales
     */
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

    /**
     * Realiza la petición HTTP a la API de Banorte
     */
    private function make_request($endpoint, $data, $order_id = '') {
        $args = array(
            'method' => 'POST',
            'timeout' => 90,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'text/plain',
                'Connection' => 'close'
            ),
            'body' => http_build_query($data),
            'cookies' => array(),
            'sslverify' => true
        );

        // Log de la petición (enmascarando datos sensibles)
        $log_data = $data;
        if (isset($log_data['NUMERO_TARJETA'])) {
            $log_data['NUMERO_TARJETA'] = 'XXXX' . substr($log_data['NUMERO_TARJETA'], -4);
        }
        if (isset($log_data['CVV2'])) {
            $log_data['CVV2'] = 'XXX';
        }
        if (isset($log_data['PWD'])) {
            $log_data['PWD'] = 'XXXXX';
        }
        
        $this->logger->info(sprintf('Request a %s: %s', $endpoint, json_encode($log_data)));

        $response = wp_remote_post($endpoint, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->logger->error(sprintf('Error en la petición: %s', $error_message));
            throw new Exception($error_message);
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $this->logger->info(sprintf('HTTP Response Code: %s', $http_code));

        $body = wp_remote_retrieve_body($response);
        $this->logger->info(sprintf('Respuesta raw: %s', $body));

        if (empty($body)) {
            $headers = wp_remote_retrieve_headers($response);
            $this->logger->error(sprintf('Headers de respuesta: %s', json_encode($headers)));
            
            if ($http_code === 200) {
                $raw_response = wp_remote_retrieve_response($response);
                $this->logger->info(sprintf('Respuesta completa: %s', print_r($raw_response, true)));
            }
            
            throw new Exception('La respuesta del servidor está vacía. Código HTTP: ' . $http_code);
        }

        // Primero intentar separar por pipes
        $parts = explode('|', $body);
        if (count($parts) >= 4) {
            return array(
                'RESULTADO_PAYW' => trim($parts[0]),
                'REFERENCIA' => trim($parts[1]),
                'CODIGO_AUT' => trim($parts[2]),
                'TEXTO' => trim($parts[3])
            );
        }

        // Si no es formato pipe, intentar como query string
        parse_str($body, $decoded_response);
        if (!empty($decoded_response)) {
            return $decoded_response;
        }

        $this->logger->error(sprintf('Error decodificando respuesta: %s', $body));
        throw new Exception('Error decodificando la respuesta del servidor');
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

    /**
     * Procesa la autenticación 3D Secure
     */
    public function process_3d_secure($params_3d) {
        $endpoint = 'https://via.banorte.com/3d';

        try {
            $args = array(
                'method' => 'POST',
                'timeout' => 90,
                'redirection' => 5,
                'httpversion' => '1.1',
                'blocking' => true,
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept' => 'text/html',
                ),
                'body' => $params_3d,
                'cookies' => array()
            );

            $response = wp_remote_post($endpoint, $args);

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            $body = wp_remote_retrieve_body($response);
            
            if (empty($body)) {
                throw new Exception('La respuesta del servidor está vacía');
            }

            return $body;

        } catch (Exception $e) {
            $this->logger->error('Error en 3D Secure: ' . $e->getMessage());
            throw $e;
        }
    }
}