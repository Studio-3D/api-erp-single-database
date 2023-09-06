<?php

namespace App\Enum;

enum EtatBien:int
{
     case DISPONIBLE=1;
     case PRE_RESERVATION=2;
     case RESERVATION=3;
     case BLOQUE=4;
     case VENDU=5;
     case ENCOURS_DE_PROPOSITION=6;

}
