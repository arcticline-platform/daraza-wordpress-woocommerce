(function() {
    'use strict';
    
    // Retrieve settings from PHP.
    // Make sure your PHP integration passes an object using the key "daraza_payments_data".
    var settings = window.wc && window.wc.wcSettings ? window.wc.wcSettings.getSetting('daraza_payments_data', {}) : {};

    // Decode the title; fallback to a default if not provided.
    var title = window.wp && window.wp.htmlEntities ? window.wp.htmlEntities.decodeEntities(settings.title) : 'Pay with Daraza';
    if (!title) {
        title = window.wp && window.wp.i18n ? window.wp.i18n.__('Pay with Daraza', 'daraza-payments') : 'Pay with Daraza';
    }

    // Decode the description and instructions.
    var description = window.wp && window.wp.htmlEntities ? window.wp.htmlEntities.decodeEntities(settings.description || 'Securely Pay with Daraza') : 'Securely Pay with Daraza';
    var instructions = window.wp && window.wp.htmlEntities ? window.wp.htmlEntities.decodeEntities(settings.instructions || 'Enter your mobile money number to complete payment. You will receive a payment request on your phone to approve the payment. Please make sure you have sufficient balance on your mobile money account. Standard network charges may apply. ') : 'Enter your mobile money number to complete payment. You will receive a payment request on your phone to approve the payment. Please make sure you have sufficient balance on your mobile money account. Standard network charges may apply. ';

    // Component: PaymentForm
    // Renders the phone input field with validation and registers a payment-processing callback.
    function PaymentForm(props) {
        var useState = window.wp && window.wp.element ? window.wp.element.useState : function(initial) { return [initial, function() {}]; };
        var useEffect = window.wp && window.wp.element ? window.wp.element.useEffect : function() { return function() {}; };
        var _a = useState(''), phone = _a[0], setPhone = _a[1];
        var _b = useState(''), error = _b[0], setError = _b[1];

        // Helper function to validate the phone number.
        function validatePhone(value) {
            if (!value || value.trim() === '') {
                return window.wp && window.wp.i18n ? window.wp.i18n.__('Phone number is required!', 'daraza-payments') : 'Phone number is required!';
            }
            var phonePattern = /^[0-9]{10,14}$/;
            if (!phonePattern.test(value)) {
                return window.wp && window.wp.i18n ? window.wp.i18n.__('Please enter a valid phone number!', 'daraza-payments') : 'Please enter a valid phone number!';
            }
            return '';
        }

        function handleBlur(e) {
            var errorMsg = validatePhone(e.target.value);
            setError(errorMsg);
        }

        function handleChange(e) {
            var value = e.target.value;
            // Only allow numbers
            value = value.replace(/[^0-9]/g, '');
            // Limit length
            if (value.length > 14) {
                value = value.substring(0, 14);
            }
            setPhone(value);
            // Clear error if valid
            if (validatePhone(value) === '') {
                setError('');
            }
        }

        // Register callback for when the checkout processes payment.
        useEffect(function() {
            if (!props.eventRegistration || !props.emitResponse) {
                return function() {};
            }
            
            var unsubscribe = props.eventRegistration.onPaymentProcessing(function() {
                var errorMsg = validatePhone(phone);
                if (errorMsg) {
                    setError(errorMsg);
                    return {
                        type: props.emitResponse.responseTypes.ERROR,
                        message: errorMsg
                    };
                }
                return {
                    type: props.emitResponse.responseTypes.SUCCESS,
                    meta: {
                        paymentMethodData: {
                            daraza_phone: phone
                        }
                    }
                };
            });
            return function() {
                if (unsubscribe) {
                    unsubscribe();
                }
            };
        }, [phone, props.eventRegistration, props.emitResponse]);

        // Create elements safely
        function createElement(type, props, children) {
            if (window.wp && window.wp.element && window.wp.element.createElement) {
                return window.wp.element.createElement(type, props, children);
            }
            // Fallback for testing
            return { type: type, props: props, children: children };
        }

        return createElement(
            'div',
            { className: 'daraza-payment-form', style: { marginTop: '10px', padding: '10px' } },
            // Show error message if any.
            error && createElement(
                'div',
                {
                    className: 'daraza-payment-error',
                    style: {
                        color: '#a94442',
                        backgroundColor: '#f2dede',
                        border: '1px solid #ebccd1',
                        borderRadius: '4px',
                        padding: '10px',
                        marginBottom: '10px'
                    }
                },
                error
            ),
            createElement(
                'label',
                { htmlFor: 'daraza_phone', style: { display: 'block', marginBottom: '4px', fontWeight: 'bold' } },
                window.wp && window.wp.i18n ? window.wp.i18n.__('Phone Number', 'daraza-payments') : 'Phone Number'
            ),
            createElement('input', {
                type: 'tel',
                id: 'daraza_phone',
                name: 'daraza_phone',
                placeholder: window.wp && window.wp.i18n ? window.wp.i18n.__('Enter your mobile money phone number', 'daraza-payments') : 'Enter your mobile money phone number',
                required: true,
                value: phone,
                onChange: handleChange,
                onBlur: handleBlur,
                pattern: '^[0-9]{10,14}$',
                maxLength: '14',
                style: {
                    width: '100%',
                    padding: '12px',
                    border: error ? '1px solid #a94442' : '1px solid #ccc',
                    borderRadius: '4px',
                    boxSizing: 'border-box'
                }
            })
        );
    }

    // Component: DarazaContent
    // Renders the gateway description, extra instructions, and the phone input form.
    function DarazaContent(props) {
        function createElement(type, props, children) {
            if (window.wp && window.wp.element && window.wp.element.createElement) {
                return window.wp.element.createElement(type, props, children);
            }
            return { type: type, props: props, children: children };
        }

        return createElement(
            'div',
            { className: 'daraza-payment-content', style: { fontFamily: 'Arial, sans-serif', lineHeight: '1.6', padding: '10px' } },
            description && createElement(
                'p',
                { style: { marginBottom: '15px' } },
                description
            ),
            // Render extra instructions if provided.
            instructions && createElement(
                'p',
                { style: { marginBottom: '15px', fontStyle: 'italic', color: '#555' } },
                instructions
            ),
            createElement(PaymentForm, props)
        );
    }

    // Component: Icon
    // Renders an icon image if provided in settings.
    function Icon() {
        function createElement(type, props, children) {
            if (window.wp && window.wp.element && window.wp.element.createElement) {
                return window.wp.element.createElement(type, props, children);
            }
            return { type: type, props: props, children: children };
        }

        if (settings.icons) {
            return createElement('img', {
                src: settings.icons,
                alt: title,
                style: { marginLeft: '8px', height: '24px', width: '24px' }
            });
        }
        return null;
    }

    // Component: LabelComponent
    // Combines the gateway title and the icon.
    function LabelComponent() {
        function createElement(type, props, children) {
            if (window.wp && window.wp.element && window.wp.element.createElement) {
                return window.wp.element.createElement(type, props, children);
            }
            return { type: type, props: props, children: children };
        }

        return createElement(
            'span',
            { style: { display: 'flex', alignItems: 'center', fontWeight: 'bold' } },
            title,
            Icon()
        );
    }

    // Build the payment method registration object.
    var paymentMethod = {
        // The "name" here must match the PHP gateway ID.
        name: 'daraza_rtp',
        label: window.wp && window.wp.element ? window.wp.element.createElement(LabelComponent, null) : LabelComponent(),
        content: window.wp && window.wp.element ? window.wp.element.createElement(DarazaContent, null) : DarazaContent(),
        edit: window.wp && window.wp.element ? window.wp.element.createElement(DarazaContent, null) : DarazaContent(),
        canMakePayment: function() {
            return true;
        },
        ariaLabel: title,
        supports: {
            features: settings.supports || ['products', 'refunds']
        }
    };

    // Register the payment method with WooCommerce Blocks.
    if (
        window.wc &&
        window.wc.wcBlocksRegistry &&
        typeof window.wc.wcBlocksRegistry.registerPaymentMethod === 'function'
    ) {
        window.wc.wcBlocksRegistry.registerPaymentMethod(paymentMethod);
    }
})();
