<?php

namespace App\Enums;

enum TourPermission: string
{
    case CREATE = 'tours.create';
    case UPDATE = 'tours.update';
    case DELETE = 'tours.delete';
    case MANAGE_IMAGES = 'tours.manage-images';
    case MANAGE_START_DATES = 'tours.manage-start-dates';
    case VIEW_ANALYTICS = 'tours.view-analytics';
}
