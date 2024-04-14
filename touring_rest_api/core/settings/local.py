import sys

from . import base

DEBUG = base.env("DEBUG")

ALLOWED_HOSTS = ["*"]

# Database
# https://docs.djangoproject.com/en/5.0/ref/settings/#databases
DATABASES = {
    "default": {
        "ENGINE": "django.db.backends.postgresql",
        "NAME": base.env("PG_DATABASE"),
        "USER": base.env("PG_USER"),
        "PASSWORD": base.env("PG_PASSWORD"),
        "HOST": base.env("PG_HOST"),
        "PORT": base.env("PG_PORT"),
    },
    "test": {
        "ENGINE": "django.db.backends.postgresql",
        "NAME": "touring_dev",
        "USER": "test_user",
        "PASSWORD": "test_password",
        "HOST": "localhost",
        "PORT": "5432",
    },
}

# Use test database if "test" in sys.argv
if "test" in sys.argv:
    DATABASES["default"] = DATABASES["test"]

# Static files (CSS, JavaScript, Images)
# https://docs.djangoproject.com/en/5.0/howto/static-files/
STATIC_URL = "static/"
