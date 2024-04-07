from split_settings.tools import include

from .base import env

include("base.py")

if env("DEBUG"):
    print("=> Loading local settings...")
    include("local.py")
else:
    print("=> Loading production settings...")
    include("production.py")
