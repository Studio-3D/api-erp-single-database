<?php

namespace App\Enum;

enum ModePaiement:int
{
    case Espèce=1;
    case Chèque=2;
    case Chèque_Banque=3;
    case Chèque_Certifié=4;
    case Virement=5;
    case Versement=6;
    case transfert_dossier=7;
}
