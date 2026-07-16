# SUPMEAL

Structure du monorepo :

- `backend/` : Laravel backend
- `frontend/` : Vue 3 + TypeScript frontend
- `docs/` : documentation
- `docker/` : Docker configuration (placeholder)

## Docker Compose

Point d'entree HTTP du projet :

- `http://localhost:8080/` : frontend Vue via reverse proxy
- `http://localhost:8080/api/` : API Laravel via reverse proxy
- `http://localhost:8080/robots.txt`, `http://localhost:8080/favicon.ico` et `http://localhost:8080/storage/` : fichiers publics proxifies

Ports :

- `PROXY_PORT` : port HTTP expose par le reverse proxy, `8080` par defaut
- `POSTGRES_PORT` : port PostgreSQL expose vers l'hote, `5434` par defaut
- `frontend` reste interne au reseau Docker sur `5173`
- `backend` reste interne au reseau Docker sur `8000`

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
pnpm install
pnpm dev
```
