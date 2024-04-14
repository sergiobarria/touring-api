from api.utils import APIResponse
from rest_framework import status
from rest_framework.views import APIView

from tours.models import Tour
from tours.serializers import TourSerializer


class TourListCreateAPIView(APIView):
    """List or create tours."""

    def get(self, request):
        """List all tours."""
        tours = Tour.objects.all()
        serializer = TourSerializer(tours, many=True)

        return APIResponse(data=serializer.data, results=len(tours))

    def post(self, request):
        """Create a new tour."""
        serializer = TourSerializer(data=request.data)

        if not serializer.is_valid():
            return APIResponse(errors=serializer.errors, status=status.HTTP_400_BAD_REQUEST)

        serializer.save()
        return APIResponse(data=serializer.data, status=status.HTTP_201_CREATED)
