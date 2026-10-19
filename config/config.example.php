<?php

// Copier ce fichier en config/config.php et renseigner les valeurs.
// config/config.php ne doit jamais être versionné ni publié.

return [
    // URL publique du site, sans slash final (utilisée dans les liens des emails)
    'app_url' => 'https://www.example.com',

    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'lumina',
        'user' => 'lumina_user',
        'password' => 'change-me',
    ],

    // Clé secrète longue et aléatoire, utilisée pour pseudonymiser les adresses IP
    'ip_salt' => 'replace-with-a-long-random-string',

    // V2 : 'outbox' écrit les emails dans storage/outbox (développement uniquement).
    // V3 remplacera ce mode par l'envoi SMTP.
    'mail' => [
        'host' => 'sandbox.smtp.mailtrap.io',
        'port' => 587,
        'username' => 'TON_USERNAME_MAILTRAP',
        'password' => 'TON_NOUVEAU_MOT_DE_PASSE',
        'encryption' => 'tls',
        'from_email' => 'contact@lumina.com',
        'from_name' => 'Lumina Smart Mug',
        'reply_to' => 'no-reply@lumina.com',
    ],
];