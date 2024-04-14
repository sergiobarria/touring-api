from django.urls import path

from .views import TourDetailAPIView, TourListCreateAPIView

urlpatterns = [
    path("", TourListCreateAPIView.as_view(), name="tour-list-create"),
    path("<int:pk>", TourDetailAPIView.as_view(), name="tour-detail"),
]
