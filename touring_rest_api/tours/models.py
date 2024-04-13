from django.db import models
from django_extensions.db.fields import AutoSlugField
from django_extensions.db.models import TimeStampedModel


# Create your models here.
class Tour(TimeStampedModel, models.Model):
    """Model definition for Tour."""

    name = models.CharField(max_length=255, unique=True, blank=False, null=False)
    slug = AutoSlugField(populate_from="name", overwrite=True)

    def __str__(self):
        """Unicode representation of Tour."""
        return self.name
