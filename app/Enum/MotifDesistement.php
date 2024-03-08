<?php

namespace App\Enum;

enum MotifDesistement:int
{
    case Incapacité_Financière=1;
    case Décès=2;
    case Problème_familier=3;
    case Mutation=4;
    case Licenciement=5;
    case Insatisfaction=6;
    case Echange=7;
    case Crédit_Bancaire_non_accordé=8;
    case Client_imposé_à_la_TSC=9;
    case Autre_investissement=10;
    case Probléme_de_santé=11;
}

