<?php

namespace  App\Http\Helpers;

use Illuminate\Support\Facades\Auth;
use App\Models\Notification;


class NotificationHelper
{
    public static function storeNotification($lien, $date_relance, $type, $description_type,$user_id,$visite_id,$prospect_id,$projet_id)
    {

        $notif = new Notification();
        $notif->setConnection('temp');
        $notif->lien= $lien;
        $notif->date= $date_relance;
        $notif->type= $type;
        $notif->description_type=$description_type;
        $notif->user_id= $user_id;
        $notif->visite_id= $visite_id;
        $notif->prospect_id= $prospect_id;
        $notif->projet_id= $projet_id;
        $notif->save();
    }
}
