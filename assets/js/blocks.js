const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { getSetting } = window.wc.wcSettings;
const { decodeEntities } = window.wp.htmlEntities;
const { __ } = window.wp.i18n;
const { createElement } = window.wp.element;

const settings = getSetting('strike_lightning_data', {});

const label = decodeEntities(settings.title) || __('Bitcoin Lightning Payment', 'strike-lightning-payment');

// Create Icon component without JSX
const Icon = function() {
    if (!settings.icon) return null;
    return createElement('img', {
        src: settings.icon,
        style: { float: 'right', height: '24px' }
    });
};

// Create Label component without JSX
const Label = function() {
    return createElement('span', 
        { style: { width: '100%' } },
        label,
        createElement(Icon, null)
    );
};

// Create Content component without JSX
const Content = function() {
    return decodeEntities(settings.description || '');
};

registerPaymentMethod({
    name: "strike_lightning",
    label: createElement(Label, null),
    content: createElement(Content, null),
    edit: createElement(Content, null),
    canMakePayment: function() { return true; },
    ariaLabel: label,
    supports: {
        features: settings.supports || ['products'],
    },
    paymentMethodId: 'strike_lightning',
});
