<?php

namespace App\Enum;

enum StatutVisiteEnum:int
{
   case PRE_RESERVATION=1;
   case VENDU=2;
   case PRE_RESERVATION_PERDU=3;
   case RESERVATION_PERDU=4;
}
