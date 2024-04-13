from django.test import SimpleTestCase
from django.urls import reverse
from rest_framework import status


class HealthCheckTestCase(SimpleTestCase):
    """Test the health check API endpoint for all API versions."""

    def test_get_api_v1_health(self):
        """Test the health endpoint for API version 1."""
        url = reverse("get_api_v1_health")
        response = self.client.get(url)

        self.assertEqual(response.status_code, status.HTTP_200_OK)
        self.assertEqual(response.data["status"], "ok")
        self.assertEqual(response.data["name"], "Touring Rest API")
        self.assertEqual(response.data["message"], "Server is running.")
        self.assertEqual(response.data["version"], "1.0.0")
        self.assertIsNotNone(response.data["timestamp"])
