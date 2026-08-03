SHELL := /bin/bash

.PHONY: setup prepare-storage migrate-private-storage start stop logs reset test extension open-private db-shell

setup:
	@test -f .env || cp .env.example .env
	@echo "Generate APP_ENCRYPTION_KEY with: openssl rand -base64 32"
	docker compose build

prepare-storage:
	@mkdir -p data/private
	@if [ ! -f data/private/.storage-migrated ]; then \
		echo "Migrating existing private files to data/private..."; \
		docker compose stop api scheduler >/dev/null 2>&1 || true; \
		docker compose --profile tools run --rm private-storage-migrator; \
		touch data/private/.storage-migrated; \
	fi

migrate-private-storage:
	@rm -f data/private/.storage-migrated
	@$(MAKE) prepare-storage

start: prepare-storage
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

open-private:
	@mkdir -p data/private
	open data/private

db-shell:
	docker compose exec db psql -U $${POSTGRES_USER:-jobpilot} -d $${POSTGRES_DB:-jobpilot}
