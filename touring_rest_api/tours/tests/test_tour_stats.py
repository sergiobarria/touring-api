from django.urls import reverse
from rest_framework import status
from rest_framework.test import APIClient, APITestCase
from tours.models import Tour

from touring_rest_api.core.helpers import generate_random_tour_data


class TourStatisticsTest(APITestCase):
    """This class tests the tour statistics related API views"""

    def setUp(self):
        """Set up the test client & random tour data for testing"""

        self.tours = [Tour.objects.create(**generate_random_tour_data(i)) for i in range(10)]
        self.client = APIClient()

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

    def test_get_tour_monthly_plan(self):
        """Receive a year as a parameter and return the monthly plan of tours"""
        url = reverse("monthly-tour-plan", kwargs={"year": 2024})
        response = self.client.get(url)

        self.assertEqual(response.status_code, status.HTTP_200_OK)
        self.assertTrue(len(response.data["data"]) > 0)

    def test_get_tour_stats(self):
        """Test retrieval of tour statistics grouped by difficulty"""
        url = reverse("tour-stats")
        response = self.client.get(url)

        self.assertEqual(response.status_code, status.HTTP_200_OK)
        self.assertTrue("data" in response.data)
        for stat in response.data["data"]["stats"]:
            self.assertTrue(
                all(
                    key in stat
                    for key in [
                        "difficulty",
                        "num_tours",
                        "num_ratings",
                        "average_ratings",
                        "average_price",
                        "min_price",
                        "max_price",
                    ]
                )
            )
            self.assertGreater(stat["num_tours"], 0)
