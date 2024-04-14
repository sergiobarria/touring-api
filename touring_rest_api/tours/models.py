from django.core.validators import MaxLengthValidator, MinLengthValidator
from django.db import models
from django_extensions.db.fields import AutoSlugField
from django_extensions.db.models import TimeStampedModel


# Create your models here.
class Tour(TimeStampedModel, models.Model):
    """Model definition for Tour."""

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

    def __str__(self):
        """Unicode representation of Tour."""
        return self.name
