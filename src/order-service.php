<?php
declare(strict_types=1);
use Lumina\Database\Connection;
use Lumina\Order\CurrencyConverter;
use Lumina\Order\OrderRepository;
use Lumina\Order\OrderService;
require_once __DIR__ . '/bootstrap.php';

// Les erreurs de configuration lèvent une exception (et non un exit) : create-order.php
// les intercepte et redirige vers la page d'erreur prévue, même si la base est indisponible.
$configFile = __DIR__ . '/../config/config.php';
if (!is_file($configFile)) {
    throw new RuntimeException('config/config.php introuvable.');
}

$config = require $configFile;

if (strlen($config['ip_salt'] ?? '') < 32 || str_starts_with($config['ip_salt'], 'replace-with')) {
    throw new RuntimeException('ip_salt non configuré (32 caractères minimum).');
}

try {
    $pdo = Connection::create($config['db']);
} catch (\PDOException $exception) {
    throw new RuntimeException('connexion à la base impossible : ' . $exception->getMessage(), 0, $exception);
}

$fx = $config['fx'] ?? [];
return new OrderService(
    new OrderRepository($pdo),
    $config['product'] ?? [],
    new CurrencyConverter(
        (int) ($fx['usd_to_eur_rate_scaled'] ?? 0),
        (string) ($fx['rate_at'] ?? ''),
    ),
);