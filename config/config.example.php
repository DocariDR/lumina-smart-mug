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

    // 'outbox' : les emails sont écrits dans storage/outbox/ (développement uniquement).
    // 'smtp'   : envoi réel via un fournisseur SMTP (production). Ne pas utiliser le sandbox Mailtrap en production.
    'mailer' => 'outbox',
    'mail' => [
        'host' => 'sandbox.smtp.mailtrap.io',
        'port' => 587,
        'username' => 'TON_USERNAME_MAILTRAP',
        'password' => 'TON_NOUVEAU_MOT_DE_PASSE',
        'encryption' => 'tls',
        // Adresses d'exemple : remplacer par celles de votre domaine (authentifié en SPF/DKIM).
        'from_email' => 'contact@votre-domaine.tld',
        'from_name' => 'Lumina Smart Mug',
        // Adresse qui reçoit les réponses des abonnés (ne pas utiliser une adresse « no-reply »).
        'reply_to' => 'reponses@votre-domaine.tld',
    ],
];