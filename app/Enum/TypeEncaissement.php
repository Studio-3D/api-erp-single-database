<?php

namespace App\Enum;

enum TypeEncaissement:int
{
    case AVANCE=1;
    case RESTITUTION=2;
    case REMBOURSEMENT=3;
    case DECHARGE=4;
    case DEBLOCAGE=5;
}
