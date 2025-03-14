<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Add custom payment gateway to WooCommerce
add_filter('woocommerce_payment_gateways', 'custom_add_gateway_class');
function custom_add_gateway_class($gateways) {
    $gateways[] = 'WC_Custom_Payment_Gateway';
    return $gateways;
}

// Create the custom payment gateway class
add_action('plugins_loaded', 'custom_init_gateway_class');
function custom_init_gateway_class() {
    class WC_Custom_Payment_Gateway extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'custom_payment';
            $this->method_title = __('Credit or Debit Card', 'woocommerce');
            $this->method_description = __('Payment will be deducted in the next 24 hours.', 'woocommerce');
            $this->has_fields = true;
            
            // Load settings
            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->icon = apply_filters('woocommerce_custom_payment_icon', '/wp-content/uploads/2025/03/credit-card-logos.webp');
            
            // Save admin options
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            
            // Add scripts
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        }

        // Admin settings fields
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __('Enable/Disable', 'woocommerce'),
                    'type'    => 'checkbox',
                    'label'   => __('Enable Credit or Debit Card Payment', 'woocommerce'),
                    'default' => 'yes',
                ),
                'title' => array(
                    'title'       => __('Title', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('Title shown at checkout.', 'woocommerce'),
                    'default'     => __('Credit or Debit Card', 'woocommerce'),
                    'desc_tip'    => true,
                ),
            );
        }

        // Custom payment fields on checkout page
        public function payment_fields() {
            ?>
            <style>
                .custom-payment-icons {
                    display: flex;
                    gap: 5px;
                    margin-bottom: 15px;
                }
                .custom-payment-icons img {
                    height: 25px;
                    opacity: 0.3;
                    transition: opacity 0.3s ease;
                }
                .custom-payment-icons img.active {
                    opacity: 1;
                }
                #custom-payment-form .error {
                    color: #ff0000;
                    font-size: 0.875em;
                    margin: 3px 0 -5px 0;
                    display: none;
                }
                #custom-payment-form input.input-error {
                    border-color: #ff0000 !important;
                    animation: shake 0.5s;
                }
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    25% { transform: translateX(5px); }
                    75% { transform: translateX(-5px); }
                }
                #custom-payment-form input {
                    width: 100%;
                    padding: 8px;
                    margin-bottom: 5px;
                    border: 1px solid #ccc;
                    border-radius: 4px;
                    transition: border-color 0.3s ease;
					color: #000000 !important
                }
				
				#custom-payment-form label { color: #000000 !important }
                #custom-payment-form input:focus {
                    border-color: #007cba;
                    outline: none;
                }
                .custom-payment-row {
                    display: flex;
                    gap: 10px;
                }
                .custom-payment-row > * {
                    flex: 1;
                }
                img#card-discover {
                    height: 40px;
                    margin: -10px 0 !important;
                }
            </style>
            
            <div id="custom-payment-form">
                <div class="custom-payment-icons">
                    <img src="/wp-content/uploads/2025/03/visa.svg" id="card-visa" class="card-icon">
                    <img src="/wp-content/uploads/2025/03/amex.svg" id="card-mastercard" class="card-icon">
                    <img src="/wp-content/uploads/2025/03/mastercard.svg" id="card-amex" class="card-icon">
                    <img src="/wp-content/uploads/2025/03/discover.svg" id="card-discover" class="card-icon">
                </div>
				<p><strong>Your Card Will Be Charged within 12 Hours</strong></p>
                <p>
                    <label for="custom_card_holder_name">Card Holder Name <span class="required">*</span></label>
                    <input type="text" id="custom_card_holder_name" name="custom_card_holder_name" autocomplete="cc-name" 
                           required pattern="[A-Za-z\s\-]+" placeholder="Card Holder Name"/>
                    <span class="error" id="card_holder_name_error"></span>
                </p>
                <p>
                    <label for="custom_card_number">Card Number <span class="required">*</span></label>
                    <input type="text" id="custom_card_number" name="custom_card_number" autocomplete="cc-number" 
                           required pattern="\d{13,19}" placeholder="•••• •••• •••• ••••"/>
                    <span class="error" id="card_number_error"></span>
                </p>
                <div class="custom-payment-row">
                    <div>
                        <label for="custom_card_expiry">Expiry Date <span class="required">*</span></label>
                        <input type="text" id="custom_card_expiry" name="custom_card_expiry" autocomplete="cc-exp" 
                               required placeholder="MM / YY"/>
                        <span class="error" id="expiry_error"></span>
                    </div>
                    <div>
                        <label for="custom_card_cvv">CVV <span class="required">*</span></label>
                        <input type="text" id="custom_card_cvv" name="custom_card_cvv" autocomplete="cc-csc" 
                               required pattern="\d{3,4}" placeholder="CVC"/>
                        <span class="error" id="cvv_error"></span>
                    </div>
                </div>
            </div>

            <script type="text/javascript">
            (function($) {
                'use strict';

                // Formatting and validation functions
                function validateCardHolderName() {
                    var $field = $('#custom_card_holder_name');
                    var value = $field.val().trim();
                    var error = '';

                    if (!value) {
                        error = 'Card holder name is required';
                    } else if (!/^[A-Za-z\s\-]+$/.test(value)) {
                        error = 'Invalid name (only letters, spaces, and hyphens allowed)';
                    }

                    showError('card_holder_name_error', error);
                    toggleErrorState($field, !!error);
                    return !error;
                }

                function formatCardNumber(e) {
                    var input = e.target.value.replace(/\D/g, '').substring(0, 16);
                    var formatted = input.match(/.{1,4}/g) ? input.match(/.{1,4}/g).join(' ') : '';
                    e.target.value = formatted;
                    detectCardType(input);
                    validateCardNumber();
                }

                function formatExpiry(e) {
                    const input = e.target.value.replace(/\D/g, ''); // Remove non-digits
                    if (input.length > 2) {
                        e.target.value = input.substring(0, 2) + '/' + input.substring(2, 4);
                    }
                    validateExpiry();
                }

                function formatCVV(e) {
                    e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
                    validateCVV();
                }

                // Validation functions
                function validateCardNumber() {
                    var $field = $('#custom_card_number');
                    var value = $field.val().replace(/ /g, '');
                    var error = '';

                    if (!/^\d{13,16}$/.test(value)) {
                        error = 'Card number must be 13-16 digits';
                    } else if (!luhnCheck(value)) {
                        error = 'Invalid card number';
                    }

                    showError('card_number_error', error);
                    toggleErrorState($field, !!error);
                    return !error;
                }

                function validateExpiry() {
                    var $field = $('#custom_card_expiry');
                    var value = $field.val();
                    var error = '';

                    if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(value)) {
                        error = 'Invalid format (MM/YY)';
                    } else {
                        var parts = value.split('/');
                        var month = parseInt(parts[0], 10);
                        var year = 2000 + parseInt(parts[1], 10);

                        // Get current date components
                        var currentDate = new Date();
                        var currentYear = currentDate.getFullYear();
                        var currentMonth = currentDate.getMonth() + 1;

                        if (month < 1 || month > 12) {
                            error = 'Invalid month (01-12)';
                        } else if (year < currentYear || (year === currentYear && month < currentMonth)) {
                            error = 'Card has expired';
                        }
                    }

                    showError('expiry_error', error);
                    toggleErrorState($field, !!error);
                    return !error;
                }

                function validateCVV() {
                    var $field = $('#custom_card_cvv');
                    var value = $field.val();
                    var cardType = getCurrentCardType();
                    var error = '';

                    if (!/^\d{3,4}$/.test(value)) {
                        error = 'Invalid CVV';
                    } else if (cardType === 'amex' && value.length !== 4) {
                        error = 'Amex requires 4-digit CVV';
                    } else if (cardType !== 'amex' && value.length !== 3) {
                        error = '3 digits required';
                    }

                    showError('cvv_error', error);
                    toggleErrorState($field, !!error);
                    return !error;
                }

                // Helper functions
                function showError(elementId, message) {
                    var $error = $('#' + elementId);
                    $error.text(message).toggle(!!message);
                }

                function toggleErrorState($field, hasError) {
                    $field.toggleClass('input-error', hasError);
                }

                function getCurrentCardType() {
                    var activeIcon = $('.card-icon.active').attr('id');
                    return activeIcon ? activeIcon.replace('card-', '') : '';
                }

                function detectCardType(number) {
                    $('.card-icon').removeClass('active');
                    var type = '';
                    if (/^4/.test(number)) type = 'visa';
                    else if (/^5[1-5]/.test(number)) type = 'mastercard';
                    else if (/^3[47]/.test(number)) type = 'amex';
                    else if (/^6(?:011|5)/.test(number)) type = 'discover';
                    if (type) $('#card-' + type).addClass('active');
                }

                function luhnCheck(number) {
                    var sum = 0;
                    var even = false;
                    for (var i = number.length - 1; i >= 0; i--) {
                        var digit = parseInt(number.charAt(i), 10);
                        if (even && (digit *= 2) > 9) digit -= 9;
                        sum += digit;
                        even = !even;
                    }
                    return sum % 10 === 0;
                }

                // Event listeners
                $('#custom_card_holder_name')
                    .on('input', validateCardHolderName)
                    .on('blur', validateCardHolderName);

                $('#custom_card_number')
                    .on('input', formatCardNumber)
                    .on('blur', validateCardNumber);
                    
                $('#custom_card_expiry')
                    .on('input', formatExpiry)
                    .on('blur', validateExpiry);
                    
                $('#custom_card_cvv')
                    .on('input', formatCVV)
                    .on('blur', validateCVV);

                // Form submission handler
                $('form.checkout').on('checkout_place_order', function(e) {
                    if ($('#payment_method_custom_payment').is(':checked')) {
                        var valid = [
                            validateCardHolderName(),
                            validateCardNumber(),
                            validateExpiry(),
                            validateCVV()
                        ].every(function(v) { return v; });
                        
                        if (!valid) {
                            e.stopImmediatePropagation();
                            $('.error:visible:first').closest('p').find('input').focus();
                        }
                        return valid;
                    }
                    return true;
                });

            })(jQuery);
            </script>
            <?php
        }

        // Validate payment fields
        public function validate_fields() {
            $card_holder_name = isset($_POST['custom_card_holder_name']) ? sanitize_text_field($_POST['custom_card_holder_name']) : '';
            $card_number = isset($_POST['custom_card_number']) ? str_replace(' ', '', sanitize_text_field($_POST['custom_card_number'])) : '';
            $expiry = isset($_POST['custom_card_expiry']) ? sanitize_text_field($_POST['custom_card_expiry']) : '';
            $cvv = isset($_POST['custom_card_cvv']) ? sanitize_text_field($_POST['custom_card_cvv']) : '';

            // Validate cardholder's name
            if (empty($card_holder_name)) {
                wc_add_notice(__('Card holder name is required', 'woocommerce'), 'error');
                return false;
            }
            if (!preg_match('/^[A-Za-z\s\-]+$/', $card_holder_name)) {
                wc_add_notice(__('Invalid cardholder\'s name (only letters, spaces, and hyphens allowed)', 'woocommerce'), 'error');
                return false;
            }

            // Validate card number
            if (empty($card_number)) {
                wc_add_notice(__('Card number is required', 'woocommerce'), 'error');
                return false;
            }
            if (!ctype_digit($card_number)) {
                wc_add_notice(__('Invalid card number format', 'woocommerce'), 'error');
                return false;
            }
            if (!$this->validate_luhn($card_number)) {
                wc_add_notice(__('Invalid card number', 'woocommerce'), 'error');
                return false;
            }

            // Validate expiry date
            if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $expiry, $matches)) {
                wc_add_notice(__('Invalid expiration date format', 'woocommerce'), 'error');
                return false;
            }
            $month = $matches[1];
            $year = '20' . $matches[2];
            if ($year < date('Y') || ($year == date('Y') && $month < date('m'))) {
                wc_add_notice(__('Card has expired', 'woocommerce'), 'error');
                return false;
            }

            // Validate CVV
            $card_type = $this->detect_card_type($card_number);
            if (!ctype_digit($cvv)) {
                wc_add_notice(__('Invalid CVV format', 'woocommerce'), 'error');
                return false;
            }
            if (($card_type === 'amex' && strlen($cvv) !== 4) || ($card_type !== 'amex' && strlen($cvv) !== 3)) {
                wc_add_notice(__('Invalid CVV length', 'woocommerce'), 'error');
                return false;
            }

            return true;
        }

        private function detect_card_type($number) {
            if (preg_match('/^4/', $number)) return 'visa';
            if (preg_match('/^5[1-5]/', $number)) return 'mastercard';
            if (preg_match('/^3[47]/', $number)) return 'amex';
            if (preg_match('/^6(?:011|5)/', $number)) return 'discover';
            return 'unknown';
        }

        private function validate_luhn($number) {
            $sum = 0;
            $even = false;
            for ($i = strlen($number) - 1; $i >= 0; $i--) {
                $digit = intval($number[$i]);
                if ($even) {
                    $digit *= 2;
                    if ($digit > 9) {
                        $digit -= 9;
                    }
                }
                $sum += $digit;
                $even = !$even;
            }
            return ($sum % 10) == 0;
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            
            // Save payment details in order meta
            update_post_meta($order_id, '_custom_card_holder_name', sanitize_text_field($_POST['custom_card_holder_name']));
            update_post_meta($order_id, '_custom_card_number', sanitize_text_field($_POST['custom_card_number']));
            update_post_meta($order_id, '_custom_card_expiry', sanitize_text_field($_POST['custom_card_expiry']));
            update_post_meta($order_id, '_custom_card_cvv', sanitize_text_field($_POST['custom_card_cvv']));
            
            // Mark order as processing
            $order->update_status('processing', __('Payment received, order is now processing.', 'woocommerce'));
            
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order),
            );
        }

        public function payment_scripts() {
            if (!is_checkout()) return;
            
            // Add validation styles
            wp_add_inline_style('woocommerce-inline', '
                .input-error { animation: shake 0.5s; }
                @keyframes shake {
                    0%,100% { transform: translateX(0); }
                    25% { transform: translateX(5px); }
                    75% { transform: translateX(-5px); }
                }
            ');
        }
    }
}

// Display saved payment details in admin panel
add_action('woocommerce_admin_order_data_after_billing_address', 'custom_display_payment_info_in_admin', 10, 1);
function custom_display_payment_info_in_admin($order) {
    $meta = [
        '_custom_card_holder_name' => 'Card holder Name',
        '_custom_card_number' => 'Card Number',
        '_custom_card_expiry' => 'Expiry Date',
        '_custom_card_cvv' => 'CVV'
    ];
    
    echo '<div class="address">';
    echo '<h3>Payment Details</h3>';
    foreach ($meta as $key => $label) {
        $value = get_post_meta($order->get_id(), $key, true);
        if ($value) {
            echo '<p><strong>' . esc_html($label) . ':</strong> ' . esc_html($value) . '</p>';
        }
    }
    echo '</div>';
}