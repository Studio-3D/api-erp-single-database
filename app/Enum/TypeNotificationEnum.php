<?php

namespace App\Enum;

enum TypeNotificationEnum:int
{
   case Sms=1;
   case Appel=2;
   case Email=3;
   case FacebookReaction=98;
   case FacebookLead=99;
   case InstagramComment=97;
   case FacebookPublication=96;
   case InstagramReaction=95;
   case InstagramMention=94;
   case FacebookComment=93;
   case InstagramPublication=92;
   case FacebookMention=91;
   case FacebookMessage=90;
   case InstagramMessage=100;
    case WhatsAppMessage = 50;     // Nouveau message WhatsApp
    case WhatsAppProspect = 51;    // Nouveau prospect WhatsApp
}
