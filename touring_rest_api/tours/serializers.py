from rest_framework import serializers

from tours.models import Tour


class TourListSerializer(serializers.ModelSerializer):
    """Serializer for the tour list view"""

    class Meta:
        model = Tour
        exclude = ["summary", "description", "start_dates", "created", "modified"]
        read_only = ["slug", "created", "modified"]


class TourDetailSerializer(serializers.ModelSerializer):
    """Serializer for detailed tour view"""

    class Meta:
        model = Tour
        fields = "__all__"
        read_only = ["slug", "created", "modified"]
