<?php

namespace App\Enum;

enum LienParente:int
{
    case Parents=1;
    case Fils=2;
    case Fréres=3;
    case Soeurs=4;
    case Autre=5;
}
