<?php

declare(strict_types=1);

use Lumina\Database\Connection;
use Lumina\Mail\OutboxMailer;
use Lumina\Subscription\SubscriberRepository;
use Lumina\Subscription\SubscriptionService;

require_once __DIR__ . '/bootstrap.php';

$configFile = __DIR__ . '/../config/config.php';

if (!is_file($configFile)) {
    error_log('Lumina : config/config.php introuvable.');
    http_response_code(503);
    exit('Service indisponible.');
}

$config = require $configFile;

try {
    $pdo = Connection::create($config['db']);
} catch (\PDOException $exception) {
    // Détails techniques dans le journal serveur uniquement, jamais dans la réponse.
    error_log('Lumina : connexion à la base impossible : ' . $exception->getMessage());
    http_response_code(503);
    exit('Service indisponible.');
}

$mailer = match ($config['mailer']) {
    'outbox' => new OutboxMailer(__DIR__ . '/../storage/outbox'),
    default => throw new RuntimeException('Mode d\'envoi inconnu.'),
};

return new SubscriptionService(
    new SubscriberRepository($pdo),
    $mailer,
    $config['app_url'],
    $config['ip_salt'],
);