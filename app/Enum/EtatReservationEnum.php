<?php

namespace App\Enum;

use Illuminate\Validation\Rules\Enum;

Enum EtatReservationEnum:int
{
    case active=1;
    case desist_definitif=2;
    case desist_profit_proche=3;
    case desist_profit_co=4;
    case desist_partiel=5;
    case desist_change_bien=6;

}
