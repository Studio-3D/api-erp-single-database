<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Models\User;
use App\Models\Visite;
use App\Models\Avance;
use App\Models\Relance_Rdv_Visite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Enum\StatutVisiteEnum;
use App\Enum\InteretEnum;
use App\Models\Remboursement;
use App\Models\Desistement;

use App\Models\PenaliteDesistement;


class ActualiteController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, $projet_id,$user_id,$de_date,$a_date)
    {
        DatabaseHelper::Config();
        if($de_date=='null' && $a_date=='null' ){
            $dt_now=date('Y-m-d');
            $dt = Carbon::createFromFormat('Y-m-d', $dt_now)->startOfDay();
            $a_dt = Carbon::createFromFormat('Y-m-d', $dt_now)->endOfDay();
        }else{

            $dt = Carbon::createFromFormat('Y-m-d', $de_date)->startOfDay();
            $a_dt = Carbon::createFromFormat('Y-m-d', $a_date)->endOfDay();


        }
         //si est un commercial ou admin fait actualite par commercial
        if (RoleHelper::Com()||($user_id!='tous'&&$user_id!='tout')) {

            if($user_id!='tous'){
                $us_id=$user_id;
            }else{
                $user = Auth::user();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                $us_id=$userAuth->value('id');
            }

            $data_v_pre_reserve = [
                'par_commercial' =>  1,
                'user_id' =>  $us_id,
                'de_date' => $dt,
                'a_date' => $a_dt,
                'statut' => StatutVisiteEnum::Pré_Réservation->value,
                'interet' => InteretEnum::Intéressé->value,
                'projet_id' =>$projet_id,
            ];
            $data_v_pre_reserve_perdu = [
                'par_commercial' =>  1,
                'user_id' =>  $us_id,
                'de_date' => $dt,
                'a_date' => $a_dt,
                'statut' => StatutVisiteEnum::Pré_Réservation_Perdu->value,
                'interet' => InteretEnum::Intéressé->value,
                'projet_id' =>$projet_id,
            ];
            $data_v_pre_reserve_vendu = [
                'par_commercial' =>  1,
                'user_id' =>  $us_id,
                'de_date' => $dt,
                'a_date' => $a_dt,
                'statut' => StatutVisiteEnum::Pré_Réservation_Vendu->value,
                'interet' => InteretEnum::Intéressé->value,
                'projet_id' =>$projet_id,
            ];
            $data_v_reserve_perdu = [
                'par_commercial' =>  1,
                'user_id' =>  $us_id,
                'de_date' => $dt,
                'a_date' => $a_dt,
                'statut' => StatutVisiteEnum::Réservation_Perdu->value,
                'interet' => InteretEnum::Intéressé->value,
                'projet_id' =>$projet_id,
            ];
            $data_v_receptif = [
                'par_commercial' =>  1,
                'user_id' =>  $us_id,
                'de_date' => $dt,
                'a_date' => $a_dt,
                'statut' => null,
                'order'=>null,
                'interet' => InteretEnum::Réceptif->value,
                'projet_id' =>$projet_id,
            ];
            $data_v_perdu = [
                'par_commercial' =>  1,
                'user_id' =>  $us_id,
                'de_date' => $dt,
                'a_date' => $a_dt,
                'statut' => null,
                'order' => null,
                'interet' => InteretEnum::Perdu->value,
                'projet_id' =>$projet_id,
            ];
            $data_v_vente_direct = [
                'user_id' =>  $us_id,
                'de_date' => $dt,
                'a_date' => $a_dt,
                'order' => 1,
                'statut' => StatutVisiteEnum::Vendu->value,
                'interet' => InteretEnum::Intéressé->value,
                'projet_id' =>$projet_id,
            ];
            $data_v_vente = [
                'par_commercial' =>  1,
                'user_id' =>  $us_id,
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

            $rdv_relances=Relance_Rdv_Visite::on('temp')->join('visites', 'visites.id', '=', 'relances_rdv_visites.visite_id')
            ->select('relances_rdv_visites.*')
            ->whereBetween('relances_rdv_visites.date_traitement', [$dt, $a_dt])
            ->whereIN('relances_rdv_visites.type_traitement',[2,3])
            ->where('relances_rdv_visites.user_id',$us_id)
            ->where('visites.projet_id', $request->projet_id)->get();

            $nb_visite_last_5_days=Visite::on('temp')
            ->whereBetween('created_at', [Carbon::parse($dt)->subDays(5), Carbon::parse($a_dt)->subDays(5)])
            ->where('projet_id', $request->projet_id)-> where('user_id',$us_id)->count();

            $avances_bien=Avance::on('temp')
            ->join('reservations', 'reservations.id', '=', 'avances.reservation_id')
            ->join('biens', 'biens.id', '=', 'reservations.bien_id')
            ->leftjoin('tranches', 'tranches.id', '=', 'biens.tranche_id')
            ->leftjoin('blocs', 'blocs.id', '=', 'biens.bloc_id')
            ->leftjoin('immeubles', 'immeubles.id', '=', 'biens.immeuble_id')
            ->select('avances.montant','biens.propriete_dite_bien','tranches.nom as tranche_nom','blocs.nom as bloc_nom','immeubles.nom as immeuble_nom')
            ->where('avances.user_id',$us_id)
            ->where('reservations.projet_id', $request->projet_id)
            ->whereBetween('avances.date_reglement', [$dt, $a_dt])
            ->get();
            $sum_avances=0;
            if(count($avances_bien)>0){
            foreach($avances_bien as $av){
                $sum_avances+=$av->montant;
            }
            }
            $avances_bien_last_days=Avance::on('temp')
            ->join('reservations', 'reservations.id', '=', 'avances.reservation_id')
            ->join('biens', 'biens.id', '=', 'reservations.bien_id')
            ->where('avances.user_id',$us_id)
            ->where('reservations.projet_id', $request->projet_id)
            ->whereBetween('avances.date_reglement', [Carbon::parse($dt)->subDays(5), Carbon::parse($a_dt)->subDays(5)])
            ->sum('avances.montant');

            $remboursements=Remboursement::on('temp')
            ->join('desistements', 'desistements.id', '=', 'remboursements.desistement_id')
            ->join('reservations', 'reservations.id', '=', 'remboursements.reservation_id')
            ->join('biens', 'biens.id', '=', 'reservations.bien_id')
            ->leftjoin('tranches', 'tranches.id', '=', 'biens.tranche_id')
            ->leftjoin('blocs', 'blocs.id', '=', 'biens.bloc_id')
            ->leftjoin('immeubles', 'immeubles.id', '=', 'biens.immeuble_id')
            ->select('biens.propriete_dite_bien','remboursements.montant_a_rembourser','tranches.nom as tranche_nom','blocs.nom as bloc_nom','immeubles.nom as immeuble_nom')
            ->where('desistements.user_id',$us_id)
            ->where('reservations.projet_id',$request->projet_id)
            ->whereIN('remboursements.statut',[1,3])
            ->whereBetween('remboursements.date_rembourse', [$dt, $a_dt])
            ->get();
            $sum_remb=0;
            if(count($remboursements)>0){
            foreach($remboursements as $remb){
                $sum_remb+=$remb->montant_a_rembourser;
            }
            }

            $desistements=Desistement::on('temp')
            ->join('biens', 'biens.id', '=', 'desistements.bien_id_ancien')
            ->leftJoin('biens as new_biens', 'new_biens.id', '=', 'desistements.bien_id_new')
            ->join('reservations', 'reservations.id', '=', 'desistements.reservation_id')
            ->leftJoin('penalites_desistements','penalites_desistements.desistement_id','desistements.id')
            ->select('biens.propriete_dite_bien as bien','reservations.code_reservation','desistements.motif','penalites_desistements.montant as penalite','desistements.lien_parente','new_biens.propriete_dite_bien as new_bien','desistements.montant_a_ajouter','desistements.type','desistements.type_dp')
            ->where('desistements.projet_id',$request->projet_id)
            ->where('desistements.user_id',$us_id)
            ->whereBetween('desistements.created_at', [$dt, $a_dt])
             ->get();

             $sum_penalites=0;
             $sum_mont_a_ajouter=0;
             if(count($desistements)>0){
                 foreach($desistements as $ds){
                     $sum_penalites+=$ds->penalite;
                     $sum_mont_a_ajouter+=$ds->montant_a_ajouter;
                 }
                 }
            return response()->json([
                'admin'=>0,
                'visites'=>$Array_visite,
                'sum_visites'=>$sum = array_sum($Array_visite),
                'rdv_relances'=>$rdv_relances,
                'nb_visite_last_5_days'=>$nb_visite_last_5_days,
                'avances_last_5_days'=>$avances_bien_last_days,
                'avances_bien'=>$avances_bien,
                'sum_avances'=>$sum_avances,
                'remboursements'=>$remboursements,
                'sum_remb'=>$sum_remb,
                'desistements'=>$desistements,
                'sum_penalites'=>$sum_penalites,
                'sum_mont_a_ajouter'=>$sum_mont_a_ajouter
            ], 200);

        } else{
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

            $rdv_relances=Relance_Rdv_Visite::on('temp')->join('visites', 'visites.id', '=', 'relances_rdv_visites.visite_id')
            ->select('relances_rdv_visites.*')
            ->whereBetween('relances_rdv_visites.created_at', [$dt,$a_dt])
            ->whereIN('relances_rdv_visites.type_traitement',[2,3])
            ->where('visites.projet_id', $request->projet_id)->get();

            $nb_visite_last_5_days=Visite::on('temp')
            ->whereBetween('created_at', [Carbon::parse($dt)->subDays(5),Carbon::parse($a_dt)->subDays(5)])
            ->where('projet_id', $request->projet_id)->count();

            $avances_bien=Avance::on('temp')
            ->join('reservations', 'reservations.id', '=', 'avances.reservation_id')
            ->join('biens', 'biens.id', '=', 'reservations.bien_id')
            ->leftjoin('tranches', 'tranches.id', '=', 'biens.tranche_id')
            ->leftjoin('blocs', 'blocs.id', '=', 'biens.bloc_id')
            ->leftjoin('immeubles', 'immeubles.id', '=', 'biens.immeuble_id')
            ->select('avances.montant','biens.propriete_dite_bien','tranches.nom as tranche_nom','blocs.nom as bloc_nom','immeubles.nom as immeuble_nom')
            ->where('reservations.projet_id', $request->projet_id)
            ->whereBetween('avances.date_reglement', [$dt, $a_dt])
            ->get();
            $sum_avances=0;
            if(count($avances_bien)>0){
            foreach($avances_bien as $av){
                $sum_avances+=$av->montant;
            }
            }
            $remboursements=Remboursement::on('temp')
            ->join('desistements', 'desistements.id', '=', 'remboursements.desistement_id')
            ->join('reservations', 'reservations.id', '=', 'remboursements.reservation_id')
            ->join('biens', 'biens.id', '=', 'reservations.bien_id')
            ->leftjoin('tranches', 'tranches.id', '=', 'biens.tranche_id')
            ->leftjoin('blocs', 'blocs.id', '=', 'biens.bloc_id')
            ->leftjoin('immeubles', 'immeubles.id', '=', 'biens.immeuble_id')
            ->select('biens.propriete_dite_bien','remboursements.montant_a_rembourser','tranches.nom as tranche_nom','blocs.nom as bloc_nom','immeubles.nom as immeuble_nom')
            ->where('reservations.projet_id',$request->projet_id)
            ->whereIN('remboursements.statut',[1,3])
            ->whereBetween('remboursements.date_rembourse', [$dt, $a_dt])
            ->get();
            $sum_remb=0;
            if(count($remboursements)>0){
            foreach($remboursements as $remb){
                $sum_remb+=$remb->montant_a_rembourser;
            }
            }

            $desistements=Desistement::on('temp')
            ->join('biens', 'biens.id', '=', 'desistements.bien_id_ancien')
            ->leftJoin('biens as new_biens', 'new_biens.id', '=', 'desistements.bien_id_new')
            ->join('reservations', 'reservations.id', '=', 'desistements.reservation_id')
            ->leftJoin('penalites_desistements','penalites_desistements.desistement_id','desistements.id')
            ->select('desistements.id','biens.propriete_dite_bien as bien','reservations.code_reservation','desistements.motif','penalites_desistements.montant as penalite','desistements.lien_parente','new_biens.propriete_dite_bien as new_bien','desistements.montant_a_ajouter','desistements.type','desistements.type_dp')
            ->where('desistements.projet_id',$request->projet_id)
            ->whereBetween('desistements.created_at', [$dt, $a_dt])
             ->get();

            $sum_penalites=0;
            $sum_mont_a_ajouter=0;
            if(count($desistements)>0){
                foreach($desistements as $ds){
                    $sum_penalites+=$ds->penalite;
                    $sum_mont_a_ajouter+=$ds->montant_a_ajouter;
                }
                }

            $avances_bien_last_days=Avance::on('temp')
                ->join('reservations', 'reservations.id', '=', 'avances.reservation_id')
                ->join('biens', 'biens.id', '=', 'reservations.bien_id')
                ->where('reservations.projet_id', $request->projet_id)
                ->whereBetween('avances.date_reglement', [Carbon::parse($dt)->subDays(5),Carbon::parse($a_dt)->subDays(5)])
                ->sum('avances.montant');
            return response()->json([
                'admin'=>1,
                'visites'=>$Array_visite,
                'sum_visites'=>$sum = array_sum($Array_visite),
                'rdv_relances'=>$rdv_relances,
                'nb_visite_last_5_days'=>$nb_visite_last_5_days,
                'avances_last_5_days'=>$avances_bien_last_days,
                'avances_bien'=>$avances_bien,
                'sum_avances'=>$sum_avances,
                'remboursements'=>$remboursements,
                'sum_remb'=>$sum_remb,
                'desistements'=>$desistements,
                'sum_penalites'=>$sum_penalites,
                'sum_mont_a_ajouter'=>$sum_mont_a_ajouter,
                'de_date'=>$dt,
                'a_date'=>$a_dt,

            ], 200);


        }
    }

    public  function get_visites(Request $request)
    {
        DatabaseHelper::Config();
        //si est un commercial ou admin fait actualite par commercial
        if(RoleHelper::Com()||$request->par_commercial==1){
            //comm
            if($request->order==1){
                //first visite
                $nb_visite = Visite::on('temp')
                ->whereBetween('created_at', [$request->de_date, $request->a_date])
                ->where('etat',1)
                ->where('user_id',$request->user_id)
                ->where('old_v_id',null)
                ->where('interet',$request->interet)
                ->where('statut',$request->statut)
                ->where('projet_id', $request->projet_id)->count();
            }else{
                if($request->statut<=2){
                    //pre reserve ou vendu
                    $nb_visite = Visite::on('temp')
                    ->whereBetween('created_at', [$request->de_date, $request->a_date])
                    ->where('user_id',$request->user_id)
                    ->where('etat',1)
                    ->where('interet',$request->interet)
                    ->where('statut',$request->statut)
                    ->where('projet_id', $request->projet_id)->count();
                }else{
                    $nb_visite = Visite::on('temp')
                    ->whereBetween('updated_at', [$request->de_date, $request->a_date])
                    ->where('user_id',$request->user_id)
                    ->where('etat',1)
                    ->where('interet',$request->interet)
                    ->where('statut',$request->statut)
                    ->where('projet_id', $request->projet_id)->count();
                }

            }
            return response()->json(['nb_v' => $nb_visite], 200);
        }else{

            if($request->order==1){
                //first visite
                $nb_visite = Visite::on('temp')
                /*->whereDate('created_at','<=',$request->de_date)
                ->whereDate('created_at','>=',$request->a_date)*/
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
                   /* ->whereDate('created_at','<=',$request->de_date)
                     ->whereDate('created_at','>=',$request->a_date)*/
                   ->whereBetween('created_at', [$request->de_date, $request->a_date])
                    ->where('etat',1)
                    ->where('interet',$request->interet)
                    ->where('statut',$request->statut)
                    ->where('projet_id', $request->projet_id)->count();
                }else{
                    $nb_visite = Visite::on('temp')
                  /*  ->whereDate('updated_at','<=',$request->de_date)
                ->whereDate('updated_at','>=',$request->a_date)*/
                    ->whereBetween('updated_at', [$request->de_date, $request->a_date])
                    ->where('etat',1)
                    ->where('interet',$request->interet)
                    ->where('statut',$request->statut)
                    ->where('projet_id', $request->projet_id)->count();
                }

            }
            return response()->json(['nb_v' => $nb_visite], 200);
        }

    }

    public function get_historique($date,$id,$type)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            if($id='tous'){
                //admin

                $histo=Avance::on('temp')
                ->join('reservations', 'reservations.id', '=', 'avances.reservation_id')
                ->join('biens', 'biens.id', '=', 'reservations.bien_id')
                ->leftjoin('tranches', 'tranches.id', '=', 'biens.tranche_id')
                ->leftjoin('blocs', 'blocs.id', '=', 'biens.bloc_id')
                ->leftjoin('immeubles', 'immeubles.id', '=', 'biens.immeuble_id')
                ->select('avances.montant','biens.propriete_dite_bien','tranches.nom as tranche_nom','blocs.nom as bloc_nom','immeubles.nom as immeuble_nom')
                ->whereDate('avances.date_reglement',$date)->get();
                $sum_avances=0;
                    if(count($histo)>0){
                    foreach($histo as $av){
                        $sum_avances+=$av->montant;
                    }
                }
                return response()->json(['admin' => 0,'historiques' => $histo,'sum_avances'=>$sum_avances], 200);

            }else{
                //par user_id
                $user = Auth::user();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();

                $histo=Avance::on('temp')
                ->join('reservations', 'reservations.id', '=', 'avances.reservation_id')
                ->join('biens', 'biens.id', '=', 'reservations.bien_id')
                ->leftjoin('tranches', 'tranches.id', '=', 'biens.tranche_id')
                ->leftjoin('blocs', 'blocs.id', '=', 'biens.bloc_id')
                ->leftjoin('immeubles', 'immeubles.id', '=', 'biens.immeuble_id')
                ->select('avances.montant','biens.propriete_dite_bien','tranches.nom as tranche_nom','blocs.nom as bloc_nom','immeubles.nom as immeuble_nom')
                ->where('avances.user_id',$userAuth->value('id'))->whereDate('avances.date_reglement',$date)->get();

                $sum_avances=0;
                    if(count($histo)>0){
                    foreach($histo as $av){
                        $sum_avances+=$av->montant;
                    }
                }
                return response()->json(['historiques' => $histo,'sum_avances'=>$sum_avances], 200);
            }


        } else {
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
