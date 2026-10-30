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

- Arithmétique monétaire en entiers : centimes, taux mis à l'échelle (millionièmes et millièmes), arrondi au supérieur, même formule en PHP et en JavaScript (`BigInt`). Un bug d'échelle (montant XOF 1000 fois trop grand) repéré en relecture est maintenant couvert par des tests de non-régression.
- Protection contre le rejeu : jeton CSRF à usage unique et détection des commandes en double.
- Post/Redirect/Get avec messages et saisie gardés en session, lus une seule fois.
- Validation qui remonte toutes les erreurs d'un coup (`OrderValidationException` porte la liste des champs), reliées aux champs pour les lecteurs d'écran.
- `OrderStore` (interface) pour tester `OrderService` sans base de données : le script `tests/OrderTest.php` couvre la conversion, la validation et les doublons.
- Cycle de vie d'un abonné avec deux jetons, dépendance Composer (PHPMailer) derrière `MailerInterface`, `Throwable` capturé à la frontière du service avec journalisation côté serveur.

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

Renseigne dans `config/config.php` tes identifiants de base de données locale, un `ip_salt` d'au moins 32 caractères (par exemple `openssl rand -hex 32`), le bloc `product` (prix en centimes USD, quantité maximale) et le bloc `fx` (taux USD vers EUR en millionièmes, avec sa date), puis importe le schéma :

```bash
mysql -u UTILISATEUR -p NOM_DE_BASE < db/schema.sql
php -S localhost:8000 -t public
```

Ouvre ensuite `http://localhost:8000`. Avec `'mailer' => 'outbox'`, les emails atterrissent dans `storage/outbox/` : les liens de confirmation et de désinscription s'y trouvent. Pour un vrai envoi, passe à `'mailer' => 'smtp'` et renseigne le bloc `mail` (hôte, port, identifiants, adresse d'expédition). La précommande est disponible sur `http://localhost:8000/order.php`.

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
│   ├── order.php                   # Formulaire de précommande
│   ├── create-order.php            # Validation et enregistrement
│   ├── terms.html
│   ├── privacy.html
│   ├── subscribe.php
│   ├── confirm.php
│   ├── unsubscribe.php
│   ├── .htaccess
│   └── assets/
│       ├── css/
│       │   ├── styles.css
│       │   ├── v3-overrides.css
│       │   └── order.css
│       └── js/
│           ├── form.js
│           └── order.js            # Total USD et XOF en direct
├── src/
│   ├── bootstrap.php                # Autoload, session sécurisée, fonctions communes
│   ├── services.php                 # Câblage newsletter
│   ├── order-service.php            # Câblage précommande
│   ├── Database/
│   │   ├── Connection.php
│   │   └── DuplicateKeyException.php
│   ├── Mail/
│   │   ├── MailerInterface.php
│   │   ├── OutboxMailer.php
│   │   └── SmtpMailer.php
│   ├── Order/
│   │   ├── CurrencyConverter.php
│   │   ├── OrderStore.php
│   │   ├── OrderRepository.php
│   │   ├── OrderService.php
│   │   ├── OrderValidationException.php
│   │   ├── OrderDuplicateException.php
│   │   └── UsStates.php
│   └── Subscription/
│       ├── EmailValidator.php
│       ├── SubscribeOutcome.php
│       ├── ConfirmOutcome.php
│       ├── UnsubscribeOutcome.php
│       ├── SubscriberStore.php
│       ├── SubscriberRepository.php
│       └── SubscriptionService.php
├── db/
│   ├── schema.sql
│   └── migrations/
├── tests/
│   └── OrderTest.php
├── config/
│   ├── config.example.php
│   └── .htaccess
├── storage/
│   └── .htaccess
├── composer.json
├── composer.lock
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