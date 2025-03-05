// assets/js/block-payment.js

// Retrieve settings for Daraza RTP payment method.
const settings = window.wc.wcSettings.getSetting('daraza_payments_data', {});

// Decode the title; fallback to a localized default if needed.
const label =
    window.wp.htmlEntities.decodeEntities(settings.title) ||
    window.wp.i18n.__('Daraza RTP', 'daraza-payments');

// Function to validate phone input with more robust validation.
const validatePhone = (event) => {
    const input = event.target;
    const value = input.value.trim();
    // Regex to match international phone number formats.
    const regex = /^(\+\d{1,3}[- ]?)?\d{10,14}$/;

    if (regex.test(value)) {
        input.setCustomValidity('');
        input.style.borderColor = 'green';
        return true;
    } else {
        input.setCustomValidity(
            window.wp.i18n.__('Please enter a valid phone number', 'daraza-payments')
        );
        input.style.borderColor = 'red';
        return false;
    }
};

// Create the Content component using createElement.
const Content = () => {
    return window.wp.element.createElement(
        'div',
        {
            className: 'daraza-payment-method',
            style: {
                padding: '1rem',
                border: '1px solid #e0e0e0',
                borderRadius: '5px',
                backgroundColor: '#f9f9f9',
                fontFamily: 'Arial, sans-serif'
            }
        },
        // Description block.
        window.wp.element.createElement(
            'div',
            {
                className: 'daraza-description',
                style: { marginBottom: '1rem', fontSize: '14px', color: '#333' }
            },
            window.wp.htmlEntities.decodeEntities(settings.description || '')
        ),
        // Phone input container.
        window.wp.element.createElement(
            'div',
            {
                className: 'daraza_phone-input',
                style: { marginBottom: '1rem' }
            },
            window.wp.element.createElement(
                'label',
                {
                    htmlFor: 'daraza_phone',
                    style: { display: 'block', marginBottom: '0.5rem', fontWeight: 'bold', fontSize: '14px' }
                },
                window.wp.i18n.__('Phone Number', 'daraza-payments')
            ),
            window.wp.element.createElement(
                'input',
                {
                    type: 'tel',
                    id: 'daraza_phone',
                    name: 'daraza_phone',
                    required: true,
                    pattern: '^(\+\d{1,3}[- ]?)?\d{10,14}$',
                    placeholder: window.wp.i18n.__('Enter your mobile money phone number', 'daraza-payments'),
                    style: {
                        width: '100%',
                        padding: '0.5rem',
                        fontSize: '14px',
                        border: '1px solid #ccc',
                        borderRadius: '3px',
                        boxSizing: 'border-box'
                    },
                    onInput: validatePhone,
                    'aria-required': 'true',
                    'aria-label': window.wp.i18n.__('Mobile Money Phone Number', 'daraza-payments')
                }
            )
        )
    );
};

// Create a simple Edit component that displays the payment method title.
const Edit = () => {
    return window.wp.element.createElement(
        'div',
        {
            className: 'daraza-payment-method-edit',
            style: { fontFamily: 'Arial, sans-serif', fontSize: '16px', fontWeight: 'bold', padding: '0.5rem' }
        },
        window.wp.htmlEntities.decodeEntities(settings.title || '')
    );
};

// Define the payment method block.
const DarazaBlock = {
    name: 'daraza_rtp',
    label: label,
    content: window.wp.element.createElement(Content, null),
    edit: window.wp.element.createElement(Edit, null),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
    // onSubmit: Validate and include the phone number.
    onSubmit: (fields) => {
        const phoneInput = document.getElementById('daraza_phone');
        if (!phoneInput || !validatePhone({ target: phoneInput })) {
            throw new Error(window.wp.i18n.__('Invalid phone number', 'daraza-payments'));
        }
        return {
            ...fields,
            daraza_phone: phoneInput.value.trim(),
        };
    },
    // getPaymentMethodData: Returns the phone number.
    getPaymentMethodData: () => {
        const phoneInput = document.getElementById('daraza_phone');
        return {
            daraza_phone: phoneInput ? phoneInput.value.trim() : '',
        };
    },
    // setPaymentMethodData: Sets the phone field's value.
    setPaymentMethodData: (data) => {
        const phoneInput = document.getElementById('daraza_phone');
        if (phoneInput) {
            const phoneValue = data.daraza_phone || '';
            phoneInput.value = phoneValue;
            validatePhone({ target: phoneInput });
        }
    },
    // Initialize paymentMethodData.
    paymentMethodData: {
        daraza_phone: '',
    },
    // prepare: Validate and prepare the data.
    prepare: () => {
        const phoneInput = document.getElementById('daraza_phone');
        const phoneNumber = phoneInput ? phoneInput.value.trim() : '';

        if (!phoneNumber || !validatePhone({ target: phoneInput })) {
            throw new Error(window.wp.i18n.__('Please enter a valid phone number', 'daraza-payments'));
        }
        return {
            daraza_phone: phoneNumber
        };
    },
    // processPaymentMethodData: Use consistent key name.
    processPaymentMethodData: (data) => {
        const phoneInput = document.getElementById('daraza_phone');
        const phoneNumber = phoneInput ? phoneInput.value.trim() : '';
        return {
            ...data,
            daraza_phone: phoneNumber
        };
    }
};

// Register the payment method.
wp.hooks.addFilter(
    'woocommerce_blocks_payment_method_data',
    'daraza-payments/custom-payment-data',
    (paymentData, paymentMethodName) => {
        if ( paymentMethodName === 'daraza_rtp' ) {
            const phoneInput = document.getElementById('daraza_phone');
            if ( phoneInput ) {
                // Add the phone number to the paymentData object.
                paymentData.daraza_phone = phoneInput.value.trim();
            }
        }
        return paymentData;
    }
);

