from django.core.exceptions import ValidationError
from django.test import TestCase

from tours.models import Tour


class TourModelTest(TestCase):
    """Test the Tour model"""

    def setUp(self):
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
