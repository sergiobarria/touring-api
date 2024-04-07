# Touric REST API

> This is a REST API for the Touric project. It is built with Django, Django Ninja, and PostgreSQL.

## Commands

### Generate random secret key

```bash
$ poetry run python -m touric_api.manage shell

# Inside the shell
>>> from django.core.management.utils import get_random_secret_key
>>> print(get_random_secret_key())
```