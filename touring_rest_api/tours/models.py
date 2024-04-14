from django.contrib.postgres.fields import ArrayField
from django.core.validators import MaxLengthValidator, MinLengthValidator
from django.db import models
from django_extensions.db.fields import AutoSlugField
from django_extensions.db.models import TimeStampedModel


# Create your models here.
class Tour(TimeStampedModel, models.Model):
    """Model definition for Tour."""

    DIFFICULTY_CHOICES = [
        ("easy", "Easy"),
        ("medium", "Medium"),
        ("difficult", "Difficult"),
    ]

    name = models.CharField(
        max_length=255,
        unique=True,
        blank=False,
        null=False,
        validators=[
            MinLengthValidator(2, "Tour name must be at least 2 characters long"),
            MaxLengthValidator(100, "Tour name must be at most 100 characters long"),
        ],
    )
    slug = AutoSlugField(populate_from="name", overwrite=True)
    duration = models.PositiveIntegerField(blank=False, null=False)
    max_group_size = models.PositiveIntegerField(blank=False, null=False)
    difficulty = models.CharField(max_length=10, choices=DIFFICULTY_CHOICES, default="medium")
    price = models.PositiveIntegerField(blank=False, null=False)
    ratings_average = models.FloatField(blank=True, null=True)
    ratings_quantity = models.IntegerField(blank=True, null=True, default=0)
    summary = models.TextField(
        null=True,
        blank=True,
        validators=[
            MinLengthValidator(10, "Tour summary must be at least 10 characters long"),
            MaxLengthValidator(1000, "Tour summary must be at most 1000 characters long"),
        ],
    )
    description = models.TextField(
        null=True,
        blank=True,
        validators=[
            MinLengthValidator(20, "Tour description must be at least 20 characters long"),
            MaxLengthValidator(10000, "Tour description must be at most 10000 characters long"),
        ],
    )
    start_dates = ArrayField(models.DateTimeField(), blank=True, null=True, default=list)

    def __str__(self):
        """Unicode representation of Tour."""
        return self.name
