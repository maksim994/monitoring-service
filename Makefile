.PHONY: up down logs backend-shell migrate frontend-install seed-demo test smoke

up:
	docker compose -f deploy/docker-compose.yml up -d --build

down:
	docker compose -f deploy/docker-compose.yml down

logs:
	docker compose -f deploy/docker-compose.yml logs -f

migrate:
	docker compose -f deploy/docker-compose.yml exec api php bin/console doctrine:migrations:migrate --no-interaction

seed-demo:
	docker compose -f deploy/docker-compose.yml exec api php bin/console app:seed-demo

seed-platform-admin:
	docker compose -f deploy/docker-compose.yml exec api php bin/console app:seed-platform-admin

backend-shell:
	docker compose -f deploy/docker-compose.yml exec api sh

frontend-install:
	cd frontend && npm install

test:
	docker compose -f deploy/docker-compose.yml exec -T api php bin/phpunit

smoke:
	bash scripts/smoke-test.sh
