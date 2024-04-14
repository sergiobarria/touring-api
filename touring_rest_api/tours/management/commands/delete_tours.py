"""WARNING: This command deletes all tours from the database."""

from core.config import env
from django.core.management.base import BaseCommand
from django.utils.termcolors import colorize
from tours.models import Tour


class Command(BaseCommand):
    """Command definition for delete_tours."""

    help = "Deletes all tours from the database."

    def handle(self, *args, **options):
        """Handle the command."""

        # If not in development mode, send a warning message
        if not env("DEBUG"):
            print(colorize("💥 WARNING: This command will delete all tours from the database.", fg="red"))
            print(colorize("This action cannot be undone.", fg="red"))
            print(colorize("Are you sure you want to proceed?", fg="red"))
            proceed = input("Type 'yes' to proceed: ")

            if proceed != "yes":
                print(colorize("Aborting...", fg="red"))
                return

        count = Tour.objects.count()
        Tour.objects.all().delete()
        self.stdout.write(self.style.SUCCESS(f"✅ Successfully deleted {count} tours."))
