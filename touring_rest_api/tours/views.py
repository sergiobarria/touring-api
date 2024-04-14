from django.db.models import Avg, Count, F, Func, IntegerField, Max, Min, Sum
from django.utils import timezone
from rest_framework import status
from rest_framework.views import APIView

from touring_rest_api.core.constants import MONTHS
from touring_rest_api.core.utils import APIResponse

from .models import Tour
from .serializers import TourDetailSerializer, TourListSerializer


class ExtractMonthFromDates(Func):
    function = "EXTRACT"
    template = "%(function)s(MONTH from %(expressions)s)"
    output_field = IntegerField()


class TourListCreateAPIView(APIView):
    """List or create tours."""

    def get(self, request):
        """List all tours."""
        tours = Tour.objects.all()
        serializer = TourListSerializer(tours, many=True)

        return APIResponse(data=serializer.data, results=len(tours))

    def post(self, request):
        """Create a new tour."""
        serializer = TourListSerializer(data=request.data)

        if not serializer.is_valid():
            return APIResponse(errors=serializer.errors, status_code=status.HTTP_400_BAD_REQUEST)

        serializer.save()
        return APIResponse(data=serializer.data, status_code=status.HTTP_201_CREATED)


class TourDetailAPIView(APIView):
    """Retrieve, update or delete a tour instance."""

    def get_object(self, pk):
        """Retrieve a tour from the database."""
        try:
            return Tour.objects.get(pk=pk)
        except Tour.DoesNotExist:
            return None

    def get(self, request, pk):
        """Retrieve a tour instance."""
        tour = self.get_object(pk)

        if not tour:
            return APIResponse(success=False, errors="Tour not found", status_code=status.HTTP_404_NOT_FOUND)

        serializer = TourDetailSerializer(tour)
        return APIResponse(data=serializer.data)

    def patch(self, request, pk):
        """Update a tour instance."""
        tour = self.get_object(pk)

        if not tour:
            return APIResponse(success=False, errors="Tour not found", status_code=status.HTTP_404_NOT_FOUND)

        serializer = TourDetailSerializer(tour, data=request.data, partial=True)

        if not serializer.is_valid():
            return APIResponse(errors=serializer.errors, status_code=status.HTTP_400_BAD_REQUEST)

        serializer.save()
        return APIResponse(data=serializer.data)

    def delete(self, request, pk):
        """Delete a tour instance:."""
        tour = self.get_object(pk)

        if not tour:
            return APIResponse(success=False, errors="Tour not found", status_code=status.HTTP_404_NOT_FOUND)

        tour.delete()
        return APIResponse(status_code=status.HTTP_204_NO_CONTENT)


class ToursTopRatedAPIView(APIView):
    """Retrieve the top 5 rated tours sorted by ratings_average."""

    def get(self, request):
        """Top 5 rated tours."""
        tours = Tour.objects.order_by("-ratings_average")[:5]
        serializer = TourListSerializer(tours, many=True)

        return APIResponse(data=serializer.data, results=len(tours))


class ToursMonthlyPlanAPIView(APIView):
    """Retrieve the monthly plan of tours for a given year."""

    def get(self, request, year):
        """Get the monthly plan of tours for a given year."""

        year = int(year)
        year_start = timezone.make_aware(timezone.datetime(year, 1, 1))
        year_end = timezone.make_aware(timezone.datetime(year, 12, 31))

        tours = (
            Tour.objects.annotate(month=ExtractMonthFromDates(F("start_dates__0")))
            .filter(start_dates__0__gte=year_start, start_dates__0__lte=year_end)
            .values("month")
            .annotate(num_tour_starts=Count("id"), tours=Count("name", distinct=True))
            .order_by("month")
        )

        plan = list(tours)
        for item in plan:
            item["month"] = MONTHS.get(item["month"], "Unknown")

        return APIResponse(data={"plan": list(tours)})


class ToursStatsAPIView(APIView):
    """Retrieve statistics about tours, grouped by difficulty."""

    def get(self, request):
        stats = (
            Tour.objects.filter(ratings_average__gte=4.5)
            .values("difficulty")
            .annotate(
                num_tours=Count("id"),
                num_ratings=Sum("ratings_quantity"),
                average_ratings=Avg("ratings_average"),
                average_price=Avg("price"),
                min_price=Min("price"),
                max_price=Max("price"),
            )
            .order_by("average_price")
        )

        return APIResponse(data={"stats": list(stats)})
