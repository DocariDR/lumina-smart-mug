# Lumina Smart Mug

![Status](https://img.shields.io/badge/version-V1-blue)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?logo=css3&logoColor=white)

Landing page du produit Lumina Smart Mug : formulaire d'inscription à la newsletter, présentation des fonctionnalités et bloc de précommande. Né d'un projet freeCodeCamp (Responsive Web Design), ce dépôt en est la suite : une version destinée à un vrai hébergement, avec un vrai back-end PHP.

![Aperçu du projet](screenshot.png)

## Où en est le projet (V1)

Cette première version assainit la base héritée du projet freeCodeCamp avant d'ajouter de la logique métier :

- Le formulaire d'inscription envoie désormais vers `public/subscribe.php` (en POST), au lieu d'un service externe ; l'adresse email ne traîne plus dans l'URL.
- `subscribe.php` valide l'adresse côté serveur (format et longueur RFC 5321) avant de rediriger avec un message. **Aucune inscription n'est encore enregistrée** : la base de données et le double opt-in arrivent en V2.
- Le bouton "Pre-order Now" est désactivé, avec la mention "bientôt disponible" ; il s'activera en V4 (précommande).
- Séparation `public/` (seul dossier exposé au web) / `src/` (code applicatif) / `tests/`, en-têtes de sécurité (CSP, X-Frame-Options...) via `.htaccess`.

## Ce que j'ai mis en pratique

- Structure d'un projet PHP en couches : point d'entrée public (`public/`) séparé du code applicatif (`src/`), autoload PSR-4 maison via `spl_autoload_register`.
- Validation serveur d'un formulaire (format et longueur d'un email selon la RFC 5321), indépendamment de la validation native du navigateur.
- Gestion du cycle requête/réponse en PHP : code HTTP explicite sur méthode refusée (405), redirection post-traitement (303) pour éviter la resoumission du formulaire.
- Accessibilité : lien d'évitement ("skip to content"), label masqué sur le champ email, zone de message `aria-live` pour annoncer le résultat de l'envoi, focus visible personnalisé.
- Durcissement HTTP de base via `.htaccess` : en-têtes de sécurité (CSP, X-Frame-Options, Referrer-Policy, Permissions-Policy).
- Variables CSS pour centraliser la couleur de marque, et media query pour l'affichage mobile.
- Petit script JavaScript sans dépendance, qui lit un paramètre d'URL pour afficher un message d'état après redirection.

## Démo

[Voir la démo en ligne](https://lumina-smart-mug.kesug.com/)

## Stack technique

- PHP 8.4 (aucune dépendance de production ; PHPUnit en développement uniquement)
- HTML5 / CSS3
- JavaScript (vanilla, sans dépendance)
- Apache (`.htaccess`) pour les en-têtes de sécurité

## Installation locale

```bash
git clone https://github.com/DocariDR/lumina-smart-mug.git
cd lumina-smart-mug
php -S localhost:8000 -t public
```

Puis ouvre `http://localhost:8000` dans ton navigateur.

### Tests (optionnel)

```bash
composer install
vendor/bin/phpunit
```

### Déploiement sur hébergement mutualisé

1. Pointe le domaine vers le dossier `public/` comme racine web. Si l'hébergeur impose `public_html/`, dépose le contenu de `public/` dans `public_html/` et place `src/` (et `tests/` s'il existe) un niveau au-dessus, hors de portée du web.
2. Vérifie dans le panneau de l'hébergeur que la version PHP active du domaine est bien 8.4.
3. Teste l'envoi du formulaire : un envoi valide doit afficher "Sign-ups open very soon".

## Structure des fichiers

```
lumina-smart-mug/
├── public/                    # Racine web : seul dossier exposé
│   ├── index.html
│   ├── subscribe.php
│   ├── .htaccess
│   └── assets/
│       ├── css/styles.css
│       └── js/form.js
├── src/
│   ├── bootstrap.php          # Autoload + fonctions communes
│   └── Subscription/
│       └── EmailValidator.php
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
