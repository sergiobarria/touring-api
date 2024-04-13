from django.urls import path

from .views import TourListAPIView

urlpatterns = [
    path("", TourListAPIView.as_view(), name="tour-list"),
]
