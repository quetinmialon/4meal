SHELL := /bin/sh

DEFAULT_GOAL := help
.DEFAULT_GOAL := $(DEFAULT_GOAL)

DOCKER_COMPOSE_FILE ?= docker-compose.yml
DOCKER_ENV_FILE ?= .env.docker.example
COMPOSE = docker compose --env-file $(DOCKER_ENV_FILE) -f $(DOCKER_COMPOSE_FILE)
TEST_COMPOSE = docker compose --env-file $(DOCKER_ENV_FILE) -f docker-compose.test.yml -p 4meal-test

BACKEND_DIR := backend
FRONTEND_DIR := frontend

## By default use the dockerized composer so Windows users don't need a local
## composer install. To force using a host-installed composer, run make with
## `HOST_COMPOSER=1 make ...`.
ifdef HOST_COMPOSER
BACKEND_COMPOSER = composer --working-dir=$(BACKEND_DIR)
else
BACKEND_COMPOSER = $(COMPOSE) run --rm -w /var/www/html backend composer --working-dir=/var/www/html
# note: backend files are mounted at /var/www/html inside the container
endif
BACKEND_ARTISAN = php $(BACKEND_DIR)/artisan
BACKEND_NPM = npm --prefix $(BACKEND_DIR)

# Run frontend npm commands inside the `frontend` container by default so that
# developers don't need node/npm installed locally. Use `HOST_NPM=1` to force
# local execution.
ifdef HOST_NPM
FRONTEND_NPM = npm --prefix $(FRONTEND_DIR)
else
FRONTEND_NPM = $(COMPOSE) run --rm -w /app frontend npm --prefix /app
endif

# Command used specifically for running tests: ensure the container runs with
# the same env as GitHub Actions (testing, sqlite in-memory, array stores).
ifdef HOST_COMPOSER
BACKEND_TEST_COMPOSER = $(BACKEND_COMPOSER)
else
BACKEND_TEST_COMPOSER = $(TEST_COMPOSE) run --rm -w /var/www/html backend-test sh -lc "composer install --no-interaction --prefer-dist --optimize-autoloader && php artisan migrate:fresh --force && composer test"
endif

.PHONY: \
	help env backend-env \
	init build up start stop down restart logs ps config \
	rebuild rebuild-volumes wait-backend \
	backend-up backend-stop backend-restart backend-logs backend-shell \
	frontend-up frontend-stop frontend-restart frontend-logs frontend-shell \
	postgres-up postgres-stop postgres-restart postgres-logs postgres-shell \
	proxy-up proxy-stop proxy-restart proxy-logs proxy-shell \
	migrate migrate-fresh seed fresh-seed \
	backend-install backend-npm-install backend-build-assets backend-pint backend-analyse backend-test backend-quality \
	backend-migrate backend-migrate-fresh backend-seed backend-fresh-seed \
	frontend-install frontend-build frontend-lint frontend-typecheck frontend-test frontend-openapi \
	test ci-backend ci-frontend ci-docker ci

help: ## Affiche les cibles disponibles
	@awk 'BEGIN {FS = ":.*## "}; /^[a-zA-Z0-9_.-]+:.*## / { printf "%-24s %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

env: ## Vérifie la présence des fichiers d'environnement locaux
	@test -f $(BACKEND_DIR)/.env || cp $(BACKEND_DIR)/.env.example $(BACKEND_DIR)/.env

backend-env: env ## Alias pour initialiser l'environnement backend

init: env up wait-backend migrate ## Initialise l'environnement Docker et applique les migrations

build: ## Build les images Docker
	$(COMPOSE) build

up: ## Démarre toute la stack Docker en arrière-plan
	$(COMPOSE) up -d

start: up ## Alias de up

stop: ## Stoppe les conteneurs sans supprimer les volumes
	$(COMPOSE) stop

down: ## Stoppe et supprime les conteneurs, réseaux et orphelins
	$(COMPOSE) down --remove-orphans

restart: ## Redémarre toute la stack Docker
	$(COMPOSE) restart

logs: ## Suit les logs de toute la stack Docker
	$(COMPOSE) logs -f

ps: ## Affiche l'état des services Docker
	$(COMPOSE) ps

config: ## Valide et résout la configuration docker compose
	$(COMPOSE) config

rebuild: ## Reconstruit et recrée les conteneurs (volontairement)
	$(COMPOSE) up -d --build --force-recreate

rebuild-volumes: ## Supprime les volumes nommés puis reconstruit toute la stack
	$(COMPOSE) down -v --remove-orphans
	$(COMPOSE) up -d --build

wait-backend: ## Attend que le conteneur backend réponde aux commandes Artisan
	@printf "Waiting for backend"
	@until $(COMPOSE) exec -T backend php artisan about >/dev/null 2>&1; do printf "."; sleep 2; done
	@printf "\n"

backend-up: ## Démarre uniquement le service backend
	$(COMPOSE) up -d backend

backend-stop: ## Stoppe uniquement le service backend
	$(COMPOSE) stop backend

backend-restart: ## Redémarre uniquement le service backend
	$(COMPOSE) restart backend

backend-logs: ## Suit les logs du service backend
	$(COMPOSE) logs -f backend

backend-shell: ## Ouvre un shell dans le conteneur backend
	$(COMPOSE) exec backend sh

frontend-up: ## Démarre uniquement le service frontend
	$(COMPOSE) up -d frontend

frontend-stop: ## Stoppe uniquement le service frontend
	$(COMPOSE) stop frontend

frontend-restart: ## Redémarre uniquement le service frontend
	$(COMPOSE) restart frontend

frontend-logs: ## Suit les logs du service frontend
	$(COMPOSE) logs -f frontend

frontend-shell: ## Ouvre un shell dans le conteneur frontend
	$(COMPOSE) exec frontend sh

postgres-up: ## Démarre uniquement le service postgres
	$(COMPOSE) up -d postgres

postgres-stop: ## Stoppe uniquement le service postgres
	$(COMPOSE) stop postgres

postgres-restart: ## Redémarre uniquement le service postgres
	$(COMPOSE) restart postgres

postgres-logs: ## Suit les logs du service postgres
	$(COMPOSE) logs -f postgres

postgres-shell: ## Ouvre un shell dans le conteneur postgres
	$(COMPOSE) exec postgres sh

proxy-up: ## Démarre uniquement le reverse proxy
	$(COMPOSE) up -d reverse-proxy

proxy-stop: ## Stoppe uniquement le reverse proxy
	$(COMPOSE) stop reverse-proxy

proxy-restart: ## Redémarre uniquement le reverse proxy
	$(COMPOSE) restart reverse-proxy

proxy-logs: ## Suit les logs du reverse proxy
	$(COMPOSE) logs -f reverse-proxy

proxy-shell: ## Ouvre un shell dans le conteneur reverse proxy
	$(COMPOSE) exec reverse-proxy sh

migrate: ## Lance les migrations Laravel dans Docker
	$(COMPOSE) exec -T backend php artisan migrate --force

migrate-fresh: ## Recrée la base via migrate:fresh dans Docker
	$(COMPOSE) exec -T backend php artisan migrate:fresh --force

seed: ## Lance les seeders Laravel dans Docker
	$(COMPOSE) exec -T backend php artisan db:seed --force

fresh-seed: ## Recrée la base et relance les seeders dans Docker
	$(COMPOSE) exec -T backend php artisan migrate:fresh --seed --force

backend-install: ## Installe les dépendances PHP du backend
	$(BACKEND_COMPOSER) install

backend-npm-install: ## Installe les dépendances Node du backend
	$(BACKEND_NPM) install

backend-build-assets: ## Build les assets Vite du backend
	$(BACKEND_NPM) run build

backend-pint: ## Exécute Pint côté backend
	$(BACKEND_COMPOSER) pint

backend-analyse: ## Exécute PHPStan/Larastan côté backend
	$(BACKEND_COMPOSER) analyse

backend-test: ## Exécute Pest côté backend
	$(BACKEND_TEST_COMPOSER) test

backend-quality: backend-install ## Exécute la suite qualité backend (pint, analyse, test)
	$(BACKEND_COMPOSER) quality

backend-migrate: env ## Lance les migrations Laravel en local
	$(BACKEND_ARTISAN) migrate

backend-migrate-fresh: env ## Recrée la base via migrate:fresh en local
	$(BACKEND_ARTISAN) migrate:fresh

backend-seed: env ## Lance les seeders Laravel en local
	$(BACKEND_ARTISAN) db:seed

backend-fresh-seed: env ## Recrée la base et relance les seeders en local
	$(BACKEND_ARTISAN) migrate:fresh --seed

frontend-install: ## Installe les dépendances npm du frontend
	$(FRONTEND_NPM) ci

frontend-build: ## Build le frontend
	$(FRONTEND_NPM) run build

frontend-lint: ## Exécute ESLint côté frontend
	$(FRONTEND_NPM) run lint

frontend-typecheck: ## Exécute le typecheck côté frontend
	$(FRONTEND_NPM) run typecheck

frontend-test: ## Exécute Vitest côté frontend
	$(FRONTEND_NPM) run test

frontend-openapi: ## Valide le contrat OpenAPI depuis le frontend
	$(FRONTEND_NPM) run openapi:validate

test: ## Lance les tests front et back
	$(MAKE) backend-test
	$(MAKE) frontend-test

ci-backend: backend-install ## Rejoue les briques CI backend
	$(MAKE) backend-pint
	$(MAKE) backend-analyse
	$(MAKE) backend-test

ci-frontend: ## Rejoue les briques CI frontend
	$(MAKE) frontend-lint
	$(MAKE) frontend-typecheck
	$(MAKE) frontend-test

ci-docker: ## Rejoue la validation Docker Compose de la CI
	$(MAKE) config
	$(TEST_COMPOSE) config

ci: ## Lance l'ensemble des vérifications CI locales
	$(MAKE) ci-backend
	$(MAKE) ci-frontend
	$(MAKE) ci-docker
