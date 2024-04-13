# Touring REST API

> This is the REST API for the Touring Website. This API is built using Django, and PostgreSQL.

## Setup Steps

### Start new Django project using Poetry

```bash
$ poetry init # Create a new project
$ poetry add django djangorestframework # Add dependencies
$ poetry add -G dev ruff # Add development dependencies
$ poetry run django-admin startproject core # Create a new Django project
```

-   Change `core` directory to `touring_api`

NOTE: To run any Django command, from the root directory run `poetry run python touring_api/manage.py <command>`

### Other Poetry commands

```bash
$ poetry install # Install dependencies
$ poetry export --without-hashes -o requirements.txt # Export dependencies to requirements.txt
```
