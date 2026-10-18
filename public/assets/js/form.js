(function () {
    'use strict';

    var message = document.getElementById('form-message');
    if (!message) {
        return;
    }

    var texts = {
        invalid: 'Please enter a valid email address.',
        consent: 'Please accept the newsletter terms to subscribe.',
        'check-email': 'Almost done! Check your inbox to confirm your subscription.',
        confirmed: 'Thanks! Your subscription is confirmed.',
        'token-invalid': 'This confirmation link is invalid or has expired. Please subscribe again.',
        error: 'Something went wrong. Please try again in a few minutes.'
    };

    var status = new URLSearchParams(window.location.search).get('newsletter');
    if (status && Object.prototype.hasOwnProperty.call(texts, status)) {
        message.textContent = texts[status];
    }
})();