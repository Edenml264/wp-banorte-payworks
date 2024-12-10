<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Banorte_Payworks_Logger {
    /**
     * @var WC_Logger
     */
    private $logger;

    /**
     * @var string
     */
    private $context;

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = wc_get_logger();
        $this->context = array('source' => 'banorte-payworks');
    }

    /**
     * Log información de depuración
     *
     * @param string $message
     */
    public function debug($message) {
        if ($this->is_logging_enabled()) {
            $this->logger->debug($this->format_message($message), $this->context);
        }
    }

    /**
     * Log información
     *
     * @param string $message
     */
    public function info($message) {
        if ($this->is_logging_enabled()) {
            $this->logger->info($this->format_message($message), $this->context);
        }
    }

    /**
     * Log errores
     *
     * @param string $message
     * @param array $context_data Datos adicionales para el log
     */
    public function error($message, $context_data = array()) {
        if ($this->is_logging_enabled()) {
            $formatted_message = $this->format_message($message);
            if (!empty($context_data)) {
                $formatted_message .= "\nDatos adicionales: " . print_r($context_data, true);
            }
            $this->logger->error($formatted_message, $this->context);
        }
    }

    /**
     * Log transacciones
     *
     * @param string $order_id
     * @param string $type
     * @param array $data
     */
    public function log_transaction($order_id, $type, $data) {
        if ($this->is_logging_enabled()) {
            $message = sprintf(
                "Transacción %s para orden #%s\n%s",
                $type,
                $order_id,
                print_r($data, true)
            );
            $this->info($message);
        }
    }

    /**
     * Log respuestas de la API
     *
     * @param string $endpoint
     * @param array $request
     * @param mixed $response
     * @param string $order_id
     */
    public function log_api_response($endpoint, $request, $response, $order_id = '') {
        if ($this->is_logging_enabled()) {
            $message = sprintf(
                "API Request a %s\nOrden: %s\nRequest: %s\nResponse: %s",
                $endpoint,
                $order_id,
                print_r($this->sanitize_data($request), true),
                print_r($response, true)
            );
            $this->debug($message);
        }
    }

    /**
     * Log errores de la API
     *
     * @param string $endpoint
     * @param string $error_message
     * @param array $request
     * @param string $order_id
     */
    public function log_api_error($endpoint, $error_message, $request, $order_id = '') {
        if ($this->is_logging_enabled()) {
            $message = sprintf(
                "Error en API %s\nOrden: %s\nError: %s\nRequest: %s",
                $endpoint,
                $order_id,
                $error_message,
                print_r($this->sanitize_data($request), true)
            );
            $this->error($message);
        }
    }

    /**
     * Verifica si el logging está habilitado
     *
     * @return bool
     */
    private function is_logging_enabled() {
        return 'yes' === get_option('wc_banorte_payworks_logging', 'yes');
    }

    /**
     * Formatea el mensaje de log
     *
     * @param string $message
     * @return string
     */
    private function format_message($message) {
        return sprintf('[Banorte Payworks] %s', $message);
    }

    /**
     * Sanitiza datos sensibles para el log
     *
     * @param array $data
     * @return array
     */
    private function sanitize_data($data) {
        $sensitive_fields = array(
            'card_number',
            'security_code',
            'password',
            'cvv',
            'card_exp'
        );

        $sanitized = $data;
        foreach ($sensitive_fields as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = str_repeat('*', strlen($sanitized[$field]));
            }
        }

        return $sanitized;
    }
}