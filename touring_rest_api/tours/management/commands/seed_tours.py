"""This command seeds the database with tours data."""

import json
import os

from core.settings.base import BASE_DIR
from django.core.management.base import BaseCommand
from tours.models import Tour


class Command(BaseCommand):
    """Command definition for seed_tours."""

    help = "Seeds the database with tours data."

    def handle(self, *args, **options):
        """Handle the command."""

        # Get the path to the tours-simple.json file at the root of the project
        # NOTE: Without the '..' in the path, it points to the django project root: touring_rest_api/tours-simple.json.
        # We need to go up one level to the root of the project.
        file_path = os.path.join(BASE_DIR, "..", "data", "tours-simple.json")

        # Open the file and load the JSON data
        with open(file_path, "r") as file:
            tours = json.load(file)

        for tour_data in tours:
            # 1. Remove the following fields from the tour: id, image_cover, images
            tour_data.pop("id")
            tour_data.pop("image_cover")
            tour_data.pop("images")

            # 2. Create a new Tour object for each tour in the JSON file
            tour = Tour.objects.create(**tour_data)

            # 3. Save the Tour object
            tour.save()

            # 4. Print a success message
            self.stdout.write(self.style.SUCCESS(f"Successfully seeded tour: {tour.name}."))

        count = Tour.objects.count()
        self.stdout.write(self.style.SUCCESS(f"✅ Successfully seeded the database with {count} tours."))
