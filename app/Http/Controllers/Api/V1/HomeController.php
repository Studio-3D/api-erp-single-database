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
        if (Auth::guard('api')->check() && RoleHelper::ACSup()  || RoleHelper::AgentAdmin()) {
            DatabaseHelper::Config();
          //->whereDate('date', '>=', Carbon::now())
            $obj_mois_appels=0;
            $obj_mois_visites=0;
            $obj_mois_reservations=0;

            if(RoleHelper::AdminSup() || RoleHelper::AgentAdmin() ){

                if($user_id==0){

                    $query_notif=Notification::on('temp')->with('projet')
                        /*->where('type','!=',3)
                        ->where('type','!=',6)
                        ->where('type','!=',7)
                        ->where('type','!=',22)
                        ->where('type','!=',29)*/
                        //relance appel /rdv appel /relance visite /rdv visite /echeance
                        ->where(function ($q_type) {
                            $q_type->where('type',27)
                            ->orwhere('type',28)
                            ->orwhere('type',1)
                            ->orwhere('type',2)
                            ->orwhere('type',5)
                        ;})
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
                    /*->where('type','!=',3)
                    ->where('type','!=',6)
                    ->where('type','!=',7)
                    ->where('type','!=',22)
                    ->where('type','!=',29)*/
                    //relance appel /rdv appel /relance visite /rdv visite /echeance
                    ->where(function ($q_type) {
                            $q_type->where('type',27)
                            ->orwhere('type',28)
                            ->orwhere('type',1)
                            ->orwhere('type',2)
                            ->orwhere('type',5)
                        ;})
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
                /*->where('type','!=',6)
                ->where('type','!=',3)
                ->where('type','!=',29)
                ->where('type','!=',7)
                ->where('type','!=',22)
                ->where('type','!=',11)
                ->where('type','!=',12)->where('type','!=',16)->where('type','!=',18)
                ->where('type','!=',13)->where('type','!=',14)
                ->where('type','!=',15)*/
                //relance appel /rdv appel /relance visite /rdv visite /echeance
                 ->where(function ($q_type) {
                            $q_type->where('type',27)
                            ->orwhere('type',28)
                            ->orwhere('type',1)
                            ->orwhere('type',2)
                            ->orwhere('type',5)
                        ;})
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
 * Applique le filtre de date à une requête
 */
private function applyDateFilter($query, $dt, $a_dt, $dateField = 'created_at')
{
    if ($dt == null && $a_dt == null) {
        return $query->whereYear($dateField, Carbon::now()->year)
                     ->whereMonth($dateField, Carbon::now()->month);
    }

    if ($dt == $a_dt) {
        return $query->whereDate($dateField, $dt);
    }

    return $query->whereDate($dateField, '>=', $dt)
                 ->whereDate($dateField, '<=', $a_dt);
}
    /**
     * Show the form for creating a new resource.
     */
/**
 * Get top 4 commerciaux by CA
 */
/**
 * Get top 4 commerciaux by CA - Version simplifiée
 */
/**
 * Get top 4 commerciaux by CA
 */
private function getTopCommerciaux($projet_id, $dt, $a_dt, $us_role, $us_id)
{
    DatabaseHelper::Config();

    // Récupérer d'abord tous les commerciaux
    $commerciaux = User::on('temp')
       // ->where('role', 3)
        ->whereNull('deleted_at')
        ->get();

    $topCommerciaux = [];

    foreach ($commerciaux as $commercial) {
        // Pour chaque commercial, calculer son CA
        $query = Encaissement::on('temp')
            ->join('reservations', 'encaissements.reservation_id', '=', 'reservations.id')
            ->where('encaissements.deleted_at', null)
            ->where('reservations.etat', 1)
            ->where('reservations.statut', StatutReservationEnum::Validé->value)
            ->where('reservations.user_id', $commercial->id) // Lier au commercial
            ->where(function($q) {
                $q->where('encaissements.type_encaissement', 1)
                  ->orWhere('encaissements.type_encaissement', 6);
            });

        // Filtre par date sur les encaissements
        if ($dt == null && $a_dt == null) {
            $query->whereYear('encaissements.date_reglement', Carbon::now()->year)
                  ->whereMonth('encaissements.date_reglement', Carbon::now()->month);
        } else {
            if ($dt == $a_dt) {
                $query->whereDate('encaissements.date_reglement', $dt);
            } else {
                $query->whereDate('encaissements.date_reglement', '>=', $dt)
                      ->whereDate('encaissements.date_reglement', '<=', $a_dt);
            }
        }

        // Filtre par projet
        if ($projet_id != null) {
            $query->where('reservations.projet_id', $projet_id);
        }

        $total_ca = $query->sum('encaissements.montant');

        // Compter les ventes distinctes
        $ventesQuery = Reservation::on('temp')
            ->where('etat', 1)
            ->where('statut', StatutReservationEnum::Validé->value)
            ->where('user_id', $commercial->id);

        if ($dt == null && $a_dt == null) {
            $ventesQuery->whereYear('created_at', Carbon::now()->year)
                        ->whereMonth('created_at', Carbon::now()->month);
        } else {
            if ($dt == $a_dt) {
                $ventesQuery->whereDate('created_at', $dt);
            } else {
                $ventesQuery->whereDate('created_at', '>=', $dt)
                            ->whereDate('created_at', '<=', $a_dt);
            }
        }

        if ($projet_id != null) {
            $ventesQuery->where('projet_id', $projet_id);
        }

        $total_ventes = $ventesQuery->count();

        // Pour le commercial connecté (role=3), on garde toutes ses données
        // Pour les autres, on ne garde que ceux qui ont des ventes ou du CA

            // Pour Admin/SuperAdmin, on ajoute seulement ceux qui ont des ventes
            if ($total_ventes > 0 || $total_ca > 0) {
                $topCommerciaux[] = [
                    'id' => $commercial->id,
                    'name' => $commercial->prenom . ' ' . $commercial->name,
                    'ca' => (float) $total_ca,
                    'ventes' => (int) $total_ventes,
                    'commission' => (float) $total_ca * 0.03,
                ];
            }

    }

    // Trier par CA décroissant
    usort($topCommerciaux, function($a, $b) {
        return $b['ca'] <=> $a['ca'];
    });

    // Limiter à 4 résultats
    return array_slice($topCommerciaux, 0, 4);
}

public function dashboard(Request $request,$projet_id,$de_date,$a_date)
{
    DatabaseHelper::Config();
    $user = Auth::user();
    $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
    $obj_mois_appels=0;
    $obj_mois_visites=0;
    $obj_mois_reservations=0;
    if (Auth::guard('api')->check() && RoleHelper::ACSup_RC() || RoleHelper::AgentAdmin()) {
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
        $this->applyDateFilter($query_en, $dt, $a_dt, 'date_reglement');

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
        $this->applyDateFilter($query, $dt, $a_dt, 'date_reglement');

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
        $this->applyDateFilter($query_penalite, $dt, $a_dt);

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
        $this->applyDateFilter($query_remb, $dt, $a_dt);

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
        $this->applyDateFilter($query_nb_visites, $dt, $a_dt);

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
        $this->applyDateFilter($query_appels_date_type, $dt, $a_dt);

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
            $formattedDate = \Carbon\Carbon::createFromFormat('Y-m-d', $date)->format('d-m-Y');
            $array_appels_par_date[] = (object)[
                'date' => $formattedDate,
                'appel entrant' => $counts['appel entrant'],
                'appel sortant' => $counts['appel sortant']
            ];
        }

        /****nb_appel total** */
        $query_nb_appel=TraitementAppel::on('temp')->with('appel');
        $this->applyDateFilter($query_nb_appel, $dt, $a_dt);

        if ($projet_id!=0) {
            $query_nb_appel->whereHas('appel', function ($q) use ($projet_id) {
                $q->where('projet_id',$projet_id);
            });
        }
        //comercial
        if($us_role==3){
            $query_nb_appel->where('user_id', $us_id);
        }
        $nb_appels = $query_nb_appel->count();

        /***************************Prospects **********************/
        $query_prospect=Prospect::on('temp');
        $this->applyDateFilter($query_prospect, $dt, $a_dt);

        if ($projet_id!=0) {
            $query_prospect->where('projet_id',  $projet_id );
        }
        $nb_prospects=$query_prospect->count();

        /***************************Clients*****************/
        $query_client=Client::on('temp');
        $this->applyDateFilter($query_client, $dt, $a_dt);

        if ($projet_id!=0) {
            $query_client->where('projet_id',  $projet_id );
        }
        $nb_clients=$query_client->count();

        /**********************Reclamations*****************
        $query_reclamations = DB::connection('mysql_client')->table('reclamations')
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
        $query_reclamations->join('users','users.id','=','reclamations.user_id')
            ->join('erp_societe_principal_10.clients as clients','clients.id','=','users.client_id');

        // Appliquer le filtre de date
        if ($dt == null && $a_dt == null) {
            $query_reclamations->whereYear('reclamations.created_at', Carbon::now()->year)
                               ->whereMonth('reclamations.created_at', Carbon::now()->month);
        } else {
            if ($dt == $a_dt) {
                $query_reclamations->whereDate('reclamations.created_at', $dt);
            } else {
                $query_reclamations->whereDate('reclamations.created_at', '>=', $dt)
                                   ->whereDate('reclamations.created_at', '<=', $a_dt);
            }
        }

        $rec = $query_reclamations
            ->select('reclamations.*','clients.id as client_id'
                    ,'clients.nom as client_nom','clients.prenom as client_prenom'
                   ,'reservations.code_reservation','reclamations.service')
            ->orderBy('reclamations.etat', 'asc')
            ->get();
        $count_reclamation=count($rec);
        $reclamations=$rec->take(5);

        /***********************echeances******************/
        $query_echeances = Avance::on('temp')->with('last_statut','reservation')
            ->where('mode_paiement','!=',7)->where('montant','>',0)
            ->where('statut', StatutReservationEnum::Validé->value);

        // Pour echeance, on utilise le champ 'echeance'
        if ($dt == null && $a_dt == null) {
            $query_echeances->whereYear('echeance', Carbon::now()->year)
                            ->whereMonth('echeance', Carbon::now()->month);
        } else {
            if ($dt == $a_dt) {
                $query_echeances->whereDate('echeance', $dt);
            } else {
                $query_echeances->whereDate('echeance', '>=', $dt)
                                ->whereDate('echeance', '<=', $a_dt);
            }
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
        $formatDate = function($date) {
            return Carbon::createFromFormat('Y-m-d', $date)->format('d-m-Y');
        };

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

        $processType($desistementDefinitif, 'Désistement Définitif', $desistementStats);
        $processType($desistementProche, 'Désistement au profit d\'un proche', $desistementStats);
        $processType($desistementCoReservataire, 'Désistement au profit d\'un co reservataire', $desistementStats);
        $processType($desistementPartiel, 'Désistement partiel', $desistementStats);
        $processType($changementBien, 'Changement de Bien', $desistementStats);

        $arrayDesistementStats = array_values($desistementStats);
        $totalCounts = [
            'Désistement Définitif' => $desistementDefinitif->sum('count'),
            'Désistement au profit d\'un proche' => $desistementProche->sum('count'),
            'Désistement au profit d\'un co reservataire' => $desistementCoReservataire->sum('count'),
            'Désistement partiel' => $desistementPartiel->sum('count'),
            'Changement de Bien' => $changementBien->sum('count')
        ];

        /***********************Reservation nb  */
        $query_rsv = Reservation::on('temp')->where('etat',1)
            ->where('statut',StatutReservationEnum::Validé->value);
        $this->applyDateFilter($query_rsv, $dt, $a_dt);

        if($projet_id!=null){
            $query_rsv->where('projet_id', $projet_id);
        }
        //comercial
        if($us_role==3){
            $query_rsv->where('user_id', $us_id);
        }
        $nb_rsv = $query_rsv->count();  // Utiliser count() directement

        /*****************Sav*********************/
        $query_sav = Reclamation::on('temp')->with('piece_jointe','bien','prestataire')
            ->LeftJoin('reservations','reservations.bien_id','reclamations.bien_id')
            ->select('reclamations.*')
            ->where('reservations.etat',1)
            ->where('reservations.deleted_at',null)
            ->orderBy('reclamations.created_at', 'desc');
        // CORRECTION: Spécifier la table 'reclamations' pour created_at
        if ($dt == null && $a_dt == null) {
            $query_sav->whereYear('reclamations.created_at', Carbon::now()->year)
                    ->whereMonth('reclamations.created_at', Carbon::now()->month);
        } else {
            if ($dt == $a_dt) {
                $query_sav->whereDate('reclamations.created_at', $dt);
            } else {
                $query_sav->whereDate('reclamations.created_at', '>=', $dt)
                        ->whereDate('reclamations.created_at', '<=', $a_dt);
            }
        }
        if($projet_id!=null){
            $query_sav->where('reclamations.projet_id', $projet_id);
        }
        $nb_sav = $query_sav->count();
        $sav = $query_sav->get()->take(5);

        /*************************Remise de cles****************** */
        $query_remis_recement = RemiseCle::on('temp');
        $this->applyDateFilter($query_remis_recement, $dt, $a_dt);

        if($projet_id!=null){
            $query_remis_recement->where('projet_id', $projet_id);
        }
        //comercial
        if($us_role==3){
            $query_remis_recement->where('user_id', $us_id);
        }
        $nb_remise_recement = $query_remis_recement->count();

        //remise a venir
        $query_nb_bien_remise = Bien::on('temp')
            ->where('etat', 'RESERVATION')
            ->doesntHave('remiseCle');
        if($projet_id!=null){
            $query_nb_bien_remise->where('projet_id', $projet_id);
        }
        $nb_remise_a_venir = $query_nb_bien_remise->count();

        /********************objectifs********************/
        $query_obj = Objectif::on('temp');

        if ($us_role == 3) {
            $query_obj->where('user_id', $us_id);
        }

        if ($projet_id != null) {
            $query_obj->where('projet_id', $projet_id);
        }

        $this->applyDateFilter($query_obj, $dt, $a_dt);

        if ($us_role != 3) {
            $obj_mois_appels = $query_obj->sum(DB::raw("JSON_EXTRACT(appels, '$.mois')"));
            $obj_mois_visites = $query_obj->sum(DB::raw("JSON_EXTRACT(visites, '$.mois')"));
            $obj_mois_reservations = $query_obj->sum(DB::raw("JSON_EXTRACT(reservations, '$.mois')"));
        } else {
            $obj = $query_obj->first();
            if ($obj != null) {
                $obj_mois_appels = $obj->appels['mois'] ?? 0;
                $obj_mois_visites = $obj->visites['mois'] ?? 0;
                $obj_mois_reservations = $obj->reservations['mois'] ?? 0;
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
            if ($dt == $a_dt) {
                $query->whereDate('reservations.date_reservation', $dt);
            } else {
                $query->whereDate('reservations.date_reservation', '>=', $dt)
                      ->whereDate('reservations.date_reservation', '<=', $a_dt);
            }
        }

        if ($projet_id != null) {
            $query->where('biens.projet_id', $projet_id);
        }

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
        $this->applyDateFilter($query, $dt, $a_dt);

        if($projet_id!=null){
            $query->where('projet_id', $projet_id);
        }

        $nb_visite_by_inter_et_date = $query->groupBy(DB::raw("DATE(created_at), interet"))->get();

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

        $array_visite_interet_et_date = [];
        foreach ($groupedByDate as $date => $counts) {
            $array_visite_interet_et_date[] = (object)[
                'date' => $date,
                'intéressé' => $counts['intéressé'],
                'perdu' => $counts['perdu'],
                'réceptif' => $counts['réceptif']
            ];
        }
        /********************Top Commerciaux********************/
         if ($us_role <=2 ||$us_role ==10) {
               $top_commerciaux = $this->getTopCommerciaux($projet_id, $dt, $a_dt, $us_role, $us_id);
         }else{
             $top_commerciaux =[];
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
            'obj_mois_appels'=>$obj_mois_appels,
            'obj_mois_visites'=>$obj_mois_visites,
            'obj_mois_reservations'=>$obj_mois_reservations,
            'array_encaissement'=>$array_encaissements,
            'array_ventes'=>$array_ventes,
            'array_visite_interet_et_date'=>$array_visite_interet_et_date,
            'top_commerciaux' => $top_commerciaux, // AJOUTER CETTE LIGNE

        ]);
    }
}



private function getDesistementCountsByDate($type, $type_dp, $projet_id, $dt, $a_dt, $us_role, $us_id)
{
    DatabaseHelper::Config();
    $query = Desistement::on('temp')
        ->where('statut', 1)
        ->where('type', $type);

    if ($type_dp !== null) {
        $query->where('type_dp', $type_dp);
    }

    // Appliquer le filtre de date avec la même logique
    if ($dt == null && $a_dt == null) {
        $query->whereYear('created_at', Carbon::now()->year)
              ->whereMonth('created_at', Carbon::now()->month);
    } else {
        if ($dt == $a_dt) {
            $query->whereDate('created_at', $dt);
        } else {
            $query->whereDate('created_at', '>=', $dt)
                  ->whereDate('created_at', '<=', $a_dt);
        }
    }

    if ($projet_id != null) {
        $query->where('projet_id', $projet_id);
    }

    if ($us_role == 3) {
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
