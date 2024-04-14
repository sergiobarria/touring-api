from django.urls import path

from . import views

urlpatterns = [
    path("", views.TourListCreateAPIView.as_view(), name="tour-list-create"),
    path("top-rated", views.ToursTopRatedAPIView.as_view(), name="top-rated-tours"),
    path("monthly-plan/<int:year>", views.ToursMonthlyPlanAPIView.as_view(), name="monthly-tour-plan"),
    path("stats", views.ToursStatsAPIView.as_view(), name="tour-stats"),
    path("<int:pk>", views.TourDetailAPIView.as_view(), name="tour-detail"),
]
