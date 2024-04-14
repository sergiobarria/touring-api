from api.helpers import generate_random_tour_data
from django.urls import reverse
from rest_framework import status
from rest_framework.test import APIClient, APITestCase

from tours.models import Tour


class TourStatisticsTest(APITestCase):
    """This class tests the tour statistics related API views"""

    def setUp(self):
        """Set up the test client & random tour data for testing"""
        self.client = APIClient()
        self.tours = []

        for i in range(20):
            tour = Tour.objects.create(**generate_random_tour_data(i))
            self.tours.append(tour)

    def test_get_top_rated_tours(self):
        """Test getting the top 5 rated tours"""

        url = reverse("top-rated-tours")
        response = self.client.get(url)
        data = response.data["data"]

        self.assertEqual(response.status_code, status.HTTP_200_OK)
        self.assertEqual(len(data), 5)

        # Check that the response is sorted by ratings_average in descending order
        for i in range(4):
            self.assertGreaterEqual(data[i]["ratings_average"], data[i + 1]["ratings_average"])
