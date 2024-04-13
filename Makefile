.PHONY: install
install:
	@echo "Installing project dependencies..."
	poetry install

.PHONY: run-server
run-server:
	@echo "Running server..."
	poetry run python touring_rest_api/manage.py runserver

.PHONY: app
app:
	@echo "Creating new app $(app_name)..."
	@mkdir -p touring_rest_api/$(app_name)
	poetry run django-admin startapp $(app_name) touring_rest_api/$(app_name)
	@echo "App $(app_name) created successfully! at touring_rest_api/$(app_name)"

.PHONY: migrations
migrations:
	@echo "Generating migrations..."
	poetry run python touring_rest_api/manage.py makemigrations

.PHONY: migrate
migrate:
	@echo "Applying migrations..."
	poetry run python touring_rest_api/manage.py migrate

.PHONY: superuser
superuser:
	@echo "Creating superuser..."
	poetry run python touring_rest_api/manage.py createsuperuser

.PHONY: requirements
requirements:
	@echo "Generating requirements.txt..."
	poetry export --without-hashes -o requirements.txt


.PHONY: test
test:
	@echo "Running tests..."
	poetry run python touring_rest_api/manage.py test touring_rest_api
