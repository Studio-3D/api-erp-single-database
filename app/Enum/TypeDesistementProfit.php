<?php

namespace App\Enum;

enum TypeDesistementProfit:int
{
    case Désistement_AU_PROFIT_UN_PROCHE=1;
    case Désistement_AU_PROFIT_UN_CO_RESERVATAIRE=2;
    case Désistement_Partiel=3;
}
