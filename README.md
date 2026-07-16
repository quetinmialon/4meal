# 4meal

Structure du monorepo :

- `backend/` : API Laravel
- `frontend/` : application Vue 3 + TypeScript
- `docker/` : configuration Docker et Nginx
- `docs/` : documentation projet

## CI

Le workflow GitHub Actions est défini dans [`.github/workflows/ci.yml`](./.github/workflows/ci.yml).

Il exécute trois jobs distincts, sans secret GitHub :

- `backend` : `composer pint`, `composer analyse`, `composer test`
- `frontend` : `npm run lint`, `npm run typecheck`, `npm run test`
- `docker-compose` : validation de `docker-compose.yml` via `docker compose config`

La CI backend prépare un environnement Laravel minimal sans dépendance externe :

- copie de `backend/.env.example` vers `backend/.env`
- génération locale de `APP_KEY`
- surcharge en mémoire de `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `CACHE_STORE=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`

## Variables d'environnement attendues

### Backend local

Le backend lit ses variables depuis `backend/.env`, généralement initialisé à partir de `backend/.env.example`.

Variables principales :

| Variable | Rôle | Valeur type |
| --- | --- | --- |
| `APP_NAME` | nom applicatif Laravel | `Laravel` |
| `APP_ENV` | environnement d'exécution | `local` |
| `APP_KEY` | clé d'application Laravel | générée via `php artisan key:generate` |
| `APP_DEBUG` | mode debug | `true` |
| `APP_URL` | URL publique du backend | `http://localhost` |
| `DB_CONNECTION` | driver de base de données | `sqlite`, `pgsql`, `mysql` |
| `DB_DATABASE` | nom ou chemin de base | `database/database.sqlite`, `:memory:`, ou nom PostgreSQL |
| `DB_HOST` / `DB_PORT` | hôte et port BDD | requis hors SQLite |
| `DB_USERNAME` / `DB_PASSWORD` | identifiants BDD | requis hors SQLite |
| `SESSION_DRIVER` | stockage des sessions | `database`, `file`, `array` |
| `CACHE_STORE` | backend de cache | `database`, `file`, `array` |
| `QUEUE_CONNECTION` | backend de queue | `database`, `sync` |
| `MAIL_MAILER` | transport mail | `log`, `array`, `smtp` |

Les variables AWS, Redis et services tiers présentes dans `backend/.env.example` sont optionnelles tant que ces intégrations ne sont pas activées.

### Frontend local

Le frontend ne dépend pas d'un secret. Aucune variable personnalisée n'est requise pour lancer `lint`, `typecheck` ou `Vitest`.

### Docker Compose

Le fichier `docker-compose.yml` lit les variables racine depuis un `.env` à la racine du dépôt. Le point de départ prévu est [`.env.docker.example`](./.env.docker.example).

| Variable | Rôle | Valeur par défaut |
| --- | --- | --- |
| `PROXY_PORT` | port HTTP exposé par Nginx | `8080` |
| `BACKEND_APP_NAME` | nom applicatif backend | `4meal Backend` |
| `BACKEND_APP_ENV` | environnement Laravel | `local` |
| `BACKEND_APP_KEY` | clé Laravel injectée au conteneur backend | vide |
| `BACKEND_APP_DEBUG` | mode debug Laravel | `true` |
| `BACKEND_APP_URL` | URL backend exposée | `http://localhost:8080` |
| `BACKEND_SESSION_DRIVER` | driver de session backend | `file` |
| `BACKEND_CACHE_STORE` | driver de cache backend | `file` |
| `BACKEND_QUEUE_CONNECTION` | driver de queue backend | `sync` |
| `BACKEND_LOG_CHANNEL` | canal de logs Laravel | `stack` |
| `BACKEND_LOG_LEVEL` | niveau de logs Laravel | `debug` |
| `FRONTEND_API_BASE_URL` | base URL API côté frontend | `/api` |
| `POSTGRES_PORT` | port PostgreSQL exposé vers l'hôte | `5434` |
| `POSTGRES_DB` | base PostgreSQL | `4meal` |
| `POSTGRES_USER` | utilisateur PostgreSQL | `4meal` |
| `POSTGRES_PASSWORD` | mot de passe PostgreSQL | `change_me` |

La validation CI de `docker-compose.yml` n'attend aucun secret : toutes les interpolations utilisées dans le fichier ont une valeur par défaut.

## Contrat OpenAPI

La structure initiale du contrat OpenAPI est définie dans [`docs/openapi/openapi.yaml`](./docs/openapi/openapi.yaml).

Elle documente uniquement les conventions transverses déjà en place :

- schéma racine OpenAPI ;
- authentification Bearer JWT ;
- schémas d'erreurs communs ;
- conventions de pagination ;
- corrélation via `X-Correlation-ID`.

Aucun endpoint métier n'est encore décrit dans `paths`.

Commande de validation du contrat :

```bash
npm --prefix frontend run openapi:validate
```

## Démarrage local

### Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

### Frontend

```bash
cd frontend
npm install
npm run dev
```

### Docker Compose

```bash
cp .env.docker.example .env
docker compose up --build
```

Points d'entrée HTTP :

- `http://localhost:8080/` : frontend via reverse proxy
- `http://localhost:8080/api/` : API Laravel via reverse proxy
- `http://localhost:8080/storage/` : fichiers publics backend
