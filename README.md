# Lumina Smart Mug

![Status](https://img.shields.io/badge/version-V1.3.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL%2FMariaDB-4479A1?logo=mysql&logoColor=white)
![PHPMailer](https://img.shields.io/badge/PHPMailer-SMTP-777BB4)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?logo=css3&logoColor=white)

Landing page du produit Lumina Smart Mug : newsletter avec double opt-in et désinscription en un clic, tunnel de précommande (sans paiement pour l'instant), pages légales. Né d'un projet freeCodeCamp (Responsive Web Design).

![Aperçu du projet](screenshot.png)

## Où en est le projet (V1.3.0)

- **Tunnel de précommande, sans paiement** : le bouton « Pre-order Now » mène à `order.php` ; `create-order.php` valide la saisie côté serveur et enregistre une commande `pending` avec une référence aléatoire. Aucun paiement et aucune donnée bancaire dans cette version.
- **Montants calculés côté serveur** : le prix unitaire et la quantité maximale viennent de `config/config.php`, les valeurs envoyées par le navigateur sont ignorées. Les montants sont stockés en centimes USD.
- **Conversion USD vers XOF figée par commande** : USD, puis EUR (taux défini dans la configuration, indicatif), puis XOF (parité fixe 655,957), en entiers et arrondie au supérieur. Le montant XOF, les taux et leur date sont enregistrés avec la commande : le futur paiement FedaPay devra utiliser ce montant, jamais un recalcul.
- **Livraison aux États-Unis uniquement** : 50 États et District de Columbia, code postal ZIP ou ZIP+4.
- **Formulaire** : jeton CSRF à usage unique, honeypot, refus d'une commande identique (même email, même quantité) déjà en attente depuis moins de 10 minutes.
- **Formulaire accessible** : erreurs signalées champ par champ (résumé avec liens, `aria-invalid`), saisie conservée après une erreur, cases de consentement jamais restaurées.
- **Pages légales** : `terms.html` (conditions de vente de démonstration) et `privacy.html` complétée (données de commande, cookie de session strictement nécessaire).
- **Limite assumée** : le site est une démonstration, aucune commande n'est livrée.

## Ce que j'ai mis en pratique

- Gestion du cycle de vie d'un abonné (`pending`, `confirmed`, `unsubscribed`) avec deux jetons distincts, et renouvellement des jetons quand une adresse désinscrite se réinscrit.
- Dépendance gérée avec Composer (PHPMailer) derrière une interface (`MailerInterface`) : le service ne sait pas s'il écrit un fichier ou parle à un serveur SMTP.
- En-têtes d'email standards pour la désinscription en un clic (RFC 8058) et email HTML en tableaux avec version texte alternative.
- Gestion d'erreurs au bon niveau : `Throwable` capturé à la frontière du service, journalisation côté serveur, message neutre côté visiteur.
- Pseudonymisation de l'IP, consentement versionné et politique de confidentialité qui décrit uniquement ce que le code fait réellement.
- Séparation interface / implémentation (`SubscriberStore` / `SubscriberRepository`) et enums PHP natifs pour les résultats (`SubscribeOutcome`, `ConfirmOutcome`, `UnsubscribeOutcome`).

## Démo

[Voir la démo en ligne](https://lumina-smart-mug.kesug.com/)

## Stack technique

- PHP 8.4
- MySQL 8 ou MariaDB 10.5+ (PDO, requêtes préparées)
- PHPMailer 7 (installé avec Composer)
- HTML5 / CSS3
- JavaScript (vanilla, sans dépendance)
- Apache (`.htaccess`) pour les en-têtes de sécurité et la protection des dossiers sensibles

## Installation locale

```bash
git clone https://github.com/DocariDR/lumina-smart-mug.git
cd lumina-smart-mug
composer install
cp config/config.example.php config/config.php
```

Renseigne dans `config/config.php` tes identifiants de base de données locale et un `ip_salt` d'au moins 32 caractères (par exemple `openssl rand -hex 32`), puis importe le schéma :

```bash
mysql -u UTILISATEUR -p NOM_DE_BASE < db/schema.sql
php -S localhost:8000 -t public
```

Ouvre ensuite `http://localhost:8000`. Avec `'mailer' => 'outbox'`, les emails atterrissent dans `storage/outbox/` : les liens de confirmation et de désinscription s'y trouvent. Pour un vrai envoi, passe à `'mailer' => 'smtp'` et renseigne le bloc `mail` (hôte, port, identifiants, adresse d'expédition).

### Déploiement sur hébergement mutualisé

1. Crée une base `utf8mb4` et un utilisateur dédié, puis importe `db/schema.sql`.
2. Pointe le domaine vers le dossier `public/` comme racine web (ou utilise la redirection `htdocs/.htaccess` si l'hébergeur l'impose), avec `src/`, `config/` et `storage/` hors de portée du web.
3. Sans accès à Composer sur le serveur : lance `composer install --no-dev --optimize-autoloader` en local, puis envoie tout le dossier `vendor/` par FTP et vérifie qu'il est complet (`vendor/autoload.php`) avant de passer en mode `smtp`.
4. Vérifie que la version PHP active du domaine est 8.4, et que `config/` et `storage/` répondent 403 ou 404 depuis le navigateur.

## Structure des fichiers

```
lumina-smart-mug/
├── public/                         # Racine web : seul dossier exposé
│   ├── index.html
│   ├── privacy.html
│   ├── subscribe.php
│   ├── confirm.php
│   ├── unsubscribe.php
│   ├── .htaccess
│   └── assets/
│       ├── css/
│       │   ├── styles.css
│       │   └── v3-overrides.css
│       └── js/form.js
├── src/
│   ├── bootstrap.php                # Autoload + fonctions communes
│   ├── services.php                 # Câblage des dépendances
│   ├── Database/
│   │   ├── Connection.php
│   │   └── DuplicateKeyException.php
│   ├── Mail/
│   │   ├── MailerInterface.php
│   │   ├── OutboxMailer.php
│   │   └── SmtpMailer.php
│   └── Subscription/
│       ├── EmailValidator.php
│       ├── SubscribeOutcome.php
│       ├── ConfirmOutcome.php
│       ├── UnsubscribeOutcome.php
│       ├── SubscriberStore.php
│       ├── SubscriberRepository.php
│       └── SubscriptionService.php
├── db/
│   └── schema.sql
├── config/
│   ├── config.example.php
│   └── .htaccess
├── storage/
│   └── .htaccess
├── composer.json
├── .gitignore
└── README.md
```

## Auteur

Ricardo Dovonou (DocariDR)

- GitHub : [github.com/DocariDR](https://github.com/DocariDR)
- LinkedIn : [linkedin.com/in/ricardo-dovonou](https://linkedin.com/in/ricardo-dovonou)
- X : [x.com/DocariDR](https://x.com/DocariDR)
- Bluesky : [bsky.app/profile/docaridr.bsky.social](https://bsky.app/profile/docaridr.bsky.social)
- Portfolio : [portfolio-docaridr.vercel.app](https://portfolio-docaridr.vercel.app)