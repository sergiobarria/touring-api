"""
This module contains the configuration settings for the application.
It reads from the .env file and sets the values for the settings.
"""

import environ

env = environ.Env(
    DEBUG=(bool, False),
    SECRET_KEY=(str, ""),
)
