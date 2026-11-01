<?php
declare(strict_types=1);
use Lumina\Order\OrderService;
use Lumina\Order\OrderValidationException;
use Lumina\Payment\FedaPayClient;
use Lumina\Subscription\EmailValidator;
require_once __DIR__ . '/../src/bootstrap.php';
start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

$sessionToken = is_string($_SESSION['csrf_token'] ?? null) ? $_SESSION['csrf_token'] : '';
$postedToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
if ($sessionToken === '' || $postedToken === '' || !hash_equals($sessionToken, $postedToken)) {
    header('Location: /order.php?status=csrf', true, 303);
    exit;
}

// Jeton à usage unique : un second envoi avec le même jeton (double clic, script) échoue.
// order.php en génère un nouveau à chaque affichage.
unset($_SESSION['csrf_token']);

// Champ piège : réponse générique, sans indiquer quel champ, et sans restaurer la saisie.
if (($_POST['website'] ?? '') !== '') {
    unset($_SESSION['order_old'], $_SESSION['order_errors']);
    header('Location: /order.php?status=invalid', true, 303);
    exit;
}

/**
 * Garde la saisie pour la réafficher après une erreur.
 * Le consentement (CGV, confidentialité) n'est jamais restauré : le client doit le recocher.
 * @return array<string, string>
 */
function keep_order_input(array $post): array
{
    $kept = [];
    foreach (['customer_name', 'email', 'shipping_address', 'city', 'state_code', 'postal_code', 'quantity'] as $field) {
        $value = is_string($post[$field] ?? null) ? $post[$field] : '';
        $kept[$field] = mb_substr($value, 0, 255);
    }
    return $kept;
}

try {
    /** @var OrderService $orders */
    $orders = require __DIR__ . '/../src/order-service.php';
} catch (Throwable $exception) {
    // Configuration absente ou invalide (base de données) : erreur serveur, pas une saisie invalide.
    error_log('Lumina : service de commande indisponible : ' . $exception->getMessage());
    header('Location: /order.php?status=error', true, 303);
    exit;
}

try {
    $result = $orders->create($_POST);
} catch (OrderValidationException $exception) {
    $_SESSION['order_old'] = keep_order_input($_POST);
    $_SESSION['order_errors'] = $exception->fields;
    header('Location: /order.php?status=invalid', true, 303);
    exit;
} catch (Throwable $exception) {
    error_log('Lumina : commande non créée : ' . $exception->getMessage());
    header('Location: /order.php?status=error', true, 303);
    exit;
}

try {
    $configFile = __DIR__ . '/../config/config.php';
    $config = is_file($configFile) ? require $configFile : [];
    $fedapay = new FedaPayClient($config['fedapay'] ?? []);
    $appUrl = rtrim((string) ($config['app_url'] ?? ''), '/');
    // Le retour du client doit revenir sur le site en HTTPS. Sinon, pas de redirection vers FedaPay.
    if (!str_starts_with($appUrl, 'https://') || strlen($appUrl) <= strlen('https://')) {
        throw new RuntimeException('app_url absente ou sans https:// dans config/config.php.');
    }
    $callbackUrl = $appUrl . '/payment-return.php?order=' . rawurlencode($result['reference']);
    $payment = $fedapay->createTransactionAndToken(
        'Lumina Smart Mug preorder #' . $result['reference'],
        $result['total_xof'],
        EmailValidator::normalize((string) ($_POST['email'] ?? '')),
        is_string($_POST['customer_name'] ?? null) ? $_POST['customer_name'] : '',
        $callbackUrl,
        $result['reference'],
    );
    $orders->attachPayment($result['reference'], 'fedapay', $payment['transaction_id']);
} catch (Throwable $exception) {
    error_log('Lumina : paiement FedaPay impossible : ' . $exception->getMessage());
    // La commande ne doit pas rester « pending » sans paiement : elle ne bloquera pas une nouvelle tentative.
    try {
        $orders->markPaymentFailed($result['reference']);
    } catch (Throwable $markException) {
        error_log('Lumina : marquage de la commande en échec impossible : ' . $markException->getMessage());
    }
    header('Location: /order.php?status=payment-error', true, 303);
    exit;
}

unset($_SESSION['order_old'], $_SESSION['order_errors']);
header('Location: ' . $payment['payment_url'], true, 303);
exit;