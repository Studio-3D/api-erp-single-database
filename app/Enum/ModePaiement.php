<?php

namespace App\Enum;

enum ModePaiement:int
{
    case ESPECE=1;
    case CHEQUE=2;
    case CHEQUE_BANQUE=3;
    case CHEQUE_CERTIFIE=4;
    case VIREMENT=5;
    case VERSEMENT=6;
}
