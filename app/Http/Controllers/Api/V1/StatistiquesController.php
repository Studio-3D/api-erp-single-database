<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Models\Visite;
use App\Models\Bien;
use App\Models\Client;
use App\Models\Prospect;
use App\Models\PenaliteDesistement;
use App\Models\TraitementAppel;
use App\Models\Encaissement;
use App\Models\TypeBien;
use App\Models\Desistement;
use App\Models\Remboursement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Enum\StatutVisiteEnum;
use App\Enum\InteretEnum;


class StatistiquesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index_admin(Request $request, $projet_id,$de_date,$a_date)
    {

        DatabaseHelper::Config();
        if($de_date=="null" && $a_date=="null" ){
                //sans date
                $nb_visites=Visite::on('temp')
                    ->where('projet_id', $request->projet_id)->count();
                $nb_clients=Client::on('temp')->count();
                $nb_prospects=Prospect::on('temp') ->count();
                $nb_biens_vendu=Bien::on('temp')
                            ->Join('reservations','biens.id','reservations.bien_id')
                            ->where('biens.etat','RESERVATION')
                            ->where('reservations.deleted_at',null)
                            ->where('reservations.etat',1)
                            ->where('biens.projet_id', $request->projet_id)
                            ->count();
                $sum_penalites= PenaliteDesistement::on('temp')
                            ->join('desistements', 'desistements.id', '=', 'penalites_desistements.desistement_id')
                            ->where('desistements.projet_id', $request->projet_id)
                            ->sum('penalites_desistements.montant');

                $sum_encaissements=Encaissement::on('temp')->Join('reservations','encaissements.reservation_id','reservations.id')
                        //  ->where('reservations.etat',1)
                            ->where('reservations.projet_id', $request->projet_id)
                            ->where('reservations.deleted_at',null)
                            ->where('reservations.etat',1)
                            /*->where(function($query) {
                                $query  ->where('type_encaissement',1)
                                ->orwhere('type_encaissement',6);
                            })*/
                            ->sum('encaissements.montant');

                $nb_biens_vendu_by_type_date=Bien::on('temp')
                            ->select(DB::raw("reservations.date_reservation,count(reservations.id) as count ,biens.type_id"))
                            ->Join('reservations','biens.id','reservations.bien_id')
                            ->where('biens.etat','RESERVATION')
                            ->where('reservations.etat',1)
                            ->where('reservations.deleted_at',null)
                            ->where('biens.projet_id', $request->projet_id)
                            ->groupBy(DB::raw("reservations.date_reservation,biens.type_id"))
                            ->get();
                $array_biens_type_et_date_reservation=[];
                        foreach ($nb_biens_vendu_by_type_date as $data) {
                                $array_biens_type_et_date_reservation[] = [
                                    date($data['date_reservation']),
                                    (int) $data['count'],
                                    $data['type_id']
                                ];
                            }
                $types_biens=TypeBien::on('temp')->get();
                $array_type_date_desistement=[];
                $nb_desistement_par_type=Desistement::on('temp')
                            ->select(DB::Raw("DATE(created_at) as day,count(id) as count ,type"))
                            ->where('deleted_at',null)
                            ->where('projet_id', $request->projet_id)
                            ->groupBy(DB::raw("day,type"))
                            ->get();
                foreach ($nb_desistement_par_type as $data) {
                                $array_type_date_desistement[] = [
                                    date($data['day']),
                                    (int) $data['count'],
                                    $data['type']
                                ];
                            }
                        /*****Remboursemenrs*****/
                $remboursements=Remboursement::on('temp')
                        ->select(DB::Raw("DATE(remboursements.created_at) as day,count(remboursements.id) as count"))
                        ->Join('desistements','desistements.id','remboursements.desistement_id')
                        ->where('remboursements.deleted_at',null)
                        ->where('desistements.projet_id', $request->projet_id)
                        ->groupBy(DB::raw("day"))
                        ->get();
                $array_remboursements=[];
                foreach ($remboursements as $data) {
                    $array_remboursements[] = [
                        date($data['day']),
                        (int) $data['count']
                    ];
                }

                $sum_remb=Remboursement::on('temp')
                        ->Join('desistements','desistements.id','remboursements.desistement_id')
                        ->where('remboursements.deleted_at',null)
                        ->where('desistements.projet_id', $request->projet_id)
                        ->sum('remboursements.montant_a_rembourser');
                /**********Encaissemens****/
                $encaissements=Encaissement::on('temp')
                        ->select(DB::Raw("DATE(encaissements.date_reglement) as day,sum(encaissements.montant) as montant"))
                        ->Join('reservations','reservations.id','encaissements.reservation_id')
                        ->where('encaissements.deleted_at',null)
                        ->where('reservations.etat',1)
                        ->where('reservations.projet_id', $request->projet_id)
                       /* ->where(function($query) {
                            $query  ->where('type_encaissement',1)
                            ->orwhere('type_encaissement',6);
                        })*/
                        ->groupBy(DB::raw("day"))
                        ->get();
                $array_encaissements=[];
                foreach ($encaissements as $data) {
                    $array_encaissements[] = [
                        date($data['day']),
                        (int) $data['montant']
                    ];
                }


                /************************Visites********************/

                //admin

                $data_v_pre_reserve = [
                    'de_date' => null,
                    'a_date' => null,
                    'statut' => StatutVisiteEnum::Pré_Réservation->value,
                    'interet' => InteretEnum::Intéressé->value,
                    'projet_id' =>$projet_id,
                ];
                $data_v_pre_reserve_perdu = [
                    'de_date' => null,
                    'a_date' => null,
                    'statut' => StatutVisiteEnum::Pré_Réservation_Perdu->value,
                    'interet' => InteretEnum::Intéressé->value,
                    'projet_id' =>$projet_id,
                ];
                $data_v_pre_reserve_vendu = [
                    'de_date' => null,
                    'a_date' => null,
                    'statut' => StatutVisiteEnum::Pré_Réservation_Vendu->value,
                    'interet' => InteretEnum::Intéressé->value,
                    'projet_id' =>$projet_id,
                ];
                $data_v_reserve_perdu = [
                    'de_date' => null,
                    'a_date' => null,
                    'statut' => StatutVisiteEnum::Réservation_Perdu->value,
                    'interet' => InteretEnum::Intéressé->value,
                    'projet_id' =>$projet_id,
                ];
                $data_v_receptif = [
                    'de_date' => null,
                    'a_date' => null,
                    'statut' => null,
                    'order'=>null,
                    'interet' => InteretEnum::Réceptif->value,
                    'projet_id' =>$projet_id,
                ];
                $data_v_perdu = [
                    'de_date' => null,
                    'a_date' => null,
                    'statut' => null,
                    'order' => null,
                    'interet' => InteretEnum::Perdu->value,
                    'projet_id' =>$projet_id,
                ];
                $data_v_vente_direct = [
                    'de_date' => null,
                    'a_date' => null,
                    'order' => 1,
                    'statut' => StatutVisiteEnum::Vendu->value,
                    'interet' => InteretEnum::Intéressé->value,
                    'projet_id' =>$projet_id,
                ];
                $data_v_vente = [
                    'de_date' => null,
                    'a_date' => null,
                    'order' => null,
                    'statut' => StatutVisiteEnum::Vendu->value,
                    'interet' => InteretEnum::Intéressé->value,
                    'projet_id' =>$projet_id,
                ];

                $Array_visite = [];
                array_push($Array_visite,$this->get_visites($request->merge($data_v_receptif))->original['nb_v']);
                array_push($Array_visite,$this->get_visites($request->merge($data_v_pre_reserve))->original['nb_v']);
                array_push($Array_visite,$this->get_visites($request->merge($data_v_pre_reserve_perdu))->original['nb_v']);
                array_push($Array_visite,$this->get_visites($request->merge($data_v_pre_reserve_vendu))->original['nb_v']);
                array_push($Array_visite,$this->get_visites($request->merge($data_v_vente_direct))->original['nb_v']);
                array_push($Array_visite,$this->get_visites($request->merge($data_v_vente))->original['nb_v']);
                array_push($Array_visite,$this->get_visites($request->merge($data_v_reserve_perdu))->original['nb_v']);
                array_push($Array_visite,$this->get_visites($request->merge($data_v_perdu))->original['nb_v']);

                $nb_appels=TraitementAppel::on('temp')
                ->select('traitements_appels.id')
                ->Join('appels','appels.id','traitements_appels.appel_id')
                ->where('traitements_appels.deleted_at',null)
                ->where('appels.projet_id', $request->projet_id)
                ->count();

        }else{

            $dt = Carbon::createFromFormat('Y-m-d', date($de_date))->startOfDay();
            if($a_date=="null"){
                $a_dt = Carbon::createFromFormat('Y-m-d', date($de_date))->endOfDay();
            }
            else{
                $a_dt = Carbon::createFromFormat('Y-m-d', date($a_date))->endOfDay();
            }

                $nb_visites=Visite::on('temp')
                    ->whereBetween('created_at', [Carbon::parse($dt),Carbon::parse($a_dt)])
                    ->where('projet_id', $request->projet_id)->count();
                $nb_clients=Client::on('temp')
                    ->whereBetween('created_at', [Carbon::parse($dt),Carbon::parse($a_dt)])->count();
                $nb_prospects=Prospect::on('temp')
                    ->whereBetween('created_at', [Carbon::parse($dt),Carbon::parse($a_dt)])->count();
                $nb_biens_vendu=Bien::on('temp')
                            ->Join('reservations','biens.id','reservations.bien_id')
                            ->where('biens.etat','RESERVATION')
                            ->whereBetween('reservations.date_reservation', [$dt, $a_dt])
                            ->where('reservations.deleted_at',null)
                            ->where('reservations.etat',1)
                            ->where('biens.projet_id', $request->projet_id)
                            ->count();
                $sum_penalites= PenaliteDesistement::on('temp')
                            ->join('desistements', 'desistements.id', '=', 'penalites_desistements.desistement_id')
                            ->whereBetween('penalites_desistements.created_at', [$dt, $a_dt])
                            ->where('desistements.projet_id', $request->projet_id)
                            ->sum('penalites_desistements.montant');

                $sum_encaissements=Encaissement::on('temp')->Join('reservations','encaissements.reservation_id','reservations.id')
                        //  ->where('reservations.etat',1)
                            ->where('reservations.projet_id', $request->projet_id)
                            ->where('reservations.deleted_at',null)
                            ->where('reservations.etat',1)
                           /* ->where(function($query) {
                                $query  ->where('type_encaissement',1)
                                ->orwhere('type_encaissement',6);
                            })*/
                            ->whereBetween('encaissements.created_at', [$dt, $a_dt])
                            ->sum('encaissements.montant');

                $nb_biens_vendu_by_type_date=Bien::on('temp')
                            ->select(DB::raw("reservations.date_reservation,count(reservations.id) as count ,biens.type_id"))
                            ->Join('reservations','biens.id','reservations.bien_id')
                            ->where('biens.etat','RESERVATION')
                            ->whereBetween('reservations.date_reservation', [$dt, $a_dt])
                            ->where('reservations.etat',1)
                            ->where('reservations.deleted_at',null)
                            ->where('biens.projet_id', $request->projet_id)
                            ->groupBy(DB::raw("reservations.date_reservation,biens.type_id"))
                            ->get();
                $array_biens_type_et_date_reservation=[];
                        foreach ($nb_biens_vendu_by_type_date as $data) {
                                $array_biens_type_et_date_reservation[] = [
                                    date($data['date_reservation']),
                                    (int) $data['count'],
                                    $data['type_id']
                                ];
                            }
                $types_biens=TypeBien::on('temp')->get();
                $array_type_date_desistement=[];
                $nb_desistement_par_type=Desistement::on('temp')
                            ->select(DB::Raw("DATE(created_at) as day,count(id) as count ,type"))
                            ->whereBetween('created_at', [$dt, $a_dt])
                            ->where('deleted_at',null)
                            ->where('projet_id', $request->projet_id)
                            ->groupBy(DB::raw("day,type"))

                            ->get();
                foreach ($nb_desistement_par_type as $data) {
                                $array_type_date_desistement[] = [
                                    date($data['day']),
                                    (int) $data['count'],
                                    $data['type']
                                ];
                }
                $remboursements=Remboursement::on('temp')
                ->select(DB::Raw("DATE(remboursements.created_at) as day,count(remboursements.id) as count"))
                ->Join('desistements','desistements.id','remboursements.desistement_id')
                ->where('remboursements.deleted_at',null)
                ->whereBetween('remboursements.created_at', [$dt, $a_dt])
                ->where('desistements.projet_id', $request->projet_id)
                ->groupBy(DB::raw("day"))
                ->get();
                $array_remboursements=[];
                foreach ($remboursements as $data) {
                    $array_remboursements[] = [
                        date($data['day']),
                        (int) $data['count']
                    ];
                }
                $sum_remb=Remboursement::on('temp')
                ->Join('desistements','desistements.id','remboursements.desistement_id')
                ->where('remboursements.deleted_at',null)
                ->where('desistements.projet_id', $request->projet_id)
                ->whereBetween('remboursements.created_at', [$dt, $a_dt])
                ->sum('remboursements.montant_a_rembourser');

                $encaissements=Encaissement::on('temp')
                ->select(DB::Raw("DATE(encaissements.date_reglement) as day,sum(encaissements.montant) as montant"))
                ->Join('reservations','reservations.id','encaissements.reservation_id')
                ->where('encaissements.deleted_at',null)
                ->where('reservations.etat', 1)
                ->where('reservations.projet_id', $request->projet_id)
                ->whereBetween('encaissements.date_reglement', [$dt, $a_dt])
                /*->where(function($query) {
                    $query  ->where('type_encaissement',1)
                    ->orwhere('type_encaissement',6);
                })*/
                ->groupBy(DB::raw("day"))
                ->get();
                $array_encaissements=[];
                foreach ($encaissements as $data) {
                    $array_encaissements[] = [
                        date($data['day']),
                        (int) $data['montant']
                    ];
                }

                /************************Visites********************/

                //admin

            $data_v_pre_reserve = [

                'de_date' => $dt,
                'a_date' => $a_dt,
                'statut' => StatutVisiteEnum::Pré_Réservation->value,
                'interet' => InteretEnum::Intéressé->value,
                'projet_id' =>$projet_id,
            ];
            $data_v_pre_reserve_perdu = [
                'de_date' => $dt,
                'a_date' => $a_dt,
                'statut' => StatutVisiteEnum::Pré_Réservation_Perdu->value,
                'interet' => InteretEnum::Intéressé->value,
                'projet_id' =>$projet_id,
            ];
            $data_v_pre_reserve_vendu = [
                'de_date' => $dt,
                'a_date' => $a_dt,
                'statut' => StatutVisiteEnum::Pré_Réservation_Vendu->value,
                'interet' => InteretEnum::Intéressé->value,
                'projet_id' =>$projet_id,
            ];
            $data_v_reserve_perdu = [

                'de_date' => $dt,
                'a_date' => $a_dt,
                'statut' => StatutVisiteEnum::Réservation_Perdu->value,
                'interet' => InteretEnum::Intéressé->value,
                'projet_id' =>$projet_id,
            ];
            $data_v_receptif = [
                'de_date' => $dt,
                'a_date' => $a_dt,
                'statut' => null,
                'order'=>null,
                'interet' => InteretEnum::Réceptif->value,
                'projet_id' =>$projet_id,
            ];
            $data_v_perdu = [
                'de_date' => $dt,
                'a_date' => $a_dt,
                'statut' => null,
                'order' => null,
                'interet' => InteretEnum::Perdu->value,
                'projet_id' =>$projet_id,
            ];
            $data_v_vente_direct = [
                'de_date' => $dt,
                'a_date' => $a_dt,
                'order' => 1,
                'statut' => StatutVisiteEnum::Vendu->value,
                'interet' => InteretEnum::Intéressé->value,
                'projet_id' =>$projet_id,
            ];
            $data_v_vente = [
                'de_date' => $dt,
                'a_date' => $a_dt,
                'order' => null,
                'statut' => StatutVisiteEnum::Vendu->value,
                'interet' => InteretEnum::Intéressé->value,
                'projet_id' =>$projet_id,
            ];

            $Array_visite = [];
            array_push($Array_visite,$this->get_visites($request->merge($data_v_receptif))->original['nb_v']);
            array_push($Array_visite,$this->get_visites($request->merge($data_v_pre_reserve))->original['nb_v']);
            array_push($Array_visite,$this->get_visites($request->merge($data_v_pre_reserve_perdu))->original['nb_v']);
            array_push($Array_visite,$this->get_visites($request->merge($data_v_pre_reserve_vendu))->original['nb_v']);
            array_push($Array_visite,$this->get_visites($request->merge($data_v_vente_direct))->original['nb_v']);
            array_push($Array_visite,$this->get_visites($request->merge($data_v_vente))->original['nb_v']);
            array_push($Array_visite,$this->get_visites($request->merge($data_v_reserve_perdu))->original['nb_v']);
            array_push($Array_visite,$this->get_visites($request->merge($data_v_perdu))->original['nb_v']);

            $nb_appels=TraitementAppel::on('temp')
            ->select('traitements_appels.id')
            ->Join('appels','appels.id','traitements_appels.appel_id')
            ->where('traitements_appels.deleted_at',null)
            ->where('appels.projet_id', $request->projet_id)
            ->whereBetween('traitements_appels.created_at', [$dt, $a_dt])
            ->count();

        }


        return response()->json([
                'nb_appels'=>$nb_appels,
                'nb_visites'=>$nb_visites,
                'nb_biens_vendu'=>$nb_biens_vendu,
                'nb_clients'=>$nb_clients,
                'sum_penalites'=>$sum_penalites,
                'sum_encaissements'=>$sum_encaissements,
                'nb_prospects'=>$nb_prospects,
              //  'plages_dates'=>$array_plages_dates,
                'array_biens_type_et_date_reservation'=>$array_biens_type_et_date_reservation,
                'array_type_date_desistement'=>$array_type_date_desistement,
                'types_biens'=>$types_biens,
                'visites'=>$Array_visite,
                'remboursements'=>$array_remboursements,
                'sum_remb'=>$sum_remb,
                'encaissements'=>$array_encaissements

            ], 200);
    }


    public static function get_visites(Request $request)
    {
        DatabaseHelper::Config();

            if($request->a_date==null && $request->de_date==null){
                if($request->order==1){
                    //first visite
                    $nb_visite = Visite::on('temp')
                    ->where('etat',1)
                    ->where('old_v_id',null)
                    ->where('interet',$request->interet)
                    ->where('statut',$request->statut)
                    ->where('projet_id', $request->projet_id)->count();
                }else{
                    if($request->statut<=2){
                        //pre reserve ou vendu
                        $nb_visite = Visite::on('temp')
                        ->where('etat',1)
                        ->where('interet',$request->interet)
                        ->where('statut',$request->statut)
                        ->where('projet_id', $request->projet_id)->count();
                    }else{
                        $nb_visite = Visite::on('temp')
                        ->where('etat',1)
                        ->where('interet',$request->interet)
                        ->where('statut',$request->statut)
                        ->where('projet_id', $request->projet_id)->count();
                    }

                }
            }else{

                if($request->order==1){
                    //first visite
                    $nb_visite = Visite::on('temp')
                    ->whereBetween('created_at', [$request->de_date, $request->a_date])
                    ->where('etat',1)
                    ->where('old_v_id',null)
                    ->where('interet',$request->interet)
                    ->where('statut',$request->statut)
                    ->where('projet_id', $request->projet_id)->count();
                }else{
                    if($request->statut<=2){
                        //pre reserve ou vendu
                        $nb_visite = Visite::on('temp')
                       ->whereBetween('created_at', [$request->de_date, $request->a_date])
                        ->where('etat',1)
                        ->where('interet',$request->interet)
                        ->where('statut',$request->statut)
                        ->where('projet_id', $request->projet_id)->count();
                    }else{
                        $nb_visite = Visite::on('temp')
                        ->whereBetween('updated_at', [$request->de_date, $request->a_date])
                        ->where('etat',1)
                        ->where('interet',$request->interet)
                        ->where('statut',$request->statut)
                        ->where('projet_id', $request->projet_id)->count();
                    }

                }

            }

            return response()->json(['nb_v' => $nb_visite], 200);


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
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */


    /**
     * Remove the specified resource from storage.
     */




}
