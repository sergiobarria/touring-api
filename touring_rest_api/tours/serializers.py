from rest_framework import serializers

from tours.models import Tour


class TourSerializer(serializers.ModelSerializer):
    """Serializer for the Tour model."""

    class Meta:
        model = Tour
        fields = "__all__"
        read_only = ["slug", "created", "modified"]
