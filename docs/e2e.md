# Tests end-to-end

La suite E2E couvre uniquement les parcours critiques : inscription puis connexion, création d’un cookbook, invitation d’un membre simulé, création d’une recette, ajout au planning, recherche, puis export/import JSON.

Les détails de validation restent couverts par Vitest et les règles métier par Pest. Les tests E2E utilisent l’application complète et des comptes générés avec une adresse unique à chaque scénario.

## Exécution locale

Prérequis : Docker Desktop, Node.js 20.19+ et npm.

Depuis la racine :

```bash
cp .env.docker.example .env
docker compose --env-file .env up --build -d
docker compose --env-file .env exec backend php artisan migrate --force
cd frontend
npm ci
npm run e2e:install
npm run e2e
```

La stack est accessible sur `http://127.0.0.1:8080`. Pour viser une instance déjà démarrée, définir `E2E_BASE_URL` avant `npm run e2e`. Les traces et captures d’échec sont écrites dans `frontend/test-results/`.

## CI

Le job `End-to-end critical flows` du workflow GitHub Actions démarre la stack Docker, applique les migrations, installe Chromium, puis lance `npm run e2e` avec `E2E_BASE_URL=http://127.0.0.1:8080`. `frontend/test-results/` est téléversé comme artefact pour faciliter le diagnostic.
