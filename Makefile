## Shortwave — development entry points.
##
## Everything runs inside containers: the host is not expected to have PHP,
## Composer or any of the three databases. `make install` is the only command
## needed on a fresh checkout.

SHELL := /bin/bash
.DEFAULT_GOAL := help

COMPOSE := docker compose
# Matching the host uid keeps bind-mounted files writable from both sides.
export UID := $(shell id -u)
export GID := $(shell id -g)

# One-off commands use `run --rm` so they do not need the stack up; anything that
# talks to a database gets the dependencies started first.
# CONTAINER_ROLE=cli tells the entrypoint to skip the boot sequence — no waiting on
# stores, no migrations — which a one-off command has no use for.
RUN := $(COMPOSE) run --rm --no-deps -e CONTAINER_ROLE=cli app
RUN_WITH_DEPS := $(COMPOSE) run --rm -e CONTAINER_ROLE=cli app
# The test service carries .env.testing, so the suite never touches dev data.
RUN_TEST := $(COMPOSE) run --rm test

.PHONY: help install up down restart logs shell ps build install-deps key migrate fresh seed \
        test test-unit test-feature coverage lint fix stan check indexes flush prune queue tinker clean

help: ## Show the available targets
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

install: ## Fresh checkout to running stack: env, deps, key, containers, schema
	@test -f .env || (cp .env.example .env && echo "  .env created from .env.example")
	$(COMPOSE) build
	$(MAKE) install-deps
	$(MAKE) key
	$(COMPOSE) up -d
	@echo
	@echo "  Shortwave is up on http://localhost:$${SHORTWAVE_HTTP_PORT:-8080}"
	@echo "  Try:  curl -s localhost:$${SHORTWAVE_HTTP_PORT:-8080}/api/v1/health | jq"

install-deps: ## Install Composer dependencies into ./vendor
	$(RUN) composer install --no-interaction --prefer-dist

up: ## Start the stack in the background
	$(COMPOSE) up -d

down: ## Stop the stack, keeping data
	$(COMPOSE) down

restart: ## Recreate the PHP containers, picking up config changes
	$(COMPOSE) up -d --force-recreate app worker scheduler

build: ## Rebuild images
	$(COMPOSE) build --pull

logs: ## Tail logs (make logs S=worker for one service)
	$(COMPOSE) logs -f --tail=100 $(S)

ps: ## Show container status
	$(COMPOSE) ps

shell: ## Open a shell in the app container
	$(COMPOSE) exec app bash

tinker: ## Open an interactive REPL
	$(COMPOSE) exec app php artisan tinker

# --- Schema ----------------------------------------------------------------

key: ## Generate APP_KEY if .env does not have one
	@grep -q '^APP_KEY=base64:' .env || $(RUN) php artisan key:generate --ansi

migrate: ## Run pending migrations
	$(RUN_WITH_DEPS) php artisan migrate --ansi

fresh: ## Drop and rebuild the schema, then seed
	$(RUN_WITH_DEPS) php artisan migrate:fresh --seed --ansi
	$(MAKE) indexes

seed: ## Seed demo data
	$(RUN_WITH_DEPS) php artisan db:seed --ansi

indexes: ## Create the MongoDB indexes
	$(RUN_WITH_DEPS) php artisan shortwave:mongo-sync --ansi

# --- Background work -------------------------------------------------------

flush: ## Reconcile buffered clicks now instead of waiting for the scheduler
	$(RUN_WITH_DEPS) php artisan shortwave:flush-buffer --ansi
	$(RUN_WITH_DEPS) php artisan shortwave:flush-clicks --ansi

prune: ## Delete click events past the retention window
	$(RUN_WITH_DEPS) php artisan shortwave:prune-clicks --ansi

queue: ## Run a queue worker in the foreground
	$(COMPOSE) exec app php artisan queue:work --queue=analytics,default --verbose

# --- Quality ---------------------------------------------------------------

test: ## Run the full suite
	$(RUN_TEST) ./vendor/bin/pest --colors=always

test-unit: ## Run only the unit suite (no databases needed)
	$(RUN) ./vendor/bin/pest --testsuite=Unit --colors=always

test-feature: ## Run only the feature suite
	$(RUN_TEST) ./vendor/bin/pest --testsuite=Feature --colors=always

coverage: ## Run the suite with a coverage floor
	$(RUN_TEST) ./vendor/bin/pest --coverage --min=80 --colors=always

lint: ## Check formatting without changing anything
	$(RUN) ./vendor/bin/pint --test

fix: ## Apply formatting
	$(RUN) ./vendor/bin/pint

stan: ## Run static analysis
	$(RUN) ./vendor/bin/phpstan analyse --memory-limit=1G

check: lint stan test ## Everything CI runs

clean: ## Remove containers, volumes and caches
	$(COMPOSE) down -v --remove-orphans
	rm -rf bootstrap/cache/*.php .phpunit.cache coverage
