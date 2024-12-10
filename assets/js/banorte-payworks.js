jQuery(function($) {
    'use strict';

    // Función para validar número de tarjeta usando el algoritmo de Luhn
    function validateCardNumber(number) {
        var sum = 0;
        var isEven = false;
        
        // Remover espacios y guiones
        number = number.replace(/\D/g, '');
        
        for (var n = number.length - 1; n >= 0; n--) {
            var digit = parseInt(number[n], 10);
            
            if (isEven) {
                digit *= 2;
                if (digit > 9) {
                    digit -= 9;
                }
            }
            
            sum += digit;
            isEven = !isEven;
        }
        
        return (sum % 10) === 0;
    }

    // Función para obtener el tipo de tarjeta
    function getCardType(number) {
        // Remover espacios y guiones
        number = number.replace(/\D/g, '');
        
        var cardType = '';
        
        // Visa
        if (number.match(/^4/)) {
            cardType = 'VI';
        }
        // MasterCard
        else if (number.match(/^5[1-5]/)) {
            cardType = 'MC';
        }
        // American Express
        else if (number.match(/^3[47]/)) {
            cardType = 'AM';
        }
        
        return cardType;
    }

    // Inicializar el formulario cuando esté listo
    $(document.body).on('updated_checkout', function() {
        if ($('#banorte_card_number').length) {
            initializeForm();
        }
    });

    function initializeForm() {
        // Formatear número de tarjeta
        $('#banorte_card_number').on('input', function() {
            var value = $(this).val().replace(/\D/g, '');
            var formattedValue = value.replace(/(\d{4})(?=\d)/g, '$1 ');
            $(this).val(formattedValue);
            
            // Actualizar tipo de tarjeta
            var cardType = getCardType(value);
            $('#banorte_card_type').val(cardType);
        });

        // Formatear fecha de expiración
        $('#banorte_card_expiry').on('input', function() {
            var value = $(this).val().replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substr(0, 2) + (value.length > 2 ? ' / ' + value.substr(2, 2) : '');
            }
            $(this).val(value);
        });

        // Validar formulario antes de enviar
        $('form.checkout').on('checkout_place_order_banorte_payworks', function() {
            var $form = $(this);
            var cardNumber = $('#banorte_card_number').val().replace(/\s/g, '');
            var expiry = $('#banorte_card_expiry').val();
            var cvv = $('#banorte_card_cvc').val();
            var cardType = $('#banorte_card_type').val();
            
            // Validar número de tarjeta
            if (!validateCardNumber(cardNumber)) {
                alert('Por favor ingresa un número de tarjeta válido');
                return false;
            }
            
            // Validar fecha de expiración
            var expiryParts = expiry.split('/');
            if (expiryParts.length !== 2) {
                alert('Por favor ingresa una fecha de expiración válida');
                return false;
            }
            
            var month = parseInt(expiryParts[0], 10);
            var year = parseInt(expiryParts[1], 10);
            var now = new Date();
            var currentYear = parseInt(now.getFullYear().toString().substr(-2), 10);
            var currentMonth = now.getMonth() + 1;
            
            if (month < 1 || month > 12 || year < currentYear || (year === currentYear && month < currentMonth)) {
                alert('La tarjeta ha expirado');
                return false;
            }
            
            // Validar CVV
            if (!/^\d{3,4}$/.test(cvv)) {
                alert('Por favor ingresa un código de seguridad válido');
                return false;
            }
            
            // Validar tipo de tarjeta
            if (!cardType) {
                alert('Tipo de tarjeta no soportado');
                return false;
            }
            
            return true;
        });
    }
});