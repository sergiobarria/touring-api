from ninja import NinjaAPI

api = NinjaAPI()


@api.get("/health")
def get_health(request):
    return {
        "status": "ok",
        "message": "Touric REST API is up and running!",
        "version": "0.1.0",
    }


# ===== Add API routers here 👇 =====
# e.g: api.add_router('/path/', 'app_name.api.router) # Assuming the router is in app_name/api.py
