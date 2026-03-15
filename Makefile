DOCKER_COMPOSE = docker compose
APP_SERVICE = app
NODE_SERVICE = node

.PHONY: up down build shell composer artisan migrate seed fresh test npm logs worker-logs worker-restart worker-shell rabbitmq-queues scheduler-logs cutover-check

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

worker-logs:
	$(DOCKER_COMPOSE) logs -f worker

worker-restart:
	$(DOCKER_COMPOSE) restart worker

worker-shell:
	$(DOCKER_COMPOSE) exec worker sh

rabbitmq-queues:
	$(DOCKER_COMPOSE) exec rabbitmq rabbitmqctl -p /docintern list_queues name messages consumers

scheduler-logs:
	$(DOCKER_COMPOSE) logs -f scheduler

cutover-check:
	$(DOCKER_COMPOSE) exec $(APP_SERVICE) php artisan docintern:cutover-check

%:
	@:
