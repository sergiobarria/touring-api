from django.urls import include, path

from .views import get_api_v1_health

urlpatterns = [
    path("v1/health/", get_api_v1_health, name="get_api_v1_health"),
    path("v1/tours/", include("tours.urls")),
]
