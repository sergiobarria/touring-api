from split_settings.tools import include

from core.config import env

include("base.py")

if env("DEBUG"):
    include("local.py")
else:
    include("production.py")
