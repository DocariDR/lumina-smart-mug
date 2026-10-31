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

    // Paramètres de la précommande. Montants en centimes, en USD. Livraison aux États-Unis uniquement.
    'product' => [
        'name' => 'Lumina Mug Gen 2',
        'unit_price_cents' => 9900,
        'shipping_cents' => 0,
        'currency' => 'USD',
        'max_quantity' => 3,
    ],
    // Taux USD vers EUR en millionièmes, sans flottant : 920000 = 0,920000.
    // Ce taux est figé dans chaque commande. Le taux EUR/XOF est 655,957.
    'fx' => [
        'usd_to_eur_rate_scaled' => 920000,
        'rate_at' => '2026-11-02 00:00:00',
    ],

    // V4.2 : la clé secrète reste uniquement dans config/config.php.
    'fedapay' => [
        'environment' => 'sandbox',
        'api_key' => 'REPLACE_WITH_SANDBOX_SECRET_KEY', // sk_sandbox_… en sandbox, sk_live_… en live
        'timeout' => 20,
    ],

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