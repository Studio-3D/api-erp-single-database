<?php

namespace App\Enum;

enum StatutVisiteEnum:int
{
   case PRE_RESERVE=1;
   case VENDU=2;
   case PRE_RESERVATION_PERDU=3;
}
