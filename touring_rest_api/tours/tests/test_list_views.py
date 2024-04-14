from django.urls import reverse
from rest_framework import status
from rest_framework.test import APIClient, APITestCase

from tours.models import Tour


class TourListAPITest(APITestCase):
    """Test the Tour list API views"""

    def setUp(self):
        self.client = APIClient()
        self.url = reverse("tour-list-create")

    def test_list_tours(self):
        """Test the list of tours endpoint"""
        Tour.objects.create(name="Great Wall Tour")
        Tour.objects.create(name="Eiffel Tower Visit")

        response = self.client.get(self.url)

        self.assertEqual(response.status_code, status.HTTP_200_OK)
        self.assertEqual(len(response.data["data"]), 2)
        self.assertTrue("results" in response.data)
        self.assertEqual(response.data["results"], 2)

    def test_create_tour_invalid_data(self):
        """Test the create tour endpoint with invalid data"""
        data = {"name": ""}

        response = self.client.post(self.url, data, format="json")

        self.assertEqual(response.status_code, status.HTTP_400_BAD_REQUEST)
        self.assertTrue("errors" in response.data)
        self.assertTrue("name" in response.data["errors"])

    def test_create_tour(self):
        """Test the create tour endpoint"""
        data = {"name": "Trip to the Moon"}

        response = self.client.post(self.url, data, format="json")

        self.assertEqual(response.status_code, status.HTTP_201_CREATED)
        self.assertEqual(Tour.objects.count(), 1)
        self.assertEqual(Tour.objects.get().name, "Trip to the Moon")
        self.assertTrue("data" in response.data)
        self.assertEqual(response.data["data"]["name"], "Trip to the Moon")
