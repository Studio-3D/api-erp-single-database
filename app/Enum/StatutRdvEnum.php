<?php

namespace App\Enum;

use Illuminate\Validation\Rules\Enum;

Enum StatutRdvEnum:int
{
    case En_Attente=1;
    case traite=2;
    case non_traite=3;
    case annule_automatique=4;
}
