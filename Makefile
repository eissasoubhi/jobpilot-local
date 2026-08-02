SHELL := /bin/bash

.PHONY: setup start stop logs reset test extension

setup:
	@test -f .env || cp .env.example .env
	@echo "Generate APP_ENCRYPTION_KEY with: openssl rand -base64 32"
	docker compose build

start:
	docker compose up --build

stop:
	docker compose down

logs:
	docker compose logs -f

reset:
	docker compose down -v
	docker compose up --build

test:
	docker compose run --rm api php bin/phpunit

extension:
	@echo "Open chrome://extensions, enable Developer mode, then Load unpacked: $$(pwd)/extension"
