<?php
declare(strict_types=1);
use Lumina\Subscription\UnsubscribeOutcome;

/** @var \Lumina\Subscription\SubscriptionService $subscriptions */
$subscriptions = require __DIR__ . '/../src/services.php';
$token = is_string($_GET['token'] ?? $_POST['token'] ?? null) ? ($_GET['token'] ?? $_POST['token']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oneClick = ($_POST['List-Unsubscribe'] ?? '') === 'One-Click';
    $outcome = $subscriptions->unsubscribe($token);
    if ($oneClick) {
        http_response_code(204);
        exit;
    }
    header('Location: /unsubscribe.php?status=' . rawurlencode($outcome->value), true, 303);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}
$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$message = match ($status) {
    'unsubscribed' => 'Your subscription has been cancelled.',
    'token-invalid' => 'This unsubscribe link is invalid or has already been used.',
    default => 'Click the button below to stop receiving the Lumina newsletter.',
};
$tokenHtml = htmlspecialchars($token, ENT_QUOTES | ENT_HTML5, 'UTF-8');
header('X-Robots-Tag: noindex');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex" />
    <title>Unsubscribe | Lumina</title>
    <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
    <main class="container">
        <section>
            <h1>Unsubscribe from Lumina</h1>
            <p><?= htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></p>
            <?php if ($status === ''): ?>
                <form method="post" action="unsubscribe.php">
                    <input type="hidden" name="token" value="<?= $tokenHtml ?>" />
                    <button type="submit" class="buy-btn">Unsubscribe</button>
                </form>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>