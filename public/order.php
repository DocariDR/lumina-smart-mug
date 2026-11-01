<?php

declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

use Lumina\Order\UsStates;
start_secure_session();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Saisie et erreurs laissées par create-order.php : lues une seule fois, puis effacées.
$oldInput = is_array($_SESSION['order_old'] ?? null) ? $_SESSION['order_old'] : [];
$errorFields = is_array($_SESSION['order_errors'] ?? null) ? $_SESSION['order_errors'] : [];
unset($_SESSION['order_old'], $_SESSION['order_errors']);

$configFile = __DIR__ . '/../config/config.php';
$config = is_file($configFile) ? require $configFile : [];
$product = $config['product'] ?? [
    'name' => 'Lumina Mug Gen 2',
    'unit_price_cents' => 9900,
    'shipping_cents' => 0,
    'currency' => 'USD',
    'max_quantity' => 3,
];
$fx = $config['fx'] ?? ['usd_to_eur_rate_scaled' => 0, 'rate_at' => ''];
$fxRateScaled = (int) ($fx['usd_to_eur_rate_scaled'] ?? 0);
$fxRateScale = 1000000;
$eurXofRateScaled = 655957;
$eurXofScale = 1000;
$defaultUsdCents = (int) $product['unit_price_cents'] + (int) $product['shipping_cents'];
$xofDenominator = 100 * $fxRateScale * $eurXofScale;
$defaultXof = $fxRateScaled > 0
    ? intdiv(($defaultUsdCents * $fxRateScaled * $eurXofRateScaled) + $xofDenominator - 1, $xofDenominator)
    : 0;
$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$reference = is_string($_GET['ref'] ?? null) ? $_GET['ref'] : '';
$messages = [
    'csrf' => 'Your form session has expired. Please reload the page and try again.',
    'error' => 'The order could not be saved. Please try again in a few moments.',
    'payment-error' => 'The secure payment page could not be opened. No payment was taken. Please try again in a few minutes.',
];
$price = number_format(((int) $product['unit_price_cents']) / 100, 2, '.', '');
$total = number_format(((int) $product['unit_price_cents'] + (int) $product['shipping_cents']) / 100, 2, '.', '');
$currency = (string) $product['currency'];
$maxQuantity = (int) $product['max_quantity'];

// Message affiché pour chaque champ en erreur (les clés correspondent à OrderValidationException).
$fieldMessages = [
    'customer_name' => 'Enter your full name (120 characters maximum).',
    'email' => 'Enter a valid email address.',
    'shipping_address' => 'Enter your delivery address (255 characters maximum).',
    'city' => 'Enter your city (100 characters maximum).',
    'state_code' => 'Choose your state.',
    'postal_code' => 'Enter a valid ZIP code, such as 90210 or 90210-1234.',
    'country_code' => 'We currently ship to the United States only.',
    'quantity' => 'Choose a quantity between 1 and ' . $maxQuantity . '.',
    'cgv_accepted' => 'Accept the general terms of sale to continue.',
    'privacy_acknowledged' => 'Confirm that you have read the privacy policy to continue.',
];

/** Échappement HTML court. */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Valeur saisie précédemment, pour réaffichage. */
function old(array $input, string $field): string
{
    return is_string($input[$field] ?? null) ? $input[$field] : '';
}

/** Attributs d'accessibilité d'un champ en erreur. */
function invalid_attrs(array $errorFields, string $field): string
{
    return in_array($field, $errorFields, true)
        ? ' aria-invalid="true" aria-describedby="err-' . e($field) . '"'
        : '';
}

/** Message d'erreur relié à un champ. */
function field_error(array $errorFields, array $messages, string $field): string
{
    if (!in_array($field, $errorFields, true)) {
        return '';
    }
    return '<p id="err-' . e($field) . '" class="field-error">' . e($messages[$field] ?? 'Check this field.') . '</p>';
}

$quantityValue = (int) old($oldInput, 'quantity');
if ($quantityValue < 1 || $quantityValue > $maxQuantity) {
    $quantityValue = 1;
}
$stateValue = old($oldInput, 'state_code');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex" />
    <title>Preorder | Lumina Smart Mug</title>
    <meta name="theme-color" content="#a04000" />
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%E2%98%95%3C/text%3E%3C/svg%3E" />
    <link rel="stylesheet" href="assets/css/styles.css" />
    <link rel="stylesheet" href="assets/css/v3-overrides.css" />
    <link rel="stylesheet" href="assets/css/order.css" />
</head>

<body>
    <main class="container order-page">
        <p class="eyebrow">Lumina Smart Mug</p>
        <h1>Prepare your preorder</h1>
        <?php if ($status === 'created'): ?>
            <section class="order-success" role="status">
                <h2>Preorder registered</h2>
                <p>Your reference number is <strong><?= e($reference) ?></strong>.</p>
                <p>Secure payment via FedaPay will be activated in the upcoming minor release.</p>
                <p><a href="/">Return to the homepage</a></p>
            </section>
        <?php else: ?>
            <?php if ($errorFields !== []): ?>
                <section class="order-error-summary" role="alert" aria-labelledby="error-summary-title">
                    <h2 id="error-summary-title">Please correct the following:</h2>
                    <ul>
                        <?php foreach ($errorFields as $field): ?>
                            <?php if (isset($fieldMessages[$field])): ?>
                                <li><a href="#<?= e($field) ?>"><?= e($fieldMessages[$field]) ?></a></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php elseif ($status === 'invalid'): ?>
                <p class="order-error" role="alert">Please check the information entered and try again.</p>
            <?php elseif (isset($messages[$status])): ?>
                <p class="order-error" role="alert"><?= e($messages[$status]) ?></p>
            <?php endif; ?>

            <div class="order-layout">
                <section class="product-summary" aria-labelledby="product-title">
                    <h2 id="product-title"><?= e((string) $product['name']) ?></h2>
                    <p class="order-price"><?= $price ?> <?= e($currency) ?></p>
                    <p>Keeps your beverage at your desired temperature for hours.</p>
                </section>
                <form class="order-form" method="post" action="create-order.php">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>" />

                    <label for="customer_name">Full name</label>
                    <input id="customer_name" name="customer_name" type="text" maxlength="120" autocomplete="name" placeholder="e.g. John Doe" value="<?= e(old($oldInput, 'customer_name')) ?>" required<?= invalid_attrs($errorFields, 'customer_name') ?> />
                    <?= field_error($errorFields, $fieldMessages, 'customer_name') ?>

                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" maxlength="254" autocomplete="email" placeholder="your@email.com" value="<?= e(old($oldInput, 'email')) ?>" required<?= invalid_attrs($errorFields, 'email') ?> />
                    <?= field_error($errorFields, $fieldMessages, 'email') ?>

                    <label for="shipping_address">Delivery address</label>
                    <input id="shipping_address" name="shipping_address" type="text" maxlength="255" autocomplete="street-address" placeholder="Street name and number" value="<?= e(old($oldInput, 'shipping_address')) ?>" required<?= invalid_attrs($errorFields, 'shipping_address') ?> />
                    <?= field_error($errorFields, $fieldMessages, 'shipping_address') ?>

                    <div class="form-row">
                        <div>
                            <label for="postal_code">ZIP code</label>
                            <input id="postal_code" name="postal_code" type="text" maxlength="10" inputmode="numeric" autocomplete="postal-code" placeholder="e.g. 90210" value="<?= e(old($oldInput, 'postal_code')) ?>" required<?= invalid_attrs($errorFields, 'postal_code') ?> />
                            <?= field_error($errorFields, $fieldMessages, 'postal_code') ?>
                        </div>
                        <div>
                            <label for="city">City</label>
                            <input id="city" name="city" type="text" maxlength="100" autocomplete="address-level2" placeholder="e.g. Los Angeles" value="<?= e(old($oldInput, 'city')) ?>" required<?= invalid_attrs($errorFields, 'city') ?> />
                            <?= field_error($errorFields, $fieldMessages, 'city') ?>
                        </div>
                    </div>

                    <label for="state_code">State</label>
                    <select id="state_code" name="state_code" autocomplete="address-level1" required<?= invalid_attrs($errorFields, 'state_code') ?>>
                        <option value="">Choose a state</option>
                        <?php foreach (UsStates::STATES as $code => $label): ?>
                            <option value="<?= e($code) ?>"<?= $stateValue === $code ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errorFields, $fieldMessages, 'state_code') ?>

                    <input type="hidden" name="country_code" value="US" />
                    <p id="country_code" class="shipping-note" tabindex="-1">Shipping: United States only.</p>
                    <?= field_error($errorFields, $fieldMessages, 'country_code') ?>

                    <label for="quantity">Quantity</label>
                    <select id="quantity" name="quantity" required<?= invalid_attrs($errorFields, 'quantity') ?>>
                        <?php for ($i = 1; $i <= $maxQuantity; $i++): ?>
                            <option value="<?= $i ?>"<?= $quantityValue === $i ? ' selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                    <?= field_error($errorFields, $fieldMessages, 'quantity') ?>

                    <label class="check-row">
                        <input id="cgv_accepted" type="checkbox" name="cgv_accepted" value="1" required<?= invalid_attrs($errorFields, 'cgv_accepted') ?> />
                        <span>I accept the <a href="terms.html" target="_blank" rel="noopener">general terms of sale</a>.</span>
                    </label>
                    <?= field_error($errorFields, $fieldMessages, 'cgv_accepted') ?>

                    <label class="check-row">
                        <input id="privacy_acknowledged" type="checkbox" name="privacy_acknowledged" value="1" required<?= invalid_attrs($errorFields, 'privacy_acknowledged') ?> />
                        <span>I have read and understood the <a href="privacy.html" target="_blank" rel="noopener">privacy policy</a>.</span>
                    </label>
                    <?= field_error($errorFields, $fieldMessages, 'privacy_acknowledged') ?>

                    <div class="hp" aria-hidden="true">
                        <label for="website">Leave this field empty</label>
                        <input id="website" name="website" type="text" tabindex="-1" autocomplete="off" placeholder="Do not fill this field" />
                    </div>

                    <p class="total-line" aria-live="polite">Indicative total:
                        <strong><span id="usd-total" data-unit-cents="<?= (int) $product['unit_price_cents'] ?>" data-shipping-cents="<?= (int) $product['shipping_cents'] ?>"><?= $total ?></span> <?= e($currency) ?></strong>
                        <br><strong><span id="xof-total" data-rate-scaled="<?= $fxRateScaled ?>" data-rate-scale="<?= $fxRateScale ?>" data-eur-xof="<?= $eurXofRateScaled ?>" data-eur-xof-scale="<?= $eurXofScale ?>"><?= number_format($defaultXof, 0, ',', ' ') ?></span> XOF</strong>
                        <br><small>Indicative rate: 1 USD = <?= number_format($fxRateScaled / $fxRateScale, 6, '.', '') ?> EUR, then 1 EUR = 655.957 XOF. The amount charged is fixed when the order is placed.</small>
                    </p>

                    <button class="buy-btn" type="submit">Submit my preorder</button>
                </form>
            </div>
        <?php endif; ?>
    </main>
    <script src="assets/js/order.js" defer></script>
</body>

</html>