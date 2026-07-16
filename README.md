# SUPMEAL

Structure du monorepo :

- `backend/` : Laravel backend
- `frontend/` : Vue 3 + TypeScript frontend
- `docs/` : documentation
- `docker/` : Docker configuration (placeholder)

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
