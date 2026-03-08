DOCKER_COMPOSE = docker compose
APP_SERVICE = app
NODE_SERVICE = node

.PHONY: up down build shell composer artisan migrate seed fresh test npm logs

up:
	$(DOCKER_COMPOSE) up -d --build

down:
	$(DOCKER_COMPOSE) down

build:
	$(DOCKER_COMPOSE) build

shell:
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) sh

composer:
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) composer $(filter-out $@,$(MAKECMDGOALS))

artisan:
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan $(filter-out $@,$(MAKECMDGOALS))

migrate:
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan migrate --force

seed:
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan db:seed --force

fresh:
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan migrate:fresh --seed --force

test:
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan test --compact

npm:
	$(DOCKER_COMPOSE) exec $(NODE_SERVICE) npm $(filter-out $@,$(MAKECMDGOALS))

logs:
	$(DOCKER_COMPOSE) logs -f

%:
	@:
