.PHONY: install
install:
	poetry install

.PHONY: app
app:
	@mkdir -p touric_api/$(name)
	poetry run python -m touric_api.manage startapp $(name) touric_api/$(name)

.PHONY: run-server
run-server:
	poetry run python -m touric_api.manage runserver

.PHONY: migrations
migrations:
	poetry run python -m touric_api.manage makemigrations

.PHONY: migrate
migrate:
	poetry run python -m touric_api.manage migrate

.PHONY: shell
shell:
	poetry run python -m touric_api.manage shell

.PHONY: superuser
superuser:
	poetry run python -m touric_api.manage createsuperuser