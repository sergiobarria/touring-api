from django.contrib import admin

from .models import Tour


class TourAdmin(admin.ModelAdmin):
    """Admin definition for Tour."""

    list_display = ("name", "slug", "created", "modified")
    search_fields = ("name", "slug")
    list_filter = ("created", "modified")


# Register your models here.
admin.site.register(Tour, TourAdmin)
