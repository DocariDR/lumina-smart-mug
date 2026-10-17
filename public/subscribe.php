<?php

declare(strict_types=1);

use Lumina\Subscription\SubscribeOutcome;

/** @var \Lumina\Subscription\SubscriptionService $subscriptions */
$subscriptions = require __DIR__ . '/../src/services.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

// Honeypot : un champ caché que les humains ne remplissent pas.
// Un robot qui le remplit reçoit une réponse de succès sans que rien ne soit enregistré.
if (($_POST['website'] ?? '') !== '') {
    redirect_home(SubscribeOutcome::CheckEmail->value);
}

$rawEmail = $_POST['email'] ?? '';
$consent = ($_POST['consent'] ?? '') === '1';
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$outcome = $subscriptions->subscribe(
    is_string($rawEmail) ? $rawEmail : '',
    $consent,
    $ip,
);

redirect_home($outcome->value);