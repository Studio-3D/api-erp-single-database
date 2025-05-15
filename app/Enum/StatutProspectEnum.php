<?php

namespace App\Enum;

use Illuminate\Validation\Rules\Enum;

Enum StatutProspectEnum:int
{
    case Planification_rdv=1;
    case Injoignable=2;
    case Rappel=3;
    case Convert_visite=4;
    case nv_appel=5;
}
