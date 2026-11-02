<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

// Page de retour : jamais mise en cache par le navigateur ni par un intermédiaire.
header('Cache-Control: no-store');

// La référence n'est affichée que si elle a le format d'une référence de commande.
$orderReference = is_string($_GET['order'] ?? null) ? $_GET['order'] : '';
$showReference = preg_match('/^[A-F0-9]{16}$/', $orderReference) === 1;
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex" />
    <title>Back from the payment page | Lumina Smart Mug</title>
    <meta name="theme-color" content="#a04000" />
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%E2%98%95%3C/text%3E%3C/svg%3E" />
    <link rel="stylesheet" href="assets/css/styles.css" />
    <link rel="stylesheet" href="assets/css/v3-overrides.css" />
</head>
<body>
    <main class="container legal-page">
        <section>
            <h1>Back from the payment page</h1>
            <?php if ($showReference): ?>
            <p>Reference: <strong><?= htmlspecialchars($orderReference, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></strong></p>
            <?php endif; ?>
            <p>We have received your return from the payment page. This page does not confirm your payment: your pre-order status will be updated once the payment has been verified.</p>
            <p><a href="/">Return to Lumina</a></p>
        </section>
    </main>
</body>
</html>