from django.urls import path

from .views import TourListCreateAPIView

urlpatterns = [
    path("", TourListCreateAPIView.as_view(), name="tour-list-create"),
]
