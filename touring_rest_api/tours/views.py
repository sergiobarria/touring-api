from rest_framework import status
from rest_framework.views import APIView

from touring_rest_api.core.utils import APIResponse
from tours.models import Tour
from tours.serializers import TourDetailSerializer, TourListSerializer


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
