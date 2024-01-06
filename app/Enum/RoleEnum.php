<?php

namespace App\Enum;

enum RoleEnum:int
{
    case SUPERADMIN=1;
    case ADMIN=2;
    case COMMERCIAL=3;
}
