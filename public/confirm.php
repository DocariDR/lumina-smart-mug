<?php

declare(strict_types=1);

use Lumina\Subscription\ConfirmOutcome;

/** @var \Lumina\Subscription\SubscriptionService $subscriptions */
$subscriptions = require __DIR__ . '/../src/services.php';

// Étape 1 (GET) : on affiche un bouton sans valider le jeton.
// Les scanners de sécurité des messageries ouvrent les liens automatiquement,
// ce qui consommerait le jeton sans intervention de la personne.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
    if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
        redirect_home(ConfirmOutcome::TokenInvalid->value);
    }
    $tokenHtml = htmlspecialchars($token, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    header('X-Robots-Tag: noindex');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex" />
    <title>Confirm your subscription | Lumina</title>
    <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
    <main class="container">
        <section>
            <h1>Confirm your subscription</h1>
            <p>Click the button below to confirm your email address and start receiving the Lumina newsletter.</p>
            <form method="post" action="confirm.php">
                <input type="hidden" name="token" value="<?= $tokenHtml ?>" />
                <button id="confirm-btn" type="submit" class="buy-btn">Confirm subscription</button>
            </form>
        </section>
    </main>
</body>
</html>
    <?php
    exit;
}

// Étape 2 (POST) : consommation du jeton.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = is_string($_POST['token'] ?? null) ? $_POST['token'] : '';
    redirect_home($subscriptions->confirm($token)->value);
}

header('Allow: GET, POST');
http_response_code(405);