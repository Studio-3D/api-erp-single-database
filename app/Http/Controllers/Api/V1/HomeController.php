<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Models\Notification;
use App\Models\Visite;
use App\Models\TraitementAppel;
use App\Models\Reservation;
use App\Models\Desistement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Enum\RoleEnum;
use App\Models\User;
use App\Models\Objectif;


class HomeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function fullcalendar(Request $request,$projet_id,$user_id)
    {
        if (Auth::guard('api')->check() && RoleHelper::ACSup()) {
            DatabaseHelper::Config();
          //->whereDate('date', '>=', Carbon::now())
            $obj_mois_appels=0;
            $obj_mois_visites=0;
            $obj_mois_reservations=0;

            if(RoleHelper::AdminSup()){

                if($user_id==0){

                    $query_notif=Notification::on('temp')->with('projet')
                        ->where('type','!=',3)
                        ->where('type','!=',6)
                        ->where('type','!=',7)
                        ->where('type','!=',22)

                        ->where(function ($query) {
                        $query->where('role',RoleEnum::ADMIN->value)
                            ->orwhere('user_id',Auth::guard('api')->user()->id)
                        ;})
                        ->orderBy('id','desc')->withTrashed();
                        if ($projet_id!=0) {
                            $query_notif->where('projet_id',  $projet_id );
                        }
                    $data = $query_notif->get();

                    $query_v_now=Visite::on('temp')
                    ->whereDate('created_at', Carbon::now());
                    if ($projet_id!=0) {
                        $query_v_now->where('projet_id',  $projet_id );
                    }
                    $nb_visites_now = $query_v_now->count();


                    $query_appel_now=TraitementAppel::on('temp')->with('appel')
                    ->whereDate('date', Carbon::now());
                    if ($projet_id!=0) {
                        $query_appel_now->whereHas('appel', function ($q) use ($projet_id) {
                            $q->where('projet_id',$projet_id)
                                  ;
                       });
                    }
                    $nb_appels_now = $query_appel_now->count();

                    $query_res_now=Reservation::on('temp')
                    ->whereDate('created_at', Carbon::now())
                    ->where('etat',1);
                    if ($projet_id!=0) {
                        $query_res_now->where('projet_id',  $projet_id );
                    }
                    $nb_reservations_now=$query_res_now->count();

                    $query_des_now=Desistement::on('temp')
                    ->whereDate('created_at', Carbon::now());
                    if ($projet_id!=0) {
                        $query_des_now->where('projet_id',  $projet_id );
                    }
                    $nb_des_now=$query_des_now->count();

                    $nb_visites_mois=0;
                    $nb_appels_mois=0;
                    $nb_reservations_mois=0;

                }else{
                    $us=User::on('temp')->where('user_id_origin',$user_id)->first();
                    $us_id=$us->id;
                    //search par user_id not origin
                    $query_notif=Notification::on('temp')->with('projet')
                    ->where('type','!=',3)
                    ->where('type','!=',6)
                    ->where('type','!=',7)
                    ->where('type','!=',22)
                    ->where('user_id',$user_id)->orderBy('date','desc')->withTrashed();
                        if ($projet_id!=0) {
                            $query_notif->where('projet_id',  $projet_id );
                        }
                    $data = $query_notif->get();
                    /*****************************Now************************** */

                    $query_v_now=Visite::on('temp')
                    ->whereDate('created_at', Carbon::now())
                    ->where('user_id',$us_id);
                    if ($projet_id!=0) {
                        $query_v_now->where('projet_id',  $projet_id );
                    }
                    $nb_visites_now = $query_v_now->count();

                    $query_appel_now=TraitementAppel::on('temp')->with('appel')->whereDate('date', Carbon::now())->where('user_id',$us_id);
                    if ($projet_id!=0) {
                        $query_appel_now->whereHas('appel', function ($q) use ($projet_id) {
                            $q->where('projet_id',$projet_id)
                                  ;
                       });
                    }
                    $nb_appels_now = $query_appel_now->count();

                    $query_res_now=Reservation::on('temp')
                    ->whereDate('created_at', Carbon::now())
                    ->where('etat',1)->where('user_id',$us_id);
                    if ($projet_id!=0) {
                        $query_res_now->where('projet_id',  $projet_id );
                    }
                    $nb_reservations_now=$query_res_now->count();

                    $query_des_now=Desistement::on('temp')
                    ->whereDate('created_at', Carbon::now())
                    ->where('user_id',$us_id);
                    if ($projet_id!=0) {
                        $query_des_now->where('projet_id',  $projet_id );
                    }
                    $nb_des_now=$query_des_now->count();

                    /********************************Mois****************/
                    $query_v_mois=Visite::on('temp')
                    ->whereMonth('created_at', Carbon::now()->month)
                    ->where('user_id',$us_id);
                    if ($projet_id!=0) {
                        $query_v_mois->where('projet_id',  $projet_id );
                    }
                    $nb_visites_mois = $query_v_mois->count();


                    $query_appel_mois=TraitementAppel::on('temp')->with('appel')->whereMonth('date', Carbon::now()->month)->where('user_id',$us_id);
                    if ($projet_id!=0) {
                        $query_appel_now->whereHas('appel', function ($q) use ($projet_id) {
                            $q->where('projet_id',$projet_id)
                                  ;
                       });
                    }
                    $nb_appels_mois=$query_appel_mois->count();


                    $query_res_mois=Reservation::on('temp')
                    ->whereMonth('created_at', Carbon::now()->month)
                    ->where('etat',1)->where('user_id',$us_id);
                    if ($projet_id!=0) {
                        $query_res_mois->where('projet_id',  $projet_id );
                    }
                    $nb_reservations_mois=$query_res_mois->count();


                    /***** */

                    $query_obj=Objectif::on('temp')->where('user_id',$us_id);
                    if ($projet_id!=0) {
                        $query_obj->where('projet_id',  $projet_id );
                    }
                    $obj=$query_obj->first();
                    if($obj!=null){
                        if($obj->appels!=null){
                            $obj_mois_appels=$obj->appels['mois'];
                         }
                         if($obj->visites!=null){
                             $obj_mois_visites=$obj->visites['mois'];
                          }
                          if($obj->reservations!=null){
                             $obj_mois_reservations=$obj->reservations['mois'];
                          }
                    }

                }


            }else{
                $query_notif=Notification::on('temp')->with('projet')->where('type','!=',3)
                ->where('type','!=',6)
                ->where('type','!=',7)
                ->where('type','!=',22)
                ->where('type','!=',11)
                ->where('type','!=',12)->where('type','!=',16)->where('type','!=',18)
                ->where('type','!=',13)->where('type','!=',14)
                ->where('type','!=',15)
                ->where('user_id',Auth::guard('api')->user()->id)->orderBy('date','desc')->withTrashed();
                if ($projet_id!=0) {
                    $query_notif->where('projet_id',  $projet_id );
                }
                $data=$query_notif->get();
                $us=User::on('temp')->where('user_id_origin',Auth::guard('api')->user()->id)->first();
                    $us_id=$us->id;
                /******************************Now****************** */

                $query_v_now=Visite::on('temp')
                    ->whereDate('created_at', Carbon::now())
                    ->where('user_id',$us_id);
                    if ($projet_id!=0) {
                        $query_v_now->where('projet_id',  $projet_id );
                    }
                $nb_visites_now = $query_v_now->count();

                $query_appel_now=TraitementAppel::on('temp')->with('appel')->whereDate('date', Carbon::now())->where('user_id',$us_id);
                if ($projet_id!=0) {
                    $query_appel_now->whereHas('appel', function ($q) use ($projet_id) {
                        $q->where('projet_id',$projet_id)
                              ;
                   });
                }
                $nb_appels_now = $query_appel_now->count();

                $query_res_now=Reservation::on('temp')
                ->whereDate('created_at', Carbon::now())
                ->where('etat',1)->where('user_id',$us_id);
                if ($projet_id!=0) {
                    $query_res_now->where('projet_id',  $projet_id );
                }
                $nb_reservations_now=$query_res_now->count();

                $query_des_now=Desistement::on('temp')
                ->whereDate('created_at', Carbon::now())
                ->where('user_id',$us_id);
                if ($projet_id!=0) {
                    $query_des_now->where('projet_id',  $projet_id );
                }
                $nb_des_now=$query_des_now->count();
                /********************Month************************* */

                $query_v_mois=Visite::on('temp')
                    ->whereMonth('created_at', Carbon::now()->month)
                    ->where('user_id',$us_id);
                    if ($projet_id!=0) {
                        $query_v_mois->where('projet_id',  $projet_id );
                    }
                $nb_visites_mois = $query_v_mois->count();

                $query_appel_mois=TraitementAppel::on('temp')->with('appel')->whereMonth('date', Carbon::now()->month)->where('user_id',$us_id);
                    if ($projet_id!=0) {
                        $query_appel_now->whereHas('appel', function ($q) use ($projet_id) {
                            $q->where('projet_id',$projet_id)
                                  ;
                       });
                    }
                $nb_appels_mois=$query_appel_mois->count();


                $query_res_mois=Reservation::on('temp')
                    ->whereMonth('created_at', Carbon::now()->month)
                    ->where('etat',1)->where('user_id',$us_id);
                    if ($projet_id!=0) {
                        $query_res_mois->where('projet_id',  $projet_id );
                    }
                $nb_reservations_mois=$query_res_mois->count();


                /**** */
                $query_obj=Objectif::on('temp')->where('user_id',$us_id);
                    if ($projet_id!=0) {
                        $query_obj->where('projet_id',  $projet_id );
                    }
                $obj=$query_obj->first();
                if($obj!=null){
                    if($obj->appels!=null){
                        $obj_mois_appels=$obj->appels['mois'];
                     }
                 if($obj->visites!=null){
                         $obj_mois_visites=$obj->visites['mois'];
                      }
                 if($obj->reservations!=null){
                         $obj_mois_reservations=$obj->reservations['mois'];
                      }
                }

            }

           return response()->json([
           'data' => $data,'nb_visites_now'=>$nb_visites_now,
           'nb_appels_now'=>$nb_appels_now,'nb_reservations_now'=>$nb_reservations_now,
           'nb_des_now'=>$nb_des_now,'obj_mois_appels'=>$obj_mois_appels,'obj_mois_visites'=>$obj_mois_visites,'obj_mois_reservations'=>$obj_mois_reservations,
           'nb_visites_month'=>$nb_visites_mois,'nb_reservations_month'=>$nb_reservations_mois,'nb_appels_month'=>$nb_appels_mois
        ]);
        }
         else{
            return response()->json(['error' => 'Unauthorized'], 401);
         }

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */


    /**
     * Display the specified resource.
     */


    /**
     * Show the form for editing the specified resource.
     */




}
