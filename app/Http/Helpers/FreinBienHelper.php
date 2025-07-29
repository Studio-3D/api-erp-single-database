<?php

namespace App\Http\Helpers;

use App\Models\Frein_Bien;
use App\Models\Frein;
use App\Models\Notification;
use App\Http\Helpers\NotificationHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use App\Events\NotificationEvent;
use App\Events\NotifMenuEvent;
class FreinBienHelper
{
    public static function createFreinBien($bien_id,$frein_id){
        $frein_bien=new Frein_Bien();
        $frein_bien->setConnection('temp');
        $frein_bien->bien_id=$bien_id;
        $frein_bien->frein_id=$frein_id;
        if($frein_bien->save()){
            $frein=Frein::on('temp')->findOrFail($frein_bien->frein_id);
            $frein->etat=2;//exit bien disponible convenable a ce frein
            if($frein->save()){
                //store notification bien disponible
                $notifications_bien_count=Notification::on('temp')->where('visite_id',$frein->visite_id)->where('type',3)->count();
                if($notifications_bien_count==0){

                Config::set('broadcasting.default', 'pusher_3');
                $data_notif = [
                    'lien' =>  '/crm/visites/freins',
                    'date' => Carbon::now(),
                    'type' => 3,
                    'description' => 'Bien Disponible Frein',
                    'role'=>$frein->visite->user->role,
                    'user_id'=>$frein->visite->user->user_id_origin,
                    'visite_id'=>$frein->visite_id,
                    'prospect_id'=>$frein->visite->prospect_id,
                    'projet_id'=>$frein->visite->projet_id

                ];
                $notif_helper = new NotificationHelper();
                $request = new \Illuminate\Http\Request();
                $notif_helper->storeNotification($request->merge($data_notif));
                broadcast(new NotificationEvent($frein_id));
                    Config::set('broadcasting.default', 'pusher_5');
                         //1 traitement reservation
                broadcast(new NotifMenuEvent('C'));
                }
            }

        }
    }

    public static function destroyFreinBien($frein_id){
        $frein_bien=Frein_Bien::on('temp')->where('frein_id',$frein_id)->get();
        if(count($frein_bien)>0){
                foreach($frein_bien as $fr){
                    $fr->forceDelete();
                }
        }
    }
}
