from django.contrib import admin

from .models import Tour


class TourAdmin(admin.ModelAdmin):
    """Admin definition for Tour."""

    list_display = ("name", "slug", "price", "difficulty", "ratings_average", "created", "modified")
    search_fields = ("name", "slug")
    list_filter = ("created", "modified", "difficulty", "price")


# Register your models here.
admin.site.register(Tour, TourAdmin)
