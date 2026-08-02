<?php

namespace App\Enums;

enum UserRole: string
{
    case USER = 'user';
    case GUIDE = 'guide';
    case LEAD_GUIDE = 'lead-guide';
    case ADMIN = 'admin';
}
