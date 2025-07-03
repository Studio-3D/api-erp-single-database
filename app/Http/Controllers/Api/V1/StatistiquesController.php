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

            if($de_date!="null" && $a_date!="null" ){
                $dt = Carbon::createFromFormat('Y-m-d', date($de_date))->startOfDay();
                if($a_date=="null"){
                    $a_dt = Carbon::createFromFormat('Y-m-d', date($de_date))->endOfDay();
                }
                else{
                    $a_dt = Carbon::createFromFormat('Y-m-d', date($a_date))->endOfDay();
                }
            }else{
                $dt=null;
                $a_dt=null;
            }

                //visites
                $query = Visite::on('temp')
                    ->where('projet_id', $request->projet_id);

                // Add whereBetween only if dates are valid
                if ($dt !== null && $a_dt !== null) {
                    $query->whereBetween('created_at', [$dt, $a_dt]);
                }else{
                     $query->whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->month);
                }
                $nb_visites = $query->count();

             // Clients
                $query = Client::on('temp');
                if ($dt !== null && $a_dt !== null) {
                    $query->whereBetween('created_at', [$dt, $a_dt]);
                }else{
                     $query->whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->month);
                }
                $nb_clients = $query->count();
                // Prospects (même approche)
                $query = Prospect::on('temp');
                if ($dt !== null && $a_dt !== null) {
                    $query->whereBetween('created_at', [$dt, $a_dt]);
                }
                $nb_prospects = $query->count();
               // Biens vendus (même approche)
                $query = Bien::on('temp')
                    ->join('reservations','biens.id','reservations.bien_id')
                    ->where('biens.etat','RESERVATION')
                    ->where('reservations.deleted_at',null)
                    ->where('reservations.etat',1)
                    ->where('biens.projet_id', $request->projet_id);
                if ($dt !== null && $a_dt !== null) {
                    $query->whereBetween('reservations.date_reservation', [$dt, $a_dt]);
                }else{
                     $query->whereYear('reservations.date_reservation', Carbon::now()->year)->whereMonth('reservations.date_reservation', Carbon::now()->month);
                }
                $nb_biens_vendu = $query->count();
                 // Penalites (même approche)
                $query = PenaliteDesistement::on('temp')
                    ->join('desistements', 'desistements.id', '=', 'penalites_desistements.desistement_id')
                    ->where('desistements.projet_id', $request->projet_id);
                if ($dt !== null && $a_dt !== null) {
                    $query->whereBetween('penalites_desistements.created_at', [$dt, $a_dt]);
                }else{
                     $query->whereYear('penalites_desistements.created_at', Carbon::now()->year)->whereMonth('penalites_desistements.created_at', Carbon::now()->month);
                }
                $sum_penalites = $query->sum('penalites_desistements.montant');


                // Encaissements (même approche)
                $query = Encaissement::on('temp')
                    ->join('reservations','encaissements.reservation_id','reservations.id')
                    ->where('reservations.projet_id', $request->projet_id)
                    ->where('reservations.deleted_at',null)
                    ->where('reservations.etat',1);
                if ($dt !== null && $a_dt !== null) {
                    $query->whereBetween('encaissements.created_at', [$dt, $a_dt]);
                }
                else{
                     $query->whereYear('encaissements.created_at', Carbon::now()->year)->whereMonth('encaissements.created_at', Carbon::now()->month);
                }
                $sum_encaissements = $query->sum('encaissements.montant');

                 // Biens vendus par type et date
                $query = Bien::on('temp')
                    ->select(DB::raw("reservations.date_reservation, count(reservations.id) as count, biens.type_id"))
                    ->join('reservations', 'biens.id', 'reservations.bien_id')
                    ->where('biens.etat', 'RESERVATION')
                    ->where('reservations.etat', 1)
                    ->where('reservations.deleted_at', null)
                    ->where('biens.projet_id', $request->projet_id);
                if ($dt !== null && $a_dt !== null) {
                    $query->whereBetween('reservations.date_reservation', [$dt, $a_dt]);
                }else{
                     $query->whereYear('reservations.date_reservation', Carbon::now()->year)->whereMonth('reservations.date_reservation', Carbon::now()->month);
                }
                $nb_biens_vendu_by_type_date = $query->groupBy(DB::raw("reservations.date_reservation, biens.type_id"))->get();
                 $array_biens_type_et_date_reservation=[];
                        foreach ($nb_biens_vendu_by_type_date as $data) {
                                $array_biens_type_et_date_reservation[] = [
                                    date($data['date_reservation']),
                                    (int) $data['count'],
                                    $data['type_id']
                                ];
                            }
                $types_biens=TypeBien::on('temp')->where('projet_id',$request->projet_id)->get();
                $array_type_date_desistement=[];
               // Désistements par type et date
                $query = Desistement::on('temp')
                    ->select(DB::raw("DATE(created_at) as day, count(id) as count, type"))
                    ->where('deleted_at', null)
                    ->where('projet_id', $request->projet_id);
                if ($dt !== null && $a_dt !== null) {
                    $query->whereBetween('created_at', [$dt, $a_dt]);
                }else{
                     $query->whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->month);
                }
                $nb_desistement_par_type = $query->groupBy(DB::raw("day, type"))->get();

                foreach ($nb_desistement_par_type as $data) {
                                $array_type_date_desistement[] = [
                                    date($data['day']),
                                    (int) $data['count'],
                                    $data['type']
                                ];
                }
                    // Remboursements
                $query = Remboursement::on('temp')
                    ->select(DB::raw("DATE(remboursements.created_at) as day, sum(remboursements.montant_a_rembourser) as montant_a_rembourser"))
                    ->join('desistements', 'desistements.id', 'remboursements.desistement_id')
                    ->where('remboursements.deleted_at', null)
                    ->where('desistements.projet_id', $request->projet_id);
                if ($dt !== null && $a_dt !== null) {
                    $query->whereBetween('remboursements.created_at', [$dt, $a_dt]);
                }else{
                     $query->whereYear('remboursements.created_at', Carbon::now()->year)->whereMonth('remboursements.created_at', Carbon::now()->month);
                }
                $remboursements = $query->groupBy(DB::raw("day"))->get();

                $array_remboursements=[];
                foreach ($remboursements as $data) {
                    $array_remboursements[] = [
                        date($data['day']),
                        (int) $data['montant_a_rembourser']
                    ];
                }
                // Somme des remboursements
                $query = Remboursement::on('temp')
                    ->join('desistements', 'desistements.id', 'remboursements.desistement_id')
                    ->where('remboursements.deleted_at', null)
                    ->where('desistements.projet_id', $request->projet_id);
                if ($dt !== null && $a_dt !== null) {
                    $query->whereBetween('remboursements.created_at', [$dt, $a_dt]);
                }
                else{
                     $query->whereYear('remboursements.created_at', Carbon::now()->year)->whereMonth('remboursements.created_at', Carbon::now()->month);
                }
                $sum_remb = $query->sum('remboursements.montant_a_rembourser');

                 // Encaissements par date
                $query = Encaissement::on('temp')
                    ->select(DB::raw("DATE(encaissements.date_reglement) as day, sum(encaissements.montant) as montant"))
                    ->join('reservations', 'reservations.id', 'encaissements.reservation_id')
                    ->where('encaissements.deleted_at', null)
                    ->where('reservations.etat', 1)
                    ->where('reservations.projet_id', $request->projet_id);
                if ($dt !== null && $a_dt !== null) {
                    $query->whereBetween('encaissements.date_reglement', [$dt, $a_dt]);
                }else{
                     $query->whereYear('encaissements.date_reglement', Carbon::now()->year)->whereMonth('encaissements.date_reglement', Carbon::now()->month);
                }
                $encaissements = $query->groupBy(DB::raw("day"))->get();

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

          // Appels
            $query = TraitementAppel::on('temp')
                ->select('traitements_appels.id')
                ->join('appels', 'appels.id', 'traitements_appels.appel_id')
                ->where('traitements_appels.deleted_at', null)
                ->where('appels.projet_id', $request->projet_id);
            if ($dt !== null && $a_dt !== null) {
                $query->whereBetween('traitements_appels.created_at', [$dt, $a_dt]);
            }
            else{
                     $query->whereYear('traitements_appels.created_at', Carbon::now()->year)->whereMonth('traitements_appels.created_at', Carbon::now()->month);
                }

            $nb_appels = $query->count();

            $query = Source::on('temp')
                ->leftJoin('prospects', 'sources.id', '=', 'prospects.source')
                ->select('sources.source as source_name', DB::raw('COUNT(prospects.id) as prospects_count'))
                ->where('prospects.projet_id', $request->projet_id)
                 ->groupBy('sources.id', 'sources.source') ;
            if ($dt !== null && $a_dt !== null) {
                $query->whereBetween('prospects.created_at', [$dt, $a_dt]);
            }
            else{
                     $query->whereYear('prospects.created_at', Carbon::now()->year)->whereMonth('prospects.created_at', Carbon::now()->month);
                }
            $prospectsBySource = $query->get()->map(function ($source) {
                return [
                    $source->source_name,
                    (int) $source->prospects_count
                ];
            })
            ->toArray();;
        // For your chart data
        $chartData_sources = $prospectsBySource;

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
