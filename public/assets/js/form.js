(function () {
    'use strict';

    var message = document.getElementById('form-message');
    if (!message) {
        return;
    }

    var texts = {
        invalid: 'Please enter a valid email address.',
        unavailable: 'Sign-ups open very soon. Thanks for your interest!'
    };

    var status = new URLSearchParams(window.location.search).get('newsletter');
    if (status && Object.prototype.hasOwnProperty.call(texts, status)) {
        message.textContent = texts[status];
    }
})();