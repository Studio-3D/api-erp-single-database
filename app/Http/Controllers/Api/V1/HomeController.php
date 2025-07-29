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
use App\Models\Encaissement;
use App\Models\PenaliteDesistement;
use App\Models\Remboursement;
use App\Models\Client;
use App\Models\Prospect;
use App\Models\Avance;
use DB;
use App\Enum\StatutReservationEnum;
use App\Models\Bien;
use App\Models\Reclamation;
use App\Models\RemiseCle;

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
                    ->whereYear('created_at', Carbon::now()->year)
                    ->where('user_id',$us_id);
                    if ($projet_id!=0) {
                        $query_v_mois->where('projet_id',  $projet_id );
                    }
                    $nb_visites_mois = $query_v_mois->count();


                    $query_appel_mois=TraitementAppel::on('temp')->with('appel')->whereMonth('date', Carbon::now()->month)->whereYear('date', Carbon::now()->year)->where('user_id',$us_id);
                    if ($projet_id!=0) {
                        $query_appel_now->whereHas('appel', function ($q) use ($projet_id) {
                            $q->where('projet_id',$projet_id)
                                  ;
                       });
                    }
                    $nb_appels_mois=$query_appel_mois->count();


                    $query_res_mois=Reservation::on('temp')
                    ->whereMonth('created_at', Carbon::now()->month)
                    ->whereYear('created_at', Carbon::now()->year)
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
    public function dashboard(Request $request,$projet_id,$de_date,$a_date)
    {
        DatabaseHelper::Config();
        $user = Auth::user();
        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
        $obj_mois_appels=0;
        $obj_mois_visites=0;
        $obj_mois_reservations=0;
        if (Auth::guard('api')->check() && RoleHelper::ACSup()) {
            $us_id=$userAuth->value('id');
            $us_id_origin=$userAuth->value('user_id_origin');
            $us_role=$userAuth->value('role');

            $dt=null;
            $a_dt=null;
            if($de_date!="null" && $a_date!="null" ){
                $dt =  Carbon::parse($de_date)->format('Y-m-d');
                $a_dt = Carbon::parse($a_date)->format('Y-m-d');
            }



            if($projet_id=="null"){
                $projet_id=null;
            }
            /*********************Encaissement***************************/

                $query_en=Encaissement::on('temp')->with('reservations','avance','remboursement')
                ->whereHas('reservations', function ($q)  {
                    $q->where('etat', 1);
                })
                ->where(function($query) {
                    $query->where('type_encaissement',1)
                    ->orwhere('type_encaissement',6);
                });
                if($dt==null && $a_dt==null){
                    $query_en ->whereYear('date_reglement', Carbon::now()->year)->whereMonth('date_reglement', Carbon::now()->month);
                }else{
                    $query_en->whereBetween('date_reglement',[$dt,$a_dt]);
                }

                if($projet_id!=null){
                    $query_en->whereHas('reservations', function ($q) use ($projet_id) {
                        $q->where('projet_id', $projet_id);
                    });
                }
                //comercial
                if($us_role==3){
                    $query_en->where(function($query_n) use($us_id) {
                        $query_n->whereHas('avance', function ($q_com) use ($us_id) {
                            $q_com->where('user_id', $us_id);
                        });
                        $query_n->orwhereHas('remboursement.desistement', function ($q_remb) use ($us_id) {
                            $q_remb->where('user_id', $us_id);
                        });
                    });
                }
                $sum_encaissements=$query_en->sum('montant');
                //array encaissement nb by date

                $query=Encaissement::on('temp')->with('reservations','avance','remboursement')
                ->select(DB::raw("DATE(encaissements.date_reglement) as day, sum(encaissements.montant) as montant"))
                ->where('encaissements.deleted_at', null)
                ->whereHas('reservations', function ($q)  {
                    $q->where('etat', 1);
                })
                ->where(function($query) {
                    $query->where('type_encaissement',1)
                    ->orwhere('type_encaissement',6);
                });
                if($dt==null && $a_dt==null){
                    $query ->whereYear('date_reglement', Carbon::now()->year)->whereMonth('date_reglement', Carbon::now()->month);
                }else{
                    $query->whereBetween('date_reglement',[$dt,$a_dt]);
                }

                if($projet_id!=null){
                    $query->whereHas('reservations', function ($q) use ($projet_id) {
                        $q->where('projet_id', $projet_id);
                    });
                }
                //comercial
                if($us_role==3){
                    $query->where(function($query_n) use($us_id) {
                        $query_n->whereHas('avance', function ($q_com) use ($us_id) {
                            $q_com->where('user_id', $us_id);
                        });
                        $query_n->orwhereHas('remboursement.desistement', function ($q_remb) use ($us_id) {
                            $q_remb->where('user_id', $us_id);
                        });
                    });
                }
                $encaissements = $query->groupBy(DB::raw("day"))->get();

                $array_encaissements=[];
                foreach ($encaissements as $data) {
                    $array_encaissements[] = [
                        date($data['day']),
                        (int) $data['montant']
                    ];
                }

            /*****************************penalites*************************/

            $query_penalite= PenaliteDesistement::on('temp')->with('desistement');
            if($dt==null && $a_dt==null){
                $query_penalite ->whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->month);
            }else{
                $query_penalite->whereBetween('created_at',[$dt,$a_dt]);
            }

            if($projet_id!=null){
                $query_penalite->whereHas('desistement', function ($q) use ($projet_id) {
                    $q->where('projet_id', $projet_id);
                });
            }
              //comercial
              if($us_role==3){
                $query_penalite->whereHas('desistement', function ($q_com) use ($us_id) {
                    $q_com->where('user_id', $us_id);
                });
            }

            $sum_penalites=$query_penalite->sum('montant');
            /******************************remboursments*****************************/

            $query_remb=Remboursement::on('temp')->with('desistement');
            if($dt==null && $a_dt==null){
                $query_remb ->whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->month);
            }else{
                $query_remb->whereBetween('created_at',[$dt,$a_dt]);
            }

            if($projet_id!=null){
                $query_remb->whereHas('desistement', function ($q) use ($projet_id) {
                    $q->where('projet_id', $projet_id);
                });
            }
              //comercial
              if($us_role==3){
                $query_remb->whereHas('desistement', function ($q) use ($us_id) {
                    $q->where('user_id', $us_id);
                });
            }
            $sum_remboursements=$query_remb->where('etat',1)->where('statut',1)->sum('montant_a_rembourser');

            /**************************** Visites ************************/


            $query_nb_visites=Visite::on('temp')->where('etat', 1);
            if($dt==null && $a_dt==null){
                $query_nb_visites ->whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->month);
            }else{
                $query_nb_visites->whereBetween('created_at',[$dt,$a_dt]);
            }

            if ($projet_id!=null) {
                $query_nb_visites->where('projet_id',  $projet_id );
            }
              //comercial
              if($us_role==3){
                $query_nb_visites->where('user_id', $us_id);
            }
            $nb_visites = $query_nb_visites->count();
            /*************************Appels entrant sortant par date********************* */
                $query_appels_date_type= TraitementAppel::on('temp')->with('appel');

                if($dt == null && $a_dt == null) {
                    $query_appels_date_type->whereYear('created_at', Carbon::now()->year)
                                ->whereMonth('created_at', Carbon::now()->month);
                } else {
                    $query_appels_date_type->whereBetween('created_at', [$dt, $a_dt]);
                }

                if ($projet_id != 0) {
                    $query_appels_date_type->whereHas('appel', function ($q) use ($projet_id) {
                        $q->where('projet_id', $projet_id);
                    });
                }

                // comercial filter
                if($us_role == 3) {
                    $query_appels_date_type->where('user_id', $us_id);
                }

                // Get all calls grouped by date and type
                $appels = $query_appels_date_type
                    ->select(
                        DB::raw("DATE(created_at) as date"),
                        'type_appel',
                        DB::raw("COUNT(*) as count")
                    )
                    ->groupBy(DB::raw("DATE(created_at)"), 'type_appel')
                    ->get();

                // Group data by date
                $groupedByDate = [];
                foreach ($appels as $appel) {
                    $date = $appel->date;
                    if (!isset($groupedByDate[$date])) {
                        $groupedByDate[$date] = [
                            'appel entrant' => 0,
                            'appel sortant' => 0
                        ];
                    }

                    if ($appel->type_appel == 1) {
                        $groupedByDate[$date]['appel entrant'] = (int)$appel->count;
                    } elseif ($appel->type_appel == 2) {
                        $groupedByDate[$date]['appel sortant'] = (int)$appel->count;
                    }
                }

                // Format the final array of objects
                $array_appels_par_date = [];
                foreach ($groupedByDate as $date => $counts) {
                    // Format date as DD-MM-YYYY (assuming date is YYYY-MM-DD)
                    $formattedDate = \Carbon\Carbon::createFromFormat('Y-m-d', $date)->format('d-m-Y');

                    $array_appels_par_date[] = (object)[
                        'date' => $formattedDate,
                        'appel entrant' => $counts['appel entrant'],
                        'appel sortant' => $counts['appel sortant']
                    ];
                }

                /****nb_appel_ total** */
                 $query_nb_appel=TraitementAppel::on('temp')->with('appel');
                if($dt==null && $a_dt==null){
                    $query_nb_appel ->whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->month);
                }else{
                    $query_nb_appel->whereBetween('created_at',[$dt,$a_dt]);
                }
                if ($projet_id!=0) {
                    $query_nb_appel->whereHas('appel', function ($q) use ($projet_id) {
                        $q->where('projet_id',$projet_id)
                            ;
                });
                }
                  //comercial
                  if($us_role==3){
                    $query_nb_appel->where('user_id', $us_id);
                }
               $nb_appels = $query_nb_appel->count();
            /***************************Prospects **********************/
            $query_prospect=Prospect::on('temp');
            if($dt==null && $a_dt==null){
                $query_prospect ->whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->month);
            }else{
                $query_prospect->whereBetween('created_at',[$dt,$a_dt]);
            }
            if ($projet_id!=0) {
                $query_prospect->where('projet_id',  $projet_id );
            }
            $nb_prospects=$query_prospect->count();

            /***************************Clients*****************/
            $query_client=Client::on('temp');
            if($dt==null && $a_dt==null){
                $query_client ->whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->month);
            }else{
                $query_client->whereBetween('created_at',[$dt,$a_dt]);
            }
             if ($projet_id!=0) {
                $query_client->where('projet_id',  $projet_id );
            }
            $nb_clients=$query_client->count();

            /**********************Reclamations*************** statut ==>0 ********
            $query_reclamations = DB::connection('mysql_client')->table('reclamations')
           // ->Leftjoin('erp_societe_principal_10.users as users_traite','users_traite.id','=','reclamations.user_id_traite')
            ->Leftjoin('erp_societe_principal_10.reservations','reservations.id','=','reclamations.dossier_id');
             //comercial
             if($us_role==3){
                $query_reclamations->where('reclamations.user_id_traite',$us_id);
            }
            if($projet_id!=null){
                $query_reclamations->Leftjoin('erp_societe_principal_10.projets', function($join) use($projet_id) {
                    $join->on('reservations.projet_id', '=', 'projets.id')->where('reservations.projet_id',  $projet_id);
                    });
                }
            $query_reclamations ->join('users','users.id','=','reclamations.user_id')
            ->join('erp_societe_principal_10.clients as clients','clients.id','=','users.client_id')
            ;
            if($dt==null && $a_dt==null){
                $query_reclamations ->whereYear('reclamations.created_at', Carbon::now()->year)->whereMonth('reclamations.created_at', Carbon::now()->month);
            }else{
                $query_reclamations->whereBetween('reclamations.created_at',[$dt,$a_dt]);
            }

            $rec = $query_reclamations
           // ->where('reclamations.statut',0)
            ->select('reclamations.*','clients.id as client_id'
                    ,'clients.nom as client_nom','clients.prenom as client_prenom'
                   ,'reservations.code_reservation','reclamations.service'
                    )
                    ->orderBy('reclamations.etat', 'asc')
                        ->get();
            $count_reclamation=count($rec);
            $reclamations=$rec->take(5);
            /***********************echeances******************/
            $query_echeances =Avance::on('temp')->with('last_statut','reservation')
                    ->where('mode_paiement','!=',7)->where('montant','>',0)
                    ->where('statut', StatutReservationEnum::Validé->value);
                    if($dt==null && $a_dt==null){
                        $query_echeances ->whereYear('echeance', Carbon::now()->year)->whereMonth('echeance', Carbon::now()->month);
                    }else{
                        $query_echeances->whereBetween('echeance',[$dt,$a_dt]);
                    }

                    if($projet_id!=null){
                        $query_echeances->whereHas('reservation', function ($q) use ($projet_id) {
                           $q->where('projet_id', $projet_id)
                                ->where('etat', 1)
                                ->where('statut',StatutReservationEnum::Validé->value);
                            });
                    }
                     //comercial
                    if($us_role==3){
                        $query_echeances->where('user_id', $us_id);
                    }
            $nb_echeance = count($query_echeances->get());
            $echeances=$query_echeances->get()->take(5);
            /****************************Biens by Statut************************* */
            $Array_biens_etat=[];

            array_push($Array_biens_etat,$this->get_nb_biens($request->merge(['etat' =>  'DISPONIBLE','projet_id'=>$projet_id]))->original['nb_bien']);
            array_push($Array_biens_etat,$this->get_nb_biens($request->merge(['etat' =>  'PRE_RESERVATION','projet_id'=>$projet_id]))->original['nb_bien']);
            array_push($Array_biens_etat,$this->get_nb_biens($request->merge(['etat' =>  'RESERVATION','projet_id'=>$projet_id]))->original['nb_bien']);
            array_push($Array_biens_etat,$this->get_nb_biens($request->merge(['etat' =>  'BLOQUE','projet_id'=>$projet_id]))->original['nb_bien']);
            array_push($Array_biens_etat,$this->get_nb_biens($request->merge(['etat' =>  'ENCOURS_DE_PROPOSITION','projet_id'=>$projet_id]))->original['nb_bien']);

            /****************************Desistement by Statut************************* */
            $desistementDefinitif = $this->getDesistementCountsByDate(1, null, $projet_id, $dt, $a_dt, $us_role, $us_id);
            $desistementProche = $this->getDesistementCountsByDate(2, 1, $projet_id, $dt, $a_dt, $us_role, $us_id);
            $desistementCoReservataire = $this->getDesistementCountsByDate(2, 2, $projet_id, $dt, $a_dt, $us_role, $us_id);
            $desistementPartiel = $this->getDesistementCountsByDate(2, 3, $projet_id, $dt, $a_dt, $us_role, $us_id);
            $changementBien = $this->getDesistementCountsByDate(3, null, $projet_id, $dt, $a_dt, $us_role, $us_id);

            // Combine all the results by date
            $desistementStats = [];

            // Helper function to format date as DD-MM-YYYY
            $formatDate = function($date) {
                return Carbon::createFromFormat('Y-m-d', $date)->format('d-m-Y');
            };

            // Process each type and merge into the final array
            $processType = function($data, $typeName, &$resultArray) use ($formatDate) {
                foreach ($data as $item) {
                    $date = $formatDate($item->date);

                    if (!isset($resultArray[$date])) {
                        $resultArray[$date] = [
                            'date' => $date,
                            'Désistement Définitif' => 0,
                            'Désistement au profit d\'un proche' => 0,
                            'Désistement au profit d\'un co reservataire' => 0,
                            'Désistement partiel' => 0,
                            'Changement de Bien' => 0
                        ];
                    }

                    $resultArray[$date][$typeName] = (int)$item->count;
                }
            };

            // Process each type
            $processType($desistementDefinitif, 'Désistement Définitif', $desistementStats);
            $processType($desistementProche, 'Désistement au profit d\'un proche', $desistementStats);
            $processType($desistementCoReservataire, 'Désistement au profit d\'un co reservataire', $desistementStats);
            $processType($desistementPartiel, 'Désistement partiel', $desistementStats);
            $processType($changementBien, 'Changement de Bien', $desistementStats);

            // Convert to simple array of objects
            $arrayDesistementStats = array_values($desistementStats);

            // Calculate total counts for each type (if needed)
            $totalCounts = [
                'Désistement Définitif' => $desistementDefinitif->sum('count'),
                'Désistement au profit d\'un proche' => $desistementProche->sum('count'),
                'Désistement au profit d\'un co reservataire' => $desistementCoReservataire->sum('count'),
                'Désistement partiel' => $desistementPartiel->sum('count'),
                'Changement de Bien' => $changementBien->sum('count')
            ];

            /***********************Reservation**** nb  */
            $query_rsv=Reservation::on('temp')->where('etat',1)
            ->where('statut',StatutReservationEnum::Validé->value);
            if($dt==null && $a_dt==null){
                $query_rsv ->whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->month);
            }else{
                $query_rsv->whereBetween('created_at',[$dt,$a_dt]);
            }
            if($projet_id!=null){
                $query_rsv->where('projet_id', $projet_id);
            }
              //comercial
            if($us_role==3){
                $query_rsv->where('user_id', $us_id);
            }
            $nb_rsv = count($query_rsv->get());
            /*****************Sav*********************/
            $query_sav = Reclamation::on('temp')->with('piece_jointe','bien','prestataire')->LeftJoin('reservations','reservations.bien_id','reclamations.bien_id')
            ->select('reclamations.*')
           // ->where('reclamations.statut',1)
            ->where('reservations.etat',1)
            ->where('reservations.deleted_at',null)
            ->orderBy('reclamations.created_at', 'desc');
            if($dt==null && $a_dt==null){
                $query_sav ->whereYear('reclamations.created_at', Carbon::now()->year)->whereMonth('reclamations.created_at', Carbon::now()->month);
            }else{
                $query_sav->whereBetween('reclamations.created_at',[$dt,$a_dt]);
            }
            if($projet_id!=null){
                $query_sav->where('reclamations.projet_id', $projet_id);
            }

            $nb_sav = count($query_sav->get());
            $sav=$query_sav->get()->take(5);
            /*************************Remise de cles****************** */
            $query_remis_recement = RemiseCle::on('temp');
            if($dt==null && $a_dt==null){
                $query_remis_recement ->whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->month);
            }else{
                $query_remis_recement->whereBetween('created_at',[$dt,$a_dt]);
            }
            if($projet_id!=null){
                $query_remis_recement->where('projet_id', $projet_id);
            }
             //comercial
            if($us_role==3){
                $query_remis_recement->where('user_id', $us_id);
            }
            $nb_remise_recement=$query_remis_recement->count();

            //remise a venir
            $query_nb_bien_remise = Bien::on('temp')
                    ->where('etat', 'RESERVATION')
                    ->doesntHave('remiseCle');
                    if($projet_id!=null){
                        $query_nb_bien_remise->where('projet_id', $projet_id);
                    }
            $nb_remise_a_venir=$query_nb_bien_remise->count();
                    /********************objectifss */
             $query_obj=Objectif::on('temp')->where('user_id',$us_id);
                    if ($projet_id!=null) {
                        $query_obj->where('projet_id',  $projet_id );
                    }
                    if($dt==null && $a_dt==null){
                        $query_obj ->whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->month);
                    }else{
                        $query_obj->whereBetween('created_at',[$dt,$a_dt]);
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

                 //array nb de vente by date

                            $query = Bien::on('temp')->with('all_reservations_active')
                ->select(DB::raw("DATE(reservations.date_reservation) as day, count(reservations.id) as count"))
                ->join('reservations', 'biens.id', 'reservations.bien_id')
                ->where('biens.etat', 'RESERVATION')
                ->where('reservations.deleted_at', null)
                ->where('reservations.etat', 1);

            if ($dt == null && $a_dt == null) {
                $query->whereYear('reservations.date_reservation', Carbon::now()->year)
                    ->whereMonth('reservations.date_reservation', Carbon::now()->month);
            } else {
                $query->whereBetween('reservations.date_reservation', [$dt, $a_dt]);
            }

            if ($projet_id != null) {
                $query->where('biens.projet_id', $projet_id);
            }

            // comercial
            if ($us_role == 3) {
                $query->where(function($query_n) use ($us_id) {
                    $query_n->whereHas('all_reservations_active', function ($q_com) use ($us_id) {
                        $q_com->where('user_id', $us_id);
                    });
                });
            }

            $ventes = $query->groupBy(DB::raw("day"))->get();

            $array_ventes = $ventes->map(function ($data) {
                return [
                    'date' => Carbon::parse($data['day'])->format('d-m-Y'),
                    'nombre' => (string) $data['count']
                ];
            })->all();
                    /*************array visite by interet et date */

                      $query = Visite::on('temp')
                        ->select(
                            DB::raw("DATE(created_at) as created_date"),
                            DB::raw("count(id) as count"),
                            "interet"
                        )
                        ->where('etat', 1);

                    if($dt==null && $a_dt==null){
                        $query->whereYear('created_at', Carbon::now()->year)
                            ->whereMonth('created_at', Carbon::now()->month);
                    } else {
                        $query->whereBetween('created_at', [$dt, $a_dt]);
                    }

                    if($projet_id!=null){
                        $query->where('projet_id', $projet_id);
                    }

                    $nb_visite_by_inter_et_date = $query->groupBy(DB::raw("DATE(created_at), interet"))->get();

                    // First, group the data by date
                    $groupedByDate = [];
                    foreach ($nb_visite_by_inter_et_date as $data) {
                        $date = $data['created_date'];
                        if (!isset($groupedByDate[$date])) {
                            $groupedByDate[$date] = [
                                'intéressé' => 0,
                                'réceptif' => 0,
                                'perdu' => 0
                            ];
                        }

                        // Map interet values to French labels
                        switch ($data['interet']) {
                            case 1:
                                $groupedByDate[$date]['intéressé'] = (int)$data['count'];
                                break;
                            case 2:
                                $groupedByDate[$date]['réceptif'] = (int)$data['count'];
                                break;
                            case 3:
                                $groupedByDate[$date]['perdu'] = (int)$data['count'];
                                break;
                        }
                    }

                    // Now format into the final array of objects
                    $array_visite_interet_et_date = [];
                    foreach ($groupedByDate as $date => $counts) {
                        // Format date as DD/MM/YYYY (assuming created_date is YYYY-MM-DD)
                        $array_visite_interet_et_date[] = (object)[
                            'date' => $date,
                            'intéressé' => $counts['intéressé'],
                            'perdu' => $counts['perdu'],
                            'réceptif' => $counts['réceptif']
                        ];
                    }


           return response()->json([
            'projet_id'=>$projet_id,
            'sum_encaissements' => $sum_encaissements,
            'sum_penalites' => $sum_penalites,
            'sum_remboursements' => $sum_remboursements,
            'nb_visites'=>$nb_visites,
            'nb_appels'=>$nb_appels,
            'Appels'=>$array_appels_par_date,
            'nb_prospects'=>$nb_prospects,
            'nb_clients'=>$nb_clients,
            'reclamations_clients'=>[],
            'count_reclamation'=>0,
            'echeances'=>$echeances,
            'nb_echeances'=>$nb_echeance,
            'biens'=>$Array_biens_etat,
            'count_biens'=>array_sum($Array_biens_etat),
             'desistements' => $arrayDesistementStats,
             'count_dst' => array_sum($totalCounts),
            'nb_rsv'=> $nb_rsv,
            'nb_sav'=>$nb_sav,
            'sav'=>$sav,
            'nb_remise_a_venir'=>$nb_remise_a_venir,
            'nb_remise_recement'=>$nb_remise_recement,
            'obj_mois_appels'=>$obj_mois_appels
            ,'obj_mois_visites'=>$obj_mois_visites
            ,'obj_mois_reservations'=>$obj_mois_reservations,
            'array_encaissement'=>$array_encaissements,
            'array_ventes'=>$array_ventes,
            'array_visite_interet_et_date'=>$array_visite_interet_et_date



         ]);
        }
    }



    private function getDesistementCountsByDate($type, $type_dp, $projet_id, $dt, $a_dt, $us_role, $us_id) {
    DatabaseHelper::Config();
    $query = Desistement::on('temp')
        ->where('statut', 1)
        ->where('type', $type);

    if ($type_dp !== null) {
        $query->where('type_dp', $type_dp);
    }

    if ($dt == null && $a_dt == null) {
        $query->whereYear('created_at', Carbon::now()->year)
              ->whereMonth('created_at', Carbon::now()->month);
    } else {
        $query->whereBetween('created_at', [$dt, $a_dt]);
    }

    if ($projet_id != null) {
        $query->where('projet_id', $projet_id);
    }

    if ($us_role==3) {
        $query->where('user_id', $us_id);
    }

    return $query->select(
        DB::raw("DATE(created_at) as date"),
        DB::raw("COUNT(*) as count")
    )->groupBy(DB::raw("DATE(created_at)"))->get();
}


  public function get_nb_biens(Request $request){

        DatabaseHelper::Config();
        $query=Bien::on('temp')->where('etat',$request->etat);
        if($request->projet_id!=null ){
            $query->where('projet_id',$request->projet_id);
        }
        $nb=$query->count();

        return response()->json(['nb_bien' => $nb], 200);
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
