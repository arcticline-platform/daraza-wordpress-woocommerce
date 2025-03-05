(function() {
    // Retrieve settings from PHP; ensure the key matches your PHP integration.
    var settings = window.wc.wcSettings.getSetting('daraza_payments_data', {});

    // Decode the title; use a default if not provided.
    var title = window.wp.htmlEntities.decodeEntities(settings.title) ||
                window.wp.i18n.__('Pay with Daraza', 'daraza-payments');

    // Decode the description and instructions.
    var description = window.wp.htmlEntities.decodeEntities(settings.description || '');
    var instructions = window.wp.htmlEntities.decodeEntities(settings.instructions || '');

    // Component to render the phone input field with validation.
    function PaymentForm(props) {
        var useState = window.wp.element.useState;
        var useEffect = window.wp.element.useEffect;
        var _a = useState(''), phone = _a[0], setPhone = _a[1];
        var _b = useState(''), error = _b[0], setError = _b[1];

        // Helper to validate the phone field.
        function validatePhone(value) {
            if (value.trim() === '') {
                return window.wp.i18n.__('Phone number is required.', 'daraza-payments');
            }
            var phonePattern = /^[0-9]{10,14}$/;
            if (!phonePattern.test(value)) {
                return window.wp.i18n.__('Please enter a valid phone number.', 'daraza-payments');
            }
            return '';
        }

        function handleBlur(e) {
            var errorMsg = validatePhone(e.target.value);
            setError(errorMsg);
        }

        // Register the callback to process payment data.
        useEffect(function() {
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
                unsubscribe();
            };
        }, [phone, props.eventRegistration, props.emitResponse]);

        return window.wp.element.createElement(
            'div',
            { className: 'daraza-payment-form', style: { marginTop: '10px', padding: '10px' } },
            // Show error alert if validation fails.
            error && window.wp.element.createElement(
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
            window.wp.element.createElement(
                'label',
                { htmlFor: 'daraza_phone', style: { display: 'block', marginBottom: '4px', fontWeight: 'bold' } },
                window.wp.i18n.__('Phone Number', 'daraza-payments')
            ),
            window.wp.element.createElement('input', {
                type: 'tel',
                id: 'daraza_phone',
                name: 'daraza_phone',
                placeholder: window.wp.i18n.__('Enter your mobile money phone number', 'daraza-payments'),
                required: true,
                value: phone,
                onChange: function(e) { setPhone(e.target.value); },
                onBlur: handleBlur,
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

    // Component to render the complete content for the payment method,
    // including description, instructions, and the phone input.
    function DarazaContent(props) {
        return window.wp.element.createElement(
            'div',
            { className: 'daraza-payment-content', style: { fontFamily: 'Arial, sans-serif', lineHeight: '1.6', padding: '10px' } },
            description && window.wp.element.createElement(
                'p',
                { style: { marginBottom: '15px' } },
                description
            ),
            // Render extra instructions if provided.
            instructions && window.wp.element.createElement(
                'p',
                { style: { marginBottom: '15px', fontStyle: 'italic', color: '#555' } },
                instructions
            ),
            window.wp.element.createElement(PaymentForm, props)
        );
    }

    // Component to render an icon if an icon URL is provided.
    function Icon() {
        if (settings.icons) {
            return window.wp.element.createElement('img', {
                src: settings.icons,
                alt: title,
                style: { marginLeft: '8px', height: '24px', width: '24px' }
            });
        }
        return null;
    }

    // Combine the title and icon into a single label component.
    function LabelComponent() {
        return window.wp.element.createElement(
            'span',
            { style: { display: 'flex', alignItems: 'center', fontWeight: 'bold' } },
            title,
            Icon()
        );
    }

    // Build the payment method registration object.
    var paymentMethod = {
        // This name must match the gateway ID in your PHP class.
        name: 'daraza_rtp',
        label: window.wp.element.createElement(LabelComponent, null),
        content: window.wp.element.createElement(DarazaContent, null),
        edit: window.wp.element.createElement(DarazaContent, null),
        canMakePayment: function() {
            return true;
        },
        ariaLabel: title,
        supports: {
            features: settings.supports
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
