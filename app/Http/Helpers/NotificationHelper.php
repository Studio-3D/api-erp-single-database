<?php

namespace  App\Http\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;


class NotificationHelper
{
    public static function storeNotification(Request $request)
    {

        $notif = new Notification();
        $notif->setConnection('temp');
        $notif->lien= $request->lien;
        $notif->date= $request->date;
        $notif->type= $request->type;
        $notif->description_type=$request->description;
        $notif->user_id= $request->user_id;
        $notif->role= $request->role;
        $notif->visite_id= $request->visite_id;
        $notif->prospect_id= $request->prospect_id;
        $notif->projet_id= $request->projet_id;
        $notif->avance_id= $request->avance_id;
        $notif->reservation_id= $request->reservation_id;
        // Set bien_id to null if it's 0 or empty, otherwise use the provided value
        $notif->bien_id = (!empty($request->bien_id) && $request->bien_id != 0)
        ? $request->bien_id
        : null;
        $notif->traite_appel_id= $request->traite_appel_id;
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
