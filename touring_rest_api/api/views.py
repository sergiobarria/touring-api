from django.utils import timezone
from rest_framework import status
from rest_framework.decorators import api_view
from rest_framework.response import Response


@api_view(["GET"])
def get_api_v1_health(request):
    """Get API health status."""
    data = {
        "status": "ok",
        "name": "Touring Rest API",
        "message": "Server is running.",
        "version": "1.0.0",
        "timestamp": timezone.now(),
    }

    return Response(data, status=status.HTTP_200_OK)
