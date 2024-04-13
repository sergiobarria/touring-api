"""
This module contains the configuration settings for the application.
It reads from the .env file and sets the values for the settings.
"""

import environ

env = environ.Env(
    DEBUG=(bool, False),
    SECRET_KEY=(str, ""),
    PG_HOST=(str, ""),
    PG_DATABASE=(str, ""),
    PG_USER=(str, ""),
    PG_PASSWORD=(str, ""),
    PG_PORT=(int, 5432),
)
