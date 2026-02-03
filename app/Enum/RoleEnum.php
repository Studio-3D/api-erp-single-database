<?php

namespace App\Enum;

enum RoleEnum:int
{
    case SUPERADMIN=1;
    case ADMIN=2;
    case COMMERCIAL=3;
    case ADMIN_COMMERCIAL=4;
    case NOTAIRE=5;
    case RESPO_LIVRAISON=6;
    case COMPTABLE=7;
    case SAV=8;


}
