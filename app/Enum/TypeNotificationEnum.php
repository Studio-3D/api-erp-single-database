<?php

namespace App\Enum;

enum TypeNotificationEnum:int
{
   case SMS=1;
   case APPEL=2;
   case EMAIL=3;
}
