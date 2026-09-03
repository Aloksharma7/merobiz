.PHONY: up down reset logs validate

up:
	docker compose up --build

down:
	docker compose down

reset:
	docker compose down -v
	docker compose up --build

logs:
	docker compose logs -f api web

validate:
	cd api && php artisan test
	cd web && npm run typecheck && npm run lint && npm run build
