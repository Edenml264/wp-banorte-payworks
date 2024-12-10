jQuery(function($) {
    'use strict';

    var BanortePayworksForm = {
        init: function() {
            // Cache DOM elements
            this.$form = $('form.checkout, form#order_review');
            this.$cardNumber = $('#banorte_payworks-card-number');
            this.$cardExpiry = $('#banorte_payworks-card-expiry');
            this.$cardCvc = $('#banorte_payworks-card-cvc');

            // Bind events
            this.$cardNumber.on('input', this.formatCardNumber);
            this.$cardExpiry.on('input', this.formatCardExpiry);
            this.$cardCvc.on('input', this.formatCardCVC);
            this.$form.on('submit', this.onSubmit.bind(this));
        },

        formatCardNumber: function() {
            var cardNumber = $(this).val().replace(/\s+/g, '').replace(/[^0-9]/gi, '');
            var formattedCardNumber = '';
            
            // Format card number in groups of 4
            for(var i = 0; i < cardNumber.length; i++) {
                if(i > 0 && i % 4 === 0) {
                    formattedCardNumber += ' ';
                }
                formattedCardNumber += cardNumber[i];
            }
            
            $(this).val(formattedCardNumber.substring(0, 19));
        },

        formatCardExpiry: function() {
            var expiry = $(this).val().replace(/\s+/g, '').replace(/[^0-9]/gi, '');
            
            if(expiry.length >= 2) {
                expiry = expiry.substring(0, 2) + ' / ' + expiry.substring(2, 4);
            }
            
            $(this).val(expiry);
        },

        formatCardCVC: function() {
            var cvc = $(this).val().replace(/\s+/g, '').replace(/[^0-9]/gi, '');
            $(this).val(cvc.substring(0, 4));
        },

        validateCardNumber: function(number) {
            number = number.replace(/\s+/g, '');
            
            // Implementar algoritmo de Luhn
            var sum = 0;
            var isEven = false;
            
            // Loop through values starting from the rightmost side
            for (var i = number.length - 1; i >= 0; i--) {
                var digit = parseInt(number.charAt(i), 10);

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
        },

        validateCardExpiry: function(expiry) {
            expiry = expiry.replace(/\s+/g, '').replace('/', '');
            
            if(expiry.length !== 4) {
                return false;
            }

            var month = parseInt(expiry.substring(0, 2), 10);
            var year = parseInt(expiry.substring(2, 4), 10) + 2000;
            
            var currentDate = new Date();
            var currentYear = currentDate.getFullYear();
            var currentMonth = currentDate.getMonth() + 1;
            
            if(year < currentYear || (year === currentYear && month < currentMonth)) {
                return false;
            }
            
            return month >= 1 && month <= 12;
        },

        onSubmit: function(e) {
            if(this.$form.find('#payment_method_banorte_payworks').is(':checked')) {
                if(!this.validateForm()) {
                    e.preventDefault();
                    return false;
                }
            }
            return true;
        },

        validateForm: function() {
            var cardNumber = this.$cardNumber.val().replace(/\s+/g, '');
            var cardExpiry = this.$cardExpiry.val();
            var cardCvc = this.$cardCvc.val();
            
            // Validar número de tarjeta
            if(!this.validateCardNumber(cardNumber)) {
                this.showError('El número de tarjeta no es válido');
                return false;
            }
            
            // Validar fecha de expiración
            if(!this.validateCardExpiry(cardExpiry)) {
                this.showError('La fecha de expiración no es válida');
                return false;
            }
            
            // Validar CVC
            if(cardCvc.length < 3 || cardCvc.length > 4) {
                this.showError('El código de seguridad no es válido');
                return false;
            }
            
            return true;
        },

        showError: function(message) {
            // Remover errores existentes
            $('.woocommerce-error').remove();
            
            // Mostrar nuevo error
            var errorHtml = '<ul class="woocommerce-error"><li>' + message + '</li></ul>';
            this.$form.prepend(errorHtml);
            
            // Hacer scroll al error
            $('html, body').animate({
                scrollTop: this.$form.offset().top - 100
            }, 1000);
        }
    };

    // Inicializar cuando el documento esté listo
    $(document).ready(function() {
        BanortePayworksForm.init();
    });
});