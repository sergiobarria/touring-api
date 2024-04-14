from django.urls import reverse
from rest_framework import status
from rest_framework.test import APIClient, APITestCase

from tours.models import Tour


class TourDetailAPITest(APITestCase):
    """Test the Tour detail API views"""

    def setUp(self):
        self.client = APIClient()
        self.sample_tour_data = {
            "name": "Great Wall Tour",
            "duration": 5,
            "max_group_size": 15,
            "difficulty": "easy",
            "price": 200,
            "ratings_average": 4.5,
            "ratings_quantity": 150,
            "summary": "A brief summary",
            "description": "A detailed description",
            "start_dates": ["2024-04-25T10:00:00Z", "2024-07-20T10:00:00Z"],
        }
        self.tour = Tour.objects.create(**self.sample_tour_data)
        self.url = reverse("tour-detail", kwargs={"pk": self.tour.pk})

    def test_get_tour_detail(self):
        """Test getting a tour detail"""
        response = self.client.get(self.url)

        self.assertEqual(response.status_code, status.HTTP_200_OK)
        self.assertEqual(response.data["data"]["name"], "Great Wall Tour")

    def test_get_tour_detail_not_found(self):
        """Test getting a tour detail that does not exist"""
        response = self.client.get(reverse("tour-detail", kwargs={"pk": 100}))

        self.assertEqual(response.status_code, status.HTTP_404_NOT_FOUND)

    def test_update_tour_detail(self):
        """Test updating a tour detail"""
        response = self.client.patch(self.url, {"name": "Great Wall Tour Updated"})

        self.assertEqual(response.status_code, status.HTTP_200_OK)
        self.assertEqual(response.data["data"]["name"], "Great Wall Tour Updated")

    def test_delete_tour(self):
        """Test deleting a tour"""
        response = self.client.delete(self.url)

        self.assertEqual(response.status_code, status.HTTP_204_NO_CONTENT)
        self.assertEqual(Tour.objects.count(), 0)
