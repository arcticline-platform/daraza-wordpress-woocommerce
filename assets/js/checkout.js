// assets/js/block-payment.js

// Retrieve settings for Daraza RTP payment method.
const settings = window.wc.wcSettings.getSetting( 'daraza_rtp_data', {} );

// Decode the title; fallback to a localized default if needed.
const label =
    window.wp.htmlEntities.decodeEntities( settings.title ) ||
    window.wp.i18n.__( 'Daraza RTP', 'daraza-payments' );

// Function to auto-validate phone input as the user types.
const validatePhone = ( event ) => {
    const input = event.target;
    const value = input.value;
    const regex = /^[0-9]{10,14}$/;
    // Change border color based on validation.
    if ( !regex.test( value ) ) {
        input.style.borderColor = 'red';
    } else {
        input.style.borderColor = 'green';
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
            window.wp.htmlEntities.decodeEntities( settings.description || '' )
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
                    htmlFor: 'daraza_rtp_phone',
                    style: { display: 'block', marginBottom: '0.5rem', fontWeight: 'bold', fontSize: '14px' }
                },
                window.wp.i18n.__( 'Phone Number', 'daraza-payments' )
            ),
            window.wp.element.createElement(
                'input',
                {
                    type: 'tel',
                    id: 'daraza_phone',
                    name: 'daraza_rtp_phone', // Added name attribute
                    required: true,
                    pattern: '^[0-9]{10,14}$',
                    placeholder: window.wp.i18n.__( 'Enter your mobile money phone number for payment', 'daraza-payments' ),
                    style: {
                        width: '100%',
                        padding: '0.5rem',
                        fontSize: '14px',
                        border: '1px solid #ccc',
                        borderRadius: '3px',
                        boxSizing: 'border-box'
                    },
                    onInput: validatePhone,
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
        window.wp.htmlEntities.decodeEntities( settings.title || '' )
    );
};

// Define the payment method block.
const DarazaBlock = {
    name: 'daraza_rtp',
    label: label,
    content: window.wp.element.createElement( Content, null ),
    edit: window.wp.element.createElement( Edit, null ),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
    onSubmit: (fields) => {
        return {
            ...fields,
            daraza_phone: document.getElementById('daraza_phone').value,
        };
    },
    // Capture the phone number value to include with the payment submission.
    getPaymentMethodData: () => {
        const phoneInput = document.getElementById( 'daraza_phone' );
        console.log(phoneInput);
        return {
            daraza_phone: phoneInput ? phoneInput.value : '',
        };
    },

};

// Register the payment method.
window.wc.wcBlocksRegistry.registerPaymentMethod( DarazaBlock );
