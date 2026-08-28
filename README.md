# 4meal

Structure du monorepo :

- `backend/` : API Laravel
- `frontend/` : application Vue 3 + TypeScript
- `docker/` : configuration Docker et Nginx
- `docs/` : documentation projet

Les parcours critiques E2E et leur exécution locale/CI sont documentés dans [`docs/e2e.md`](./docs/e2e.md).

## Workflow migration et demonstration

Le workflow recommande pour obtenir une instance locale fonctionnelle est le
suivant :

```bash
cp .env.docker.example .env
make init

make migrate

make seed
```

`make migrate` est la commande a utiliser lorsque la base contient deja des
donnees : elle applique uniquement les migrations qui n'ont pas encore ete
executees. `make seed` appelle le `DatabaseSeeder`, qui delegue au
[`DemoSeeder`](./backend/database/seeders/DemoSeeder.php).

Pour repartir d'une base de demonstration propre, utiliser explicitement :

```bash
make fresh-seed
```

Cette commande est equivalente a `php artisan migrate:fresh --seed --force` et
supprime toutes les tables avant de les recreer. Elle est reservee au
developpement et aux environnements de demonstration ; ne pas l'executer sur
une base contenant des donnees a conserver.

### Comptes de demonstration

`DemoSeeder` cree notamment les comptes suivants. Ils utilisent tous le mot de
passe `supmeal-demo-only-change-me` par defaut. Pour le remplacer, definir
`SUPMEAL_DEMO_PASSWORD` dans `backend/.env` avant le seed. Ces identifiants sont
strictement reserves au local et ne doivent jamais etre utilises en production.

| Compte | Email | Role principal dans la demonstration |
| --- | --- | --- |
| Demo Owner | `demo.owner@supmeal.test` | proprietaire des carnets actifs |
| Demo Editor | `demo.editor@supmeal.test` | editeur d'un carnet partage |
| Demo Commenter | `demo.commenter@supmeal.test` | commentaires et reactions |
| Demo Reader | `demo.reader@supmeal.test` | lecture seule |
| Demo Empty | `demo.empty@supmeal.test` | utilisateur sans activite, utile pour tester les etats vides |

Le seeder ajoute aussi quinze utilisateurs secondaires, des carnets, recettes,
tags, favoris, notes, commentaires avec reponses, recherches sauvegardees,
invitations acceptees/refusees/en attente, comptes OAuth simules,
notifications, preferences, historique des recettes et repas planifies
(dont des recurrences).

### Scenario de demonstration

Apres `make fresh-seed`, ouvrir `http://localhost:8080/` et suivre ce parcours
avec `demo.owner@supmeal.test` :

1. Se connecter et parcourir le tableau de bord, les recettes et la recherche.
   Tester les filtres par tags, les recettes publiques/privees, les favoris,
   les notes et l'ouverture d'une recette.
2. Ouvrir le carnet **Le carnet de Demo Owner**. Parcourir ses recettes, les
   membres, les messages en temps reel et l'historique d'une recette.
3. Depuis les membres, consulter les differents roles, puis creer une
   invitation. La liste des invitations contient deja des invitations en
   attente, acceptees et refusees.
4. Ouvrir une recette, ajouter un commentaire puis une reponse, ajouter une
   reaction, modifier la recette et verifier son historique. Tester aussi le
   favori et la note.
5. Aller dans **Planning**, ajouter ou modifier un repas, puis ouvrir la
   **liste de courses**. Verifier egalement les repas recurrents et le planning
   du carnet.
6. Creer une recherche sauvegardee et l'executer depuis **Recherche**. Tester
   ensuite l'import/export depuis **Donnees** ou les ecrans dedies ; les
   exemples de contrats sont disponibles dans [`docs/supmeal/examples`](./docs/supmeal/examples).
7. Ouvrir le panneau de notifications, marquer une notification comme lue,
   marquer tout comme lu et modifier les preferences.
8. Dans **Profil**, tester les preferences alimentaires et d'utilisation, le
   theme, le changement de mot de passe et la gestion des comptes OAuth simules.
9. Se deconnecter puis se reconnecter avec `demo.editor@supmeal.test`,
   `demo.commenter@supmeal.test` et `demo.reader@supmeal.test` pour observer
   respectivement les droits d'edition, de commentaire et de lecture. Enfin,
   utiliser `demo.empty@supmeal.test` pour verifier les etats vides.

Pour consulter les messages envoyes par les scenarios de verification email ou
de recuperation de mot de passe, ouvrir Mailpit a `http://localhost:8025/`.

## CI

Le workflow GitHub Actions est défini dans [`.github/workflows/ci.yml`](./.github/workflows/ci.yml).

Il exécute trois jobs distincts, sans secret GitHub :

- `backend` : `composer pint`, `composer analyse`, `composer test`
- `frontend` : `npm run lint`, `npm run typecheck`, `npm run test`
- `docker-compose` : validation des deux fichiers Compose via `docker compose config`

La CI backend utilise un PostgreSQL éphémère dédié au job :

- copie de `backend/.env.example` vers `backend/.env`
- génération locale de `APP_KEY`
- `DB_DATABASE=4meal_test`, avec des identifiants propres à la CI ;
- `CACHE_STORE=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`.

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
| `DB_CONNECTION` | driver de base de données | `pgsql` |
| `DB_DATABASE` | nom de base PostgreSQL | `4meal` |
| `DB_HOST` / `DB_PORT` | hôte et port BDD | `127.0.0.1` / `5434` |
| `DB_USERNAME` / `DB_PASSWORD` | identifiants BDD | `4meal` / `change_me` |
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
| `BACKEND_APP_KEY` | clé Laravel injectée au conteneur backend | clé de développement intégrée |
| `BACKEND_APP_DEBUG` | mode debug Laravel | `true` |
| `BACKEND_APP_URL` | URL backend exposée | `http://localhost:8080` |
| `BACKEND_SESSION_DRIVER` | driver de session backend | `file` |
| `BACKEND_CACHE_STORE` | driver de cache backend | `file` |
| `BACKEND_QUEUE_CONNECTION` | driver de queue backend | `sync` |
| `BACKEND_LOG_CHANNEL` | canal de logs Laravel | `stack` |
| `BACKEND_LOG_LEVEL` | niveau de logs Laravel | `debug` |
| `BACKEND_BROADCAST_CONNECTION` | connexion Laravel Broadcasting | `reverb` |
| `REVERB_APP_ID` / `REVERB_APP_KEY` | identifiants publics de l'application Reverb | valeurs locales de l'exemple |
| `REVERB_APP_SECRET` | secret partagé entre Laravel et Reverb | requis, sans valeur par défaut |
| `REVERB_ALLOWED_ORIGINS` | origines navigateur autorisées par Reverb | `localhost,127.0.0.1` |
| `FRONTEND_API_BASE_URL` | base URL API côté frontend | `/api` |
| `FRONTEND_REVERB_HOST` / `FRONTEND_REVERB_PORT` | adresse WebSocket vue par le navigateur | `localhost` / `8080` |
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
docker compose up -d postgres
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
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
# Renseigner REVERB_APP_SECRET avec une valeur aléatoire propre à l'environnement :
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
docker compose up -d
```

Copiez la valeur générée dans `REVERB_APP_SECRET` avant le démarrage. Le backend
refuse volontairement de démarrer avec une configuration Reverb incomplète.

`docker compose up` réutilise les images et les conteneurs existants. Les dépendances
ne sont réinstallées que si `composer.lock` ou `package-lock.json` a changé.
Pour reconstruire explicitement les images et recréer les conteneurs : `make rebuild`.
Les tests locaux utilisent une base et un volume séparés via `make backend-test`.

Points d'entrée HTTP :

- `http://localhost:8080/` : frontend via reverse proxy
- `http://localhost:8080/api/` : API Laravel via reverse proxy
- `http://localhost:8080/storage/` : fichiers publics backend


### Github

le projet est disponnible au répo suivant : https://github.com/quetinmialon/4meal

