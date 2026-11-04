# Lumina Smart Mug

![Status](https://img.shields.io/badge/version-V1.4.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL%2FMariaDB-4479A1?logo=mysql&logoColor=white)
![PHPMailer](https://img.shields.io/badge/PHPMailer-SMTP-777BB4)
![FedaPay](https://img.shields.io/badge/FedaPay-sandbox-7A00BF)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?logo=css3&logoColor=white)

Landing page du produit Lumina Smart Mug : newsletter avec double opt-in et désinscription en un clic, tunnel de précommande avec paiement FedaPay en mode test, pages légales. Né d'un projet freeCodeCamp (Responsive Web Design).

![Aperçu du projet](screenshot.png)

## Où en est le projet (V1.4.0)

- **Paiement FedaPay en mode test (sandbox)** : après validation, `create-order.php` crée une transaction FedaPay en XOF, récupère l'URL de paiement et redirige le client (303). L'identifiant de transaction est enregistré dans la commande. Parcours testé de bout en bout sur l'hébergement : création, redirection, paiement de test, retour sur le site.
- **Montant XOF figé** : la transaction utilise le `total_xof` enregistré avec la commande (USD, puis EUR au taux de la configuration, puis XOF à la parité fixe 655,957, en entiers, arrondi au supérieur), jamais un nouveau calcul.
- **Réessai possible** : un client qui ferme la page de paiement peut recommencer tout de suite. Sa tentative identique (même email, même quantité) en attente depuis moins de 10 minutes passe en `cancelled`, dans la même transaction SQL que la création de la nouvelle commande. Si le paiement ne peut pas démarrer, la commande passe en `failed`.
- **Redirection verrouillée** : seule une URL de paiement en HTTPS sur un domaine `.fedapay.com` est acceptée ; la clé secrète doit correspondre à l'environnement (`sk_sandbox_` ou `sk_live_`) ; `app_url` doit être en HTTPS pour l'adresse de retour.
- **Page de retour prudente** (`payment-return.php`) : elle ne tient aucun compte du statut présent dans l'URL, n'affiche la référence que si elle a le bon format et indique que le paiement n'est pas confirmé. Aucune donnée de carte ni de téléphone ne transite par le site.
- **Limite assumée** : une commande ne passe jamais en `paid` dans cette version. La version suivante lira le statut par un appel à l'API FedaPay au retour du client (l'hébergement gratuit bloque les webhooks entrants), après contrôle du montant.
- **Points à clarifier avant un usage réel** : en sandbox, seuls des moyens de paiement mobiles sont proposés (pas de carte bancaire), ce qui ne convient pas à des acheteurs américains ; FedaPay peut aussi ajouter des frais au montant payé selon les réglages du compte.
- **Acquis de la version précédente** : livraison aux États-Unis uniquement (50 États et District de Columbia, ZIP ou ZIP+4), prix recalculés côté serveur, jeton CSRF à usage unique, honeypot, erreurs de formulaire accessibles champ par champ, saisie conservée après une erreur, pages `terms.html` et `privacy.html`.
- **Limite assumée** : le site est une démonstration, aucune commande n'est livrée et aucun vrai paiement n'est pris.

## Ce que j'ai mis en pratique

- Intégration d'une API de paiement sans SDK : client cURL natif (`FedaPayClient`), appels JSON authentifiés, délais d'attente, redirections désactivées, erreurs transformées en exception dédiée et journalisées sans jamais écrire la clé secrète.
- Lecture défensive des réponses d'API : chemins explicites plutôt qu'une recherche récursive, pour ne pas prendre l'identifiant du client pour celui de la transaction.
- Ne jamais faire confiance au navigateur : montants recalculés côté serveur, statut de l'URL de retour ignoré, URL de paiement limitée au domaine du prestataire.
- Intégrité des données : annulation de l'ancienne tentative et création de la nouvelle dans une seule transaction SQL, avec `rollBack` en cas d'erreur, et contraintes `CHECK` en base.
- Arithmétique monétaire en entiers : centimes, taux mis à l'échelle, arrondi au supérieur, même formule en PHP et en JavaScript (`BigInt`) pour l'affichage en direct du total.
- Protection du formulaire : jeton CSRF à usage unique, honeypot, Post/Redirect/Get avec saisie et erreurs gardées en session et lues une seule fois.
- Validation qui remonte toutes les erreurs d'un coup (`OrderValidationException`), reliées aux champs pour les lecteurs d'écran.
- Séparation interface / implémentation (`OrderStore` / `OrderRepository`) derrière le service de commande.
- Cycle de vie d'un abonné avec deux jetons, dépendance Composer (PHPMailer) derrière `MailerInterface`, `Throwable` capturé à la frontière du service avec journalisation côté serveur.

## Démo

[Voir la démo en ligne](https://lumina-smart-mug.kesug.com/)

## Stack technique

- PHP 8.4
- MySQL 8 ou MariaDB 10.5+ (PDO, requêtes préparées)
- PHPMailer 7 (installé avec Composer)
- Extension cURL de PHP, pour l'API FedaPay (compte sandbox pour les essais)
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