<?php

namespace App\Enums;

enum UserPermission: string
{
    case VIEW_ANY = 'users.view-any';
    case VIEW = 'users.view';
    case CREATE = 'users.create';
    case UPDATE_ROLE = 'users.update-role';
    case DELETE = 'users.delete';
}
