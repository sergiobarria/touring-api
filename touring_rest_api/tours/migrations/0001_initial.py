# Generated by Django 5.0.4 on 2024-04-14 04:58

import django.contrib.postgres.fields
import django.core.validators
import django_extensions.db.fields
from django.db import migrations, models


class Migration(migrations.Migration):

    initial = True

    dependencies = [
    ]

    operations = [
        migrations.CreateModel(
            name='Tour',
            fields=[
                ('id', models.BigAutoField(auto_created=True, primary_key=True, serialize=False, verbose_name='ID')),
                ('created', django_extensions.db.fields.CreationDateTimeField(auto_now_add=True, verbose_name='created')),
                ('modified', django_extensions.db.fields.ModificationDateTimeField(auto_now=True, verbose_name='modified')),
                ('name', models.CharField(max_length=255, unique=True, validators=[django.core.validators.MinLengthValidator(2, 'Tour name must be at least 2 characters long'), django.core.validators.MaxLengthValidator(100, 'Tour name must be at most 100 characters long')])),
                ('slug', django_extensions.db.fields.AutoSlugField(blank=True, editable=False, overwrite=True, populate_from='name')),
                ('duration', models.PositiveIntegerField()),
                ('max_group_size', models.PositiveIntegerField()),
                ('difficulty', models.CharField(choices=[('easy', 'Easy'), ('medium', 'Medium'), ('difficult', 'Difficult')], default='medium', max_length=10)),
                ('price', models.PositiveIntegerField()),
                ('ratings_average', models.FloatField(blank=True, null=True)),
                ('ratings_quantity', models.IntegerField(blank=True, default=0, null=True)),
                ('summary', models.TextField(blank=True, null=True, validators=[django.core.validators.MinLengthValidator(10, 'Tour summary must be at least 10 characters long'), django.core.validators.MaxLengthValidator(1000, 'Tour summary must be at most 1000 characters long')])),
                ('description', models.TextField(blank=True, null=True, validators=[django.core.validators.MinLengthValidator(20, 'Tour description must be at least 20 characters long'), django.core.validators.MaxLengthValidator(10000, 'Tour description must be at most 10000 characters long')])),
                ('start_dates', django.contrib.postgres.fields.ArrayField(base_field=models.DateTimeField(), blank=True, default=list, null=True, size=None)),
            ],
            options={
                'get_latest_by': 'modified',
                'abstract': False,
            },
        ),
    ]
