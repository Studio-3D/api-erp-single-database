<?php

namespace App\Enum;

use Illuminate\Validation\Rules\Enum;

Enum StatutReservationEnum:int
{
    case VALIDER=1;
    case REFUSER=2;

    case EN_ATTENTE=3;

    case ANNULLER=4;
}
