from django.test import TestCase
from tours.models import Tour


class TourModelTest(TestCase):
    """Test the Tour model"""

    def test_tour_creation(self):
        """Test the Tour model creation"""
        tour = Tour.objects.create(name="The Sahara Tour")

        self.assertEqual(tour.name, "The Sahara Tour")
        self.assertTrue(tour.slug)
        self.assertNotEqual(tour.created, None)

    def test_tour_string_representation(self):
        """Test the Tour model string representation"""
        tour = Tour.objects.create(name="The Sahara Tour")

        self.assertEqual(str(tour), "The Sahara Tour")
