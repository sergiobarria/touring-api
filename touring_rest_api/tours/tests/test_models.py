from django.core.exceptions import ValidationError
from django.test import TestCase

from touring_rest_api.api.helpers import generate_random_tour_data
from tours.models import Tour


class TourModelTest(TestCase):
    """Test the Tour model"""

    def setUp(self):
        self.sample_tour_data = generate_random_tour_data()

    def test_tour_name_validators(self):
        """Test the Tour model validators"""
        with self.assertRaises(ValidationError):
            tour = Tour(**{**self.sample_tour_data, "name": "A"})  # Name is too short
            tour.full_clean()

        with self.assertRaises(ValidationError):
            tour = Tour(**{**self.sample_tour_data, "name": "A" * 101})  # Name is too long
            tour.full_clean()

        # Test for valid name
        tour = Tour(**{**self.sample_tour_data, "name": "The Sahara Tour"})
        try:
            tour.full_clean()
        except ValidationError:
            self.fail("Tour name is valid but raised ValidationError exception unexpectedly.")

    def test_tour_creation(self):
        """Test the Tour model creation"""
        tour = Tour.objects.create(**self.sample_tour_data)

        self.assertEqual(tour.name, self.sample_tour_data["name"])
        self.assertTrue(tour.slug)
        self.assertNotEqual(tour.created, None)

    def test_tour_string_representation(self):
        """Test the Tour model string representation"""
        tour = Tour.objects.create(**self.sample_tour_data)

        self.assertEqual(str(tour), self.sample_tour_data["name"])
