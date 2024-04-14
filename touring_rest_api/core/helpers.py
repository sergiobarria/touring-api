from datetime import datetime
from random import randint, uniform

from django.utils import timezone


def generate_random_increment(start, end, increment):
    num = uniform(start, end)
    return round(num / increment) * increment


def generate_random_tour_data(step=1):
    """Generate random tour data for testing."""

    month = randint(1, 12)
    day = randint(1, 28)
    year = 2024

    return {
        "name": f"Tour {step}",
        "price": 100 * step,
        "ratings_average": generate_random_increment(1, 5, 0.1),
        "ratings_quantity": randint(1, 1000),
        "difficulty": "easy" if step % 3 == 0 else "medium" if step % 3 == 1 else "hard",
        "start_dates": [timezone.make_aware(datetime(year, month, day))],
        "duration": randint(1, 10),
        "max_group_size": randint(1, 10),
    }
