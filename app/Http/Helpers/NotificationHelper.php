<?php

namespace  App\Http\Helpers;

use Illuminate\Support\Facades\Auth;
use App\Models\Notification;


class NotificationHelper
{
    public static function storeNotification($lien, $date_relance, $type, $description_type,$user_id,$role,$visite_id,$prospect_id,$projet_id,$avance_id,$reservation_id)
    {

        $notif = new Notification();
        $notif->setConnection('temp');
        $notif->lien= $lien;
        $notif->date= $date_relance;
        $notif->type= $type;
        $notif->description_type=$description_type;
        $notif->user_id= $user_id;
        $notif->role= $role;
        $notif->visite_id= $visite_id;
        $notif->prospect_id= $prospect_id;
        $notif->projet_id= $projet_id;
        $notif->avance_id= $avance_id;
        $notif->reservation_id= $reservation_id;
        $notif->save();
    }
    public static function destroy_notif_bien_dispo_frein($visite_id){
        $notifications_bien=Notification::on('temp')->where('visite_id',$visite_id)->where('type',3)->get();
        if(count($notifications_bien)>0){
            foreach($notifications_bien as $nt){
                $nt->delete();
            }
        }
    }
}
