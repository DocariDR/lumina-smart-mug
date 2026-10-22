<?php
declare(strict_types=1);

use Lumina\Database\Connection;
use Lumina\Mail\OutboxMailer;
use Lumina\Mail\SmtpMailer;
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

if (strlen($config['ip_salt'] ?? '') < 32 || str_starts_with($config['ip_salt'], 'replace-with')) {
    error_log('Lumina : ip_salt non configuré (32 caractères minimum).');
    http_response_code(503);
    exit('Service indisponible.');
}

try {
    $pdo = Connection::create($config['db']);
} catch (\PDOException $exception) {
    error_log('Lumina : connexion à la base impossible : ' . $exception->getMessage());
    http_response_code(503);
    exit('Service indisponible.');
}

$mailer = match ($config['mailer']) {
    'outbox' => new OutboxMailer(__DIR__ . '/../storage/outbox'),
    'smtp' => new SmtpMailer($config['mail']),
    default => throw new RuntimeException('Mode d\'envoi inconnu.'),
};

return new SubscriptionService(
    new SubscriberRepository($pdo),
    $mailer,
    $config['app_url'],
    $config['ip_salt'],
);