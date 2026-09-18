# Voisin.

**Voisin.** est un prototype de réseau social développé avec **Symfony 7.4**, réalisé dans le cadre d'un examen "Framework Back End". Le concept : un réseau social centré sur l'authenticité et les liens humains, permettant aux membres de créer un compte, publier du contenu (public ou réservé aux amis), tisser des liens d'amitié et interagir (likes, commentaires) autour d'un fil d'actualité.

Ce document décrit l'architecture technique du projet, les choix d'implémentation et la manière de le lancer en local.

---

## Sommaire

- [Stack technique](#stack-technique)
- [Fonctionnalités](#fonctionnalités)
- [Architecture](#architecture)
- [Modèle de données](#modèle-de-données)
- [Sécurité & authentification](#sécurité--authentification)
- [Upload d'images](#upload-dimages)
- [Routing](#routing)
- [Installation](#installation)
- [Commandes utiles](#commandes-utiles)
- [Limites connues / pistes d'amélioration](#limites-connues--pistes-damélioration)

---

## Stack technique

| Composant | Détail |
|---|---|
| Framework | Symfony **7.4** (architecture MVC) |
| PHP | ≥ 8.2 |
| ORM | Doctrine ORM 3.x + Doctrine Migrations |
| Base de données | MySQL 8.4 (configurable via `DATABASE_URL`) |
| Templates | Twig |
| Authentification | Symfony Security (authenticator personnalisé, sans bundle tiers) |
| Assets | Symfony AssetMapper (pas de Webpack/Node build) + Stimulus/Turbo (UX) |
| Icônes | Symfony UX Icons |
| Tests | PHPUnit 12 (squelette `phpunit_dist.xml`, à compléter) |

Le projet n'utilise **aucune API REST/JSON** ni SPA front : c'est une application Symfony "classique" côté serveur, rendue en Twig, avec formulaires natifs Symfony Form et rechargement de page (Turbo est présent via `symfony/ux-turbo` mais l'essentiel du parcours repose sur des requêtes HTTP standard POST/redirect/GET).

---

## Fonctionnalités

Conformément au cahier des charges (`cahier_des_charges.txt`) :

### Partie publique (non authentifiée)
- **Page d'accueil** (`AppController::index`) : accroche, aperçu des 6 dernières publications publiques, CTA "Se connecter" / "Créer un compte".
- **Inscription** (`RegistrationController::register`) : pseudonyme, email, mot de passe, photo de profil obligatoire.
- **Connexion / Déconnexion** via le firewall Symfony Security.

### Partie privée (membres authentifiés — `ROLE_USER`)
- **Fil d'actualité** (`PostController::feed`) : publications publiques + publications "amis uniquement" des amis de l'utilisateur, triées par date décroissante, avec un filtre `?filtre=all|public|friends`.
- **Publications** : création, modification, suppression (`PostController::new/edit/delete`), avec choix de visibilité (`Publique` / `Amis uniquement`) et image optionnelle. Seul l'auteur peut modifier/supprimer sa publication (vérifié en contrôleur).
- **Profil utilisateur** (`UserController::profile`) : pseudo, photo, bio, date d'inscription, liste d'amis, publications visibles selon la relation (propriétaire / ami / visiteur).
- **Édition de profil** (`UserController::edit`) : pseudo, bio, remplacement de la photo.
- **Relations d'amitié** :
  - envoi de demande depuis un profil (`sendFriendRequest`), protégé contre l'auto-ajout et les doublons ;
  - consultation des demandes reçues en attente (`friendRequests`) ;
  - acceptation / refus (`acceptFriendRequest` / `refuseFriendRequest`) ;
  - liste d'amis avec statut en ligne (`friendList`) ;
  - relation symétrique modélisée par une seule entité `FriendRequest` avec un statut (`PENDING`/`ACCEPTED`/`REFUSED`), interrogée dans les deux sens (`sender`/`receiver`) via `User::getFriends()`.

### Bonus implémentés
- **Likes** : toggle like/unlike sur une publication (`PostController::like`), relation `ManyToMany` `User` ↔ `Post`.
- **Commentaires** : ajout et suppression (`CommentController`), suppression autorisée pour l'auteur du commentaire **ou** l'auteur de la publication.

---

## Architecture

Architecture MVC standard Symfony, organisée par responsabilité :

```
src/
├── Controller/       # AppController, SecurityController, RegistrationController,
│                      #   PostController, UserController, CommentController
├── Entity/            # User, Post, Comment, FriendRequest, Likes
├── Repository/        # UserRepository, PostRepository, CommentRepository,
│                      #   FriendRequestRepository, LikesRepository
├── Form/               # RegistrationFormType, ProfileType, PostType, CommentType
├── Security/          # AppAuthenticator
└── Service/           # ImageUploader
templates/
├── app/               # index (accueil publique)
├── security/          # login
├── registration/       # register
├── post/               # feed, view, new, edit
├── user/               # profile, edit, friend_requests, friend_list
└── base.html.twig, _header.html.twig
```

Points d'architecture notables (au-delà de ce que Symfony fait "gratuitement") :

- **Logique métier déportée dans les repositories.** `PostRepository::findForFeed()` et `findVisibleForProfile()` encapsulent en QueryBuilder DQL la logique de visibilité (publique / amis) plutôt que de la faire en PHP après un `findAll()` — évite de charger des posts non visibles et centralise la règle métier.
- **Vérification d'autorisation en deux niveaux** : `#[IsGranted('ROLE_USER')]` au niveau route (authentification) + vérification manuelle de propriété en contrôleur (`$post->getUserId()?->getId() !== $user->getId()`) pour l'autorisation fine (un membre ne peut agir que sur ses propres ressources). Complétée par des jetons **CSRF** dédiés sur chaque action de mutation sensible (like, suppression, réponse à une demande d'ami) : `friend-request-{id}`, `friend-response-{id}`, `delete-post-{id}`, `delete-comment-{id}`.
- **Service `ImageUploader` réutilisable et paramétré par injection de dépendances.** Un seul service (`App\Service\ImageUploader`) instancié deux fois via `services.yaml` avec des destinations différentes (`user_image_uploader` → `public/images/user`, `post_image_uploader` → `public/images/post`), injectés dans les contrôleurs via l'attribut `#[Autowire(service: '...')]`. Gère l'upload avec nommage unique (`uniqid()`), le remplacement (upload puis suppression de l'ancien fichier) et la suppression physique.
- **`User::getFriends()` / `isFriendWith()`** : la relation d'amitié n'est pas dupliquée en base ; une seule ligne `FriendRequest` avec statut `ACCEPTED` sert de preuve du lien dans les deux sens, et ces méthodes sur l'entité parcourent les deux collections (`friendRequests` envoyées et `friendRequestsReceived`) pour reconstituer la liste d'amis et vérifier la relation — évite la duplication d'écriture (deux lignes A→B et B→A) au prix d'un parcours en mémoire, acceptable à l'échelle du prototype.
- **Formulaire d'édition de publication dynamique** : `PostController::edit()` retire puis ré-ajoute le champ `imageFile` du `PostType` pour le rendre facultatif et le libeller différemment ("Remplacer l'image") en contexte d'édition, plutôt que de dupliquer un `FormType`.

---

## Modèle de données

5 entités Doctrine :

- **`User`** (implémente `UserInterface`, `PasswordAuthenticatedUserInterface`) : `id`, `username` (unique, regex anti-caractères spéciaux), `email` (unique, identifiant de connexion), `password` (haché), `roles`, `image`, `bio`, `online`, `datetime` (inscription). Relations : `OneToMany` vers `Post` et `Comment`, `OneToMany` (x2) vers `FriendRequest` (envoyées / reçues), `ManyToMany` vers `Post` (`likedPosts`).
- **`Post`** : `id`, `user_id` (auteur), `text`, `image` (optionnelle), `visibility` (bool : `true` = publique), `datetime_creation`, `datetime_modification`. Relations : `ManyToMany` inverse vers `User` (`likedBy`), `OneToMany` vers `Comment`.
- **`Comment`** : `id`, `text`, `user_id` (auteur), `post`, `datetime`.
- **`FriendRequest`** : `id`, `status` (constantes `STATUS_PENDING` / `STATUS_ACCEPTED` / `STATUS_REFUSED`), `datetime`, `sender`, `receiver`. Une seule entité sert à la fois de demande et de relation d'amitié une fois acceptée.
- **`Likes`** : entité présente mais non utilisée (le like est en réalité géré par la relation `ManyToMany` `User::likedPosts` / `Post::likedBy`, pas par cette entité dédiée) — probablement un vestige d'une première approche, à retirer ou à activer si un historique des likes (date, etc.) devient nécessaire.

Les migrations Doctrine (`Version20260914130722`, `Version20260914142750`, `Version20260914170506`) tracent l'évolution du schéma ; utiliser `doctrine:migrations:migrate` plutôt que `schema:update` pour reconstruire la base.

---

## Sécurité & authentification

- **Authenticator personnalisé** (`App\Security\AppAuthenticator`, `AbstractLoginFormAuthenticator`) plutôt que la configuration déclarative `form_login` : construit un `Passport` avec `UserBadge` (identifiant = email), `PasswordCredentials` et un `CsrfTokenBadge`, gère la redirection post-connexion via `TargetPathTrait` (retour à la page demandée avant redirection vers le login).
- **Provider** : `app_user_provider` charge les `User` par `email` (propriété d'identifiant de connexion, distincte du `username` affiché).
- **Access control** (`security.yaml`) : règles par préfixe d'URL (`^/user`, `^/amis`, etc. → `ROLE_USER`), complétées par `#[IsGranted('ROLE_USER')]` sur chaque route sensible et par des vérifications de propriété en contrôleur (voir ci-dessus). Le fichier contient encore quelques règles héritées d'un squelette Symfony (`/article/*`, `/categorie`) qui ne correspondent à aucune route réelle du projet — à nettoyer.
- **Mot de passe** : haché via `UserPasswordHasherInterface` (algorithme `auto`), avec contrainte `PasswordStrength` sur l'entité et `Length(min: 6)` côté formulaire d'inscription.
- **Anti-CSRF** : Symfony CSRF activé (`csrf.yaml`) ; jetons dédiés par action pour toute mutation déclenchée par un simple bouton POST hors formulaire Symfony Form (like, accepter/refuser une demande, supprimer un post/commentaire).
- **Confidentialité du mot de passe en session** : `User::__serialize()` hache le mot de passe (CRC32C) avant sérialisation en session, pour éviter qu'un hash bcrypt/argon2 complet ne transite en clair dans les données de session (fonctionnalité Symfony 7.3+).

---

## Upload d'images

Toutes les images (photo de profil, image de publication) passent par le service unique `App\Service\ImageUploader`, instancié deux fois avec des répertoires différents :

```yaml
user_image_uploader:
    class: App\Service\ImageUploader
    arguments: { $destination: '%kernel.project_dir%/public/images/user' }

post_image_uploader:
    class: App\Service\ImageUploader
    arguments: { $destination: '%kernel.project_dir%/public/images/post' }
```

Validation en amont par contraintes Symfony Validator/Form (`Assert\Image` sur l'entité `User`, `Constraints\File` sur les `FormType`) : taille max, extensions/MIME autorisés (jpg/png/webp, + gif pour les posts).

---

## Routing

Toutes les routes sont déclarées par attributs PHP `#[Route]` sur les contrôleurs (import unique dans `routes.yaml` via `routing.controllers`). Principales routes :

| Route | Nom | Méthode(s) |
|---|---|---|
| `/` | `app_index` | GET |
| `/login`, `/logout` | `app_login`, `app_logout` | GET |
| `/register` | `app_register` | GET/POST |
| `/fil-actualite` | `app_feed` | GET |
| `/publication/nouvelle` | `app_post_new` | GET/POST |
| `/publication/{id}` | `app_post_view` | GET |
| `/publication/{id}/modifier` | `app_post_edit` | GET/POST |
| `/publication/{id}/supprimer` | `app_post_delete` | POST |
| `/publication/{id}/like` | `app_post_like` | POST |
| `/publication/{id}/commentaire` | `app_comment_new` | POST |
| `/commentaire/{id}/supprimer` | `app_comment_delete` | POST |
| `/profil/{id}` | `app_user_profile` | GET |
| `/profil/{id}/modifier` | `app_user_edit` | GET/POST |
| `/amis` | `app_friend_list` | GET |
| `/amis/demandes` | `app_friend_requests` | GET |
| `/amis/demander/{id}` | `app_friend_request_send` | POST |
| `/amis/demandes/{id}/accepter` | `app_friend_request_accept` | POST |
| `/amis/demandes/{id}/refuser` | `app_friend_request_refuse` | POST |

---

## Installation

Prérequis : PHP ≥ 8.2, Composer, MySQL (ou adapter `DATABASE_URL`), Symfony CLI (optionnel).

```bash
# 1. Cloner et installer les dépendances
git clone <url-du-repo>
cd voisin
composer install

# 2. Configurer la base de données (.env.local)
DATABASE_URL="mysql://root:@127.0.0.1:3306/voisin?serverVersion=8.4.3&charset=utf8mb4"

# 3. Créer la base et jouer les migrations
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# 4. Lancer le serveur
symfony server:start
# ou
php -S 127.0.0.1:8000 -t public/
```

Un export SQL de la base (attendu par le cahier des charges pour le rendu) peut être généré avec :

```bash
mysqldump -u root voisin > voisin.sql
```

Penser à exclure `vendor/` et `node_modules/` du dépôt (voir `.gitignore`) avant tout push, conformément à la consigne.

---

## Commandes utiles

```bash
php bin/console debug:router              # lister toutes les routes
php bin/console doctrine:schema:validate   # valider la cohérence du mapping
php bin/console make:migration             # générer une migration après modif d'entité
php bin/console doctrine:migrations:migrate
php bin/console cache:clear
```

---

## Limites connues / pistes d'amélioration

- L'entité `Likes` est inutilisée (les likes passent par la relation `ManyToMany` directe) — à supprimer ou à exploiter.
- `security.yaml` conserve des règles `access_control` issues d'un squelette de départ (`/article/*`, `/categorie`) sans route correspondante dans le projet.
- Pas de suite de tests PHPUnit implémentée (le squelette `phpunit_dist.xml` est en place mais `tests/` est à écrire).
- Le CSS et la mise en page sont volontairement secondaires, conformément à la consigne du cahier des charges ("le CSS n'est pas une priorité sur cet exercice").