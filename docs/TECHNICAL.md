# SUPMEAL — documentation technique

Cette documentation décrit l’état du code présent dans le dépôt. Le nom du
projet et plusieurs valeurs de configuration utilisent encore `4meal` ; le
format d’échange et l’interface utilisent également le nom SUPMEAL.

## Architecture

SUPMEAL est un monorepo composé de :

- `backend/` : API Laravel 12 en PHP, exposant les routes sous `/api` ;
- `frontend/` : SPA Vue 3 + TypeScript, compilée et servie en développement
  par Vite ;
- PostgreSQL 16 : base relationnelle ;
- Reverb : serveur WebSocket Laravel pour les événements temps réel ;
- Mailpit : SMTP et interface de consultation des courriels en environnement
  Docker ;
- Nginx : reverse proxy et point d’entrée HTTP.

En Docker, Nginx route `/api/` et `/up` vers Laravel, `/storage/` vers les
fichiers publics du backend, `/app/` vers Reverb et le reste vers Vite. Le
frontend appelle l’API avec `VITE_API_BASE_URL` et utilise Laravel Echo/Pusher
pour Reverb.

Le backend organise le métier par contrôleurs, Form Requests, modèles Eloquent,
Resources et services d’action. Les migrations constituent la définition
effective du modèle de données. Les réponses API utilisent une enveloppe
`success`, `data` et `meta` via `ApiResponse`.

## Prérequis

Pour une installation locale :

- PHP 8.2 minimum (l’image Docker utilise PHP 8.4) ;
- Composer 2 ;
- Node.js >= 20.19 (la CI et Docker utilisent Node 24) ;
- npm ;
- PostgreSQL 16 pour le backend ;
- Docker Engine et Docker Compose si la stack conteneurisée est utilisée.

## Variables d’environnement

### Backend (`backend/.env`)

Créer le fichier depuis `backend/.env.example`. Les variables principales sont
`APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`, `JWT_SECRET`,
`JWT_COOKIE_NAME`, `JWT_COOKIE_SECURE`, `JWT_COOKIE_SAME_SITE`,
`AUTH_EMAIL_VERIFICATION_URL`, `DB_CONNECTION`, `DB_HOST`, `DB_PORT`,
`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `SESSION_DRIVER`, `CACHE_STORE`,
`QUEUE_CONNECTION`, `BROADCAST_CONNECTION`, `FILESYSTEM_DISK`, `MAIL_*` et
`LOG_*`.

Les intégrations OAuth utilisent `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`,
`GOOGLE_REDIRECT_URI`, `GOOGLE_FRONTEND_URL`, ainsi que les équivalents
`MICROSOFT_*`. Les variables `AWS_*` configurent le disque S3, mais le disque
utilisé par défaut est local.

JWT utilise HS256, une durée par défaut de 3600 secondes (`JWT_TTL`) et un
cookie HttpOnly. Le middleware accepte aussi un header `Authorization: Bearer`.

### Docker (fichier `.env` à la racine)

Partir de `.env.docker.example`. Les variables de composition préfixées
`BACKEND_` alimentent la configuration Laravel ; les variables `FRONTEND_`
alimentent Vite ; `POSTGRES_*` configure PostgreSQL. Les paramètres Reverb sont
`REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`,
`REVERB_PORT`, `REVERB_SCHEME`, `REVERB_SERVER_HOST`,
`REVERB_SERVER_PORT` et `REVERB_ALLOWED_ORIGINS`.

`PROXY_PORT` expose Nginx, `MAILPIT_SMTP_PORT` et `MAILPIT_WEB_PORT` exposent
Mailpit. `BACKEND_JWT_SECRET` est prévu par l’exemple, mais la composition
injecte `JWT_SECRET` via la configuration de l’application ; en son absence,
l’entrypoint génère une valeur au démarrage. En production, fournir des
valeurs persistantes et privées pour `APP_KEY`, `JWT_SECRET`, les mots de passe
et les secrets OAuth/Reverb.

## Installation locale

```bash
docker compose --env-file .env.docker.example up -d postgres
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Dans un second terminal :

```bash
cd frontend
npm ci
npm run dev
```

Pour utiliser les commandes du `Makefile`, `make` exécute par défaut Composer
et npm dans les conteneurs. `HOST_COMPOSER=1` et `HOST_NPM=1` forcent les
installations locales.

## Déploiement Docker

```bash
cp .env.docker.example .env
# Définir au minimum une valeur propre à l’environnement pour REVERB_APP_SECRET
docker compose up -d
```

La stack normale comprend `backend`, `reverb`, `frontend`, `reverse-proxy`,
`postgres` et `mailpit`. L’entrypoint backend installe les dépendances si le
lockfile a changé, crée le lien `public/storage`, vérifie la configuration
Reverb et lance `php artisan serve` sur le port 8000.

Commandes utiles :

```bash
make init              # démarrage, attente du backend et migrations
make up                # démarrage en arrière-plan
make logs              # logs de la stack
make ps                # état des services
make config            # validation de Compose
make rebuild           # reconstruction et recréation des conteneurs
make migrate           # migrations
make down              # arrêt et suppression des conteneurs/réseaux
```

Le Compose fourni est orienté développement : le code source est monté dans
les conteneurs et le frontend est lancé par Vite en mode dev. Il ne fournit pas
une image frontend statique ni une configuration TLS.

## Tests et qualité

Backend :

```bash
cd backend
composer test
composer pint -- --test
composer analyse
```

Frontend :

```bash
cd frontend
npm ci
npm run lint
npm run typecheck
npm run test
npm run openapi:validate
```

Depuis la racine, les équivalents sont `make backend-test`, `make frontend-test`
et `make test`. `make ci` rejoue qualité backend, qualité frontend et validation
des deux fichiers Compose. La suite backend utilise PostgreSQL ; le Compose de
test (`docker-compose.test.yml`) isole la base et le volume de dépendances.

La CI GitHub Actions exécute Pint, PHPStan/Larastan et Pest côté backend,
ESLint, vue-tsc et Vitest côté frontend, puis `docker compose config`.

## Choix techniques

- Laravel/Eloquent et migrations pour l’API et la persistance relationnelle ;
- Vue 3, TypeScript, Vue Router et Pinia pour l’interface ;
- Vite pour le serveur de développement et le build frontend ;
- JWT signé en HS256, transportable par Bearer ou cookie HttpOnly ;
- Laravel Reverb + Echo/Pusher pour les messages et notifications temps réel ;
- PostgreSQL avec index de recherche et extension `pg_trgm` prévus par les
  migrations ;
- Pest/PHPUnit, Vitest, ESLint, vue-tsc, Pint et PHPStan/Larastan pour les
  contrôles automatisés ;
- JSON Schema versionné pour SUPMEAL et format CSV plat séparé du JSON.

## Structure du dépôt

```text
backend/
  app/                 contrôleurs, modèles, services, policies, resources
  config/              configuration Laravel, JWT, Reverb, stockage
  database/migrations/ schéma et index PostgreSQL
  routes/              API, web, broadcasting, console
  tests/               tests unitaires et fonctionnels
frontend/
  src/components/      composants Vue
  src/views/           écrans et tests associés
  src/stores/          état Pinia
  src/utils/           appels API et logique d’import/export
  src/realtime/        connexion Echo/Reverb
docker/                Dockerfile backend, entrypoint PHP, configuration Nginx
docs/openapi/          contrat OpenAPI transversal
docs/supmeal/          JSON Schema SUPMEAL 1.0 et exemples
MCD_*/                documents MCD présents dans le dépôt
```

## Modèle de données

Les entités principales sont `User`, `Recipe`, `RecipeIngredient`, `RecipeStep`,
`Tag`, `Cookbook`, `CookbookMember`, `CookbookInvitation`, `PlannedMeal` et
`SavedSearch`.

Les fonctions sociales et de suivi sont portées par `RecipeFavorite`,
`RecipeRating`, `RecipeComment`, `RecipeCommentReaction`, `RecipeAudit`,
`CookbookMessage`, `CookbookMessageReaction`, `Notification` et
`NotificationPreference`.

L’authentification complémentaire utilise `OAuthAccount`,
`email_verification_tokens`, `password_reset_tokens` et
`two_factor_challenges`. Laravel ajoute également les tables `sessions`,
`cache` et `jobs` lorsque les drivers correspondants sont configurés.

Relations métier observables : un utilisateur possède ses recettes, repas
planifiés, favoris, recherches sauvegardées et notifications ; une recette
contient des ingrédients et étapes, peut avoir des tags, commentaires,
notations et audits ; les recettes peuvent être associées à plusieurs
cookbooks ; un cookbook possède des membres, invitations et messages.

Les rôles de cookbook sont `owner`, `editor`, `reader`, `commenter` et
`moderator`, avec une matrice de permissions codée dans
`backend/app/Support/CookbookPermissions.php`.

## Sécurité

- validation des entrées par Form Requests ;
- mots de passe vérifiés avec le mécanisme de hash Laravel ;
- JWT vérifié, enregistré et révocable via un registre de tokens actifs ;
- cookie JWT `HttpOnly`, `SameSite` configurable et `Secure` activable ;
- vérification d’adresse e-mail sur les routes métier concernées ;
- double authentification par code temporaire, haché et limité en tentatives ;
- OAuth Google et Microsoft avec état temporaire conservé en cache ;
- policies et matrice de permissions pour les cookbooks ;
- limitation de débit appliquée aux demandes d’e-mail de réinitialisation et
  de reset de mot de passe ;
- validation MIME des images JPEG, PNG et WebP dans les requêtes concernées ;
- CORS, TLS, rotation des secrets, sauvegardes et gestionnaire de secrets
  externe ne sont pas configurés dans ce dépôt.

## Stockage

Le disque Laravel par défaut est `local` (`storage/app/private`). Les images
créées par les utilisateurs sont explicitement écrites sur le disque `public`
(`storage/app/public`) : recettes dans `recipes/`, cookbooks dans `cookbooks/`
et avatars dans `avatars/`. Le lien `public/storage` est créé par l’entrypoint.

Docker persiste `backend_storage` et sépare les images de cookbooks dans le
volume `cookbook_images`. PostgreSQL est persisté dans `postgres_data`. Un
disque S3 est configuré comme option Laravel, mais n’est pas sélectionné par
défaut et aucun basculement automatique n’est implémenté.

## Import et export

Routes protégées : `POST /api/import/preview`, `POST /api/import`, variantes
`/csv` et `/mealie`, puis `GET /api/export` et `GET /api/export/csv`.

Le JSON SUPMEAL est versionné en `format: SUPMEAL`, version `1.0.0`, avec des
recettes, cookbooks, ingrédients, étapes et tags. Il est validé par
`docs/supmeal/supmeal-1.0.schema.json`; des exemples valides et invalides sont
fournis dans `docs/supmeal/examples/`. L’import JSON est transactionnel dans
les services d’import et rejette les références invalides.

Le CSV est UTF-8, séparé par des virgules, compatible RFC 4180, version 1,
avec des lignes `recipe`, `ingredient`, `step` et `tag`. Il n’exporte pas les
identifiants internes, images, cookbooks ni favoris. L’import CSV accepte un
fichier multipart `.csv` de 10 Mo maximum et ne conserve pas les données d’un
import partiellement invalide. L’adaptateur Mealie importe le format représenté
par le fixture `backend/tests/Fixtures/mealie/carbonara.json`.

Les images ne sont pas embarquées dans ces exports : les champs d’URL peuvent
être présents dans le schéma, mais l’exporteur SUPMEAL actuel les émet à `null`.

## Limitations connues

- `docs/openapi/openapi.yaml` décrit les schémas et conventions transversales,
  mais aucun endpoint métier n’est renseigné dans `paths` ; il ne remplace
  donc pas `backend/routes/api.php` comme inventaire d’exécution.
- Le Compose principal lance Vite en développement et `php artisan serve` ; ce
  n’est pas une chaîne de production durcie.
- Les paramètres par défaut Docker contiennent des valeurs de développement
  (`change_me`, debug actif et secrets d’exemple) qui doivent être remplacées.
- Le driver de queue par défaut de Docker est `sync` : aucune exécution
  asynchrone dédiée n’est configurée dans la stack.
- Les fichiers publics sont servis via `/storage/`; le dépôt ne fournit pas de
  politique de rétention, de quota ou de nettoyage indépendant.
- Aucun service de sauvegarde/restauration PostgreSQL ou de migration en ligne
  n’est fourni par le dépôt.

## Maintenance

1. Conserver `composer.lock` et `frontend/package-lock.json` synchronisés avec
   les manifestes.
2. Exécuter `make ci` avant toute livraison.
3. Appliquer les migrations avec `make migrate` et sauvegarder PostgreSQL et
   les volumes de stockage avant une opération destructive.
4. Maintenir `APP_KEY`, `JWT_SECRET`, les secrets Reverb/OAuth et les mots de
   passe hors du dépôt ; renouveler les secrets si l’environnement est exposé.
5. Vérifier les volumes `postgres_data`, `backend_storage` et
   `cookbook_images` dans la supervision de l’hôte.
6. Mettre à jour les images Docker et dépendances, puis rejouer la suite
   backend/frontend et la validation Compose.

