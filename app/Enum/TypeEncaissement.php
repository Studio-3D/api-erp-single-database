<?php

namespace App\Enum;

enum TypeEncaissement:int
{
    case Avance=1;
    case Restitution=2;
    case Remboursement=3;
    case Décharge=4;
    case Déblocage_Crédit=5;
    case Penalites=6;
}
