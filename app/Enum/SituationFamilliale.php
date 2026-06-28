<?php

namespace App\Enum;

enum SituationFamilliale:int
{
    case Célibataire=1;
    case Marié=2;
    case Divorcé=3;
    case Veuf=4;
    case Non_renseigné = 5;  // Added this - user didn't select/not specified

}
