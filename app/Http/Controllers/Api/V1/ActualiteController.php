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
use App\Models\Reservation;

use App\Models\Prospect;

use App\Models\PenaliteDesistement;


class ActualiteController extends Controller
{

    /**
     * Display a listing of the resource.
     */

         public function index(Request $request, $projet_id, $user_id, $de_date, $a_date)
            {
                DatabaseHelper::Config();

                // Gestion des dates
                [$dt, $a_dt] = $this->getDateRange($de_date, $a_date);

                if (RoleHelper::Com() || ($user_id != 'tous' && $user_id != 'tout')) {
                    // Mode commercial
                    $us_id = $this->getUserId($user_id);

                    $results = $this->getCommercialData($request, $projet_id, $us_id, $dt, $a_dt);

                    return response()->json(array_merge(['admin' => 0], $results), 200);
                } else {
                    // Mode admin
                    $results = $this->getAdminData($request, $projet_id, $dt, $a_dt);

                    return response()->json(array_merge(['admin' => 1], $results), 200);
                }
            }

// Méthodes auxiliaires pour simplifier le code principal

        private function getDateRange($de_date, $a_date)
        {
            if ($de_date == 'null' && $a_date == 'null') {
                $dt_now = date('Y-m-d');
                return [
                    Carbon::createFromFormat('Y-m-d', $dt_now)->startOfDay(),
                    Carbon::createFromFormat('Y-m-d', $dt_now)->endOfDay()
                ];
            }

            return [
                Carbon::createFromFormat('Y-m-d', $de_date)->startOfDay(),
                Carbon::createFromFormat('Y-m-d', $a_date)->endOfDay()
            ];
}

        private function getUserId($user_id)
        {
            if ($user_id != 'tous') {
                if (RoleHelper::Com()) {
                    $userAuth = User::on('temp')->where('user_id_origin', $user_id)->first();
                    return $userAuth->id;
                }
                return $user_id;
            }
            //else
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();
            return $userAuth->id ?? $user->getAuthIdentifier();
        }

        private function getVisitesData(Request $request, $projet_id, $user_id, $dt, $a_dt, $isCommercial = false)
        {
            $types = [
                ['statut' => null, 'interet' => InteretEnum::Réceptif->value, 'order' => null],
                ['statut' => StatutVisiteEnum::Pré_Réservation->value, 'interet' => InteretEnum::Intéressé->value],
                ['statut' => StatutVisiteEnum::Pré_Réservation_Perdu->value, 'interet' => InteretEnum::Intéressé->value],
                ['statut' => StatutVisiteEnum::Pré_Réservation_Vendu->value, 'interet' => InteretEnum::Intéressé->value],
                ['statut' => StatutVisiteEnum::Vendu->value, 'interet' => InteretEnum::Intéressé->value, 'order' => 1],
                ['statut' => StatutVisiteEnum::Vendu->value, 'interet' => InteretEnum::Intéressé->value],
                ['statut' => StatutVisiteEnum::Réservation_Perdu->value, 'interet' => InteretEnum::Intéressé->value],
                ['statut' => null, 'interet' => InteretEnum::Perdu->value, 'order' => null]
            ];

            $Array_visite = [];

            foreach ($types as $type) {
                $data = array_merge([
                    'de_date' => $dt,
                    'a_date' => $a_dt,
                    'projet_id' => $projet_id,
                    'par_commercial' => $isCommercial ? 1 : 0,
                    'user_id' => $isCommercial ? $user_id : null
                ], $type);

                array_push($Array_visite, $this->get_visites($request->merge($data))->original['nb_v']);
            }

            return [
                'visites' => $Array_visite,
                'sum_visites' => array_sum($Array_visite)
            ];
        }

        private function getCommercialData(Request $request, $projet_id, $user_id, $dt, $a_dt)
        {
            $visitesData = $this->getVisitesData($request, $projet_id, $user_id, $dt, $a_dt, true);

            $rdv_relances = Relance_Rdv_Visite::on('temp')
                ->join('visites', 'visites.id', '=', 'relances_rdv_visites.visite_id')
                ->whereBetween('relances_rdv_visites.date_traitement', [$dt, $a_dt])
                ->whereIn('relances_rdv_visites.type_traitement', [2, 3])
                ->where('relances_rdv_visites.user_id', $user_id)
                ->where('visites.projet_id', $projet_id)
                ->get();

           /* $nb_visite_last_5_days = Visite::on('temp')
                ->whereBetween('created_at', [Carbon::parse($dt)->subDays(5), Carbon::parse($a_dt)->subDays(5)])
                ->where('projet_id', $projet_id)
                ->where('user_id', $user_id)
                ->count();*/

            $avancesData = $this->getAvancesData($projet_id, $user_id, $dt, $a_dt);
              $reservationsData = $this->getReservationData($projet_id, $user_id, $dt, $a_dt);
            $remboursementsData = $this->getRemboursementsData($projet_id, $user_id, $dt, $a_dt);
            $desistementsData = $this->getDesistementsData($projet_id, $user_id, $dt, $a_dt);
             $traitementProspectData = $this->getTraitementProspect($projet_id, $user_id, $dt, $a_dt);


          /*  $avances_bien_last_days = Avance::on('temp')
                ->join('reservations', 'reservations.id', '=', 'avances.reservation_id')
                ->join('biens', 'biens.id', '=', 'reservations.bien_id')
                ->where('avances.user_id', $user_id)
                ->where('reservations.projet_id', $projet_id)
                 ->where('reservations.deleted_at', null)
                ->where('reservations.etat', 1)
                ->whereBetween('avances.date_reglement', [Carbon::parse($dt)->subDays(5), Carbon::parse($a_dt)->subDays(5)])
                ->sum('avances.montant');*/

            return array_merge($visitesData, [
                'rdv_relances' => $rdv_relances,
               // 'nb_visite_last_5_days' => $nb_visite_last_5_days,
              //  'avances_last_5_days' => $avances_bien_last_days,
                'avances_bien' => $avancesData['avances'],
                'sum_avances' => $avancesData['sum'],
                 'reservations' => $reservationsData['reservations'],
                'remboursements' => $remboursementsData['remboursements'],
                'sum_remb' => $remboursementsData['sum'],
                'desistements' => $desistementsData['desistements'],
                'sum_penalites' => $desistementsData['sum_penalites'],
                'sum_mont_a_ajouter' => $desistementsData['sum_mont_a_ajouter'],
                'traitements_prospects' => $traitementProspectData['traitements'],
            ]);
        }

        private function getAdminData(Request $request, $projet_id, $dt, $a_dt)
        {
            $visitesData = $this->getVisitesData($request, $projet_id, null, $dt, $a_dt, false);

            $rdv_relances = Relance_Rdv_Visite::on('temp')
                ->join('visites', 'visites.id', '=', 'relances_rdv_visites.visite_id')
                ->whereBetween('relances_rdv_visites.created_at', [$dt, $a_dt])
                ->whereIn('relances_rdv_visites.type_traitement', [2, 3])
                ->where('visites.projet_id', $projet_id)
                ->get();

          /*  $nb_visite_last_5_days = Visite::on('temp')
                ->whereBetween('created_at', [Carbon::parse($dt)->subDays(5), Carbon::parse($a_dt)->subDays(5)])
                ->where('projet_id', $projet_id)
                ->count();*/

            $avancesData = $this->getAvancesData($projet_id, null, $dt, $a_dt);
            $reservationsData = $this->getReservationData($projet_id, null, $dt, $a_dt);

            $remboursementsData = $this->getRemboursementsData($projet_id, null, $dt, $a_dt);
            $desistementsData = $this->getDesistementsData($projet_id, null, $dt, $a_dt);
             $traitementProspectData = $this->getTraitementProspect($projet_id, null, $dt, $a_dt);

          /*  $avances_bien_last_days = Avance::on('temp')
                ->join('reservations', 'reservations.id', '=', 'avances.reservation_id')
                ->join('biens', 'biens.id', '=', 'reservations.bien_id')
                ->where('reservations.projet_id', $projet_id)
                 ->where('reservations.deleted_at', null)
                ->where('reservations.etat', 1)
                ->whereBetween('avances.date_reglement', [Carbon::parse($dt)->subDays(5), Carbon::parse($a_dt)->subDays(5)])
                ->sum('avances.montant');*/

            return array_merge($visitesData, [
                'rdv_relances' => $rdv_relances,
               // 'nb_visite_last_5_days' => $nb_visite_last_5_days,
               // 'avances_last_5_days' => $avances_bien_last_days,
                'avances_bien' => $avancesData['avances'],
                'sum_avances' => $avancesData['sum'],
                  'reservations' => $reservationsData['reservations'],
                'remboursements' => $remboursementsData['remboursements'],
                'sum_remb' => $remboursementsData['sum'],
                'desistements' => $desistementsData['desistements'],
                'sum_penalites' => $desistementsData['sum_penalites'],
                'sum_mont_a_ajouter' => $desistementsData['sum_mont_a_ajouter'],
                'de_date' => $dt,
                'a_date' => $a_dt,
                'traitements_prospects' => $traitementProspectData['traitements'],
            ]);
        }

        private function getAvancesData($projet_id, $user_id, $dt, $a_dt)
        {
            $query = Avance::on('temp')
                ->join('reservations', 'reservations.id', '=', 'avances.reservation_id')
                ->join('biens', 'biens.id', '=', 'reservations.bien_id')
                ->leftjoin('tranches', 'tranches.id', '=', 'biens.tranche_id')
                ->leftjoin('blocs', 'blocs.id', '=', 'biens.bloc_id')
                ->leftjoin('immeubles', 'immeubles.id', '=', 'biens.immeuble_id')
                ->select('avances.montant','reservations.code_reservation','avances.date_reglement','biens.propriete_dite_bien', 'tranches.nom as tranche_nom', 'blocs.nom as bloc_nom', 'immeubles.nom as immeuble_nom')
                ->where('reservations.projet_id', $projet_id)
                ->where('reservations.deleted_at', null)
                ->where('reservations.etat', 1)
                ->whereBetween('avances.date_reglement', [$dt, $a_dt]);

            if ($user_id) {
                $query->where('avances.user_id', $user_id);
            }

            $avances = $query->get();
            $sum = $avances->sum('montant');

            return ['avances' => $avances, 'sum' => $sum];
        }
         private function getReservationData($projet_id, $user_id, $dt, $a_dt)
        {
            $query = Reservation::on('temp')->without('bien', 'user', 'projet','aquereurs','aquereurs_ancien','historiques','piece_jointe')->join('biens', 'biens.id', '=', 'reservations.bien_id')
                ->leftjoin('tranches', 'tranches.id', '=', 'biens.tranche_id')
                ->leftjoin('blocs', 'blocs.id', '=', 'biens.bloc_id')
                ->leftjoin('immeubles', 'immeubles.id', '=', 'biens.immeuble_id')
                ->select('reservations.code_reservation','reservations.created_at','biens.propriete_dite_bien', 'tranches.nom as tranche_nom', 'blocs.nom as bloc_nom', 'immeubles.nom as immeuble_nom')
                ->where('reservations.projet_id', $projet_id)
                ->whereBetween('reservations.created_at', [$dt, $a_dt]);

            if ($user_id) {
                $query->where('reservations.user_id', $user_id);
            }

            $reservations = $query->get();


            return ['reservations' => $reservations];
        }

        private function getRemboursementsData($projet_id, $user_id, $dt, $a_dt)
        {
            $query = Remboursement::on('temp')
                ->join('desistements', 'desistements.id', '=', 'remboursements.desistement_id')
                ->join('reservations', 'reservations.id', '=', 'remboursements.reservation_id')
                ->join('biens', 'biens.id', '=', 'reservations.bien_id')
                ->leftjoin('tranches', 'tranches.id', '=', 'biens.tranche_id')
                ->leftjoin('blocs', 'blocs.id', '=', 'biens.bloc_id')
                ->leftjoin('immeubles', 'immeubles.id', '=', 'biens.immeuble_id')
                ->select('biens.propriete_dite_bien', 'remboursements.montant_a_rembourser', 'tranches.nom as tranche_nom', 'blocs.nom as bloc_nom', 'immeubles.nom as immeuble_nom')
                ->where('reservations.projet_id', $projet_id)
                 ->where('reservations.deleted_at', null)

                ->whereIn('remboursements.statut', [1, 3])
                ->whereBetween('remboursements.date_rembourse', [$dt, $a_dt]);

            if ($user_id) {
                $query->where('desistements.user_id', $user_id);
            }

            $remboursements = $query->get();
            $sum = $remboursements->sum('montant_a_rembourser');

            return ['remboursements' => $remboursements, 'sum' => $sum];
        }
            private function getTraitementProspect($projet_id, $user_id, $dt, $a_dt)
                {
                    $query = Prospect::on('temp')
                        ->with(['last_statut' => function($query) use ($dt, $a_dt, $user_id) {
                            $query->whereBetween('created_at', [$dt, $a_dt]);
                            if ($user_id) {
                                $query->where('user_id_traite', $user_id);
                            }
                        }])
                        ->without('source','partenaire','affecte_par_admin','traite_par_user','commercial_affecte')
                        ->select('id', 'nom','prenom') // Added id which is needed for relationships
                        ->where('projet_id', $projet_id)
                        ->whereHas('last_statut', function($query) use ($dt, $a_dt, $user_id) {
                            $query->whereBetween('created_at', [$dt, $a_dt]);
                            if ($user_id) {
                                $query->where('user_id_traite', $user_id);
                            }
                        });

                    $traitements_prospect = $query->get();

                    $filtered = $traitements_prospect->filter(function($prospect) {
                        return $prospect->last_statut !== null;
                    });

                    return ['traitements' => $filtered->values()];
                }

        private function getDesistementsData($projet_id, $user_id, $dt, $a_dt)
        {
            $query = Desistement::on('temp')->without('reservation_ancien','Bien_ancien','user')
                ->join('biens', 'biens.id', '=', 'desistements.bien_id_ancien')
                ->leftJoin('biens as new_biens', 'new_biens.id', '=', 'desistements.bien_id_new')
                        // Left joins for the original bien

                ->leftjoin('tranches', 'tranches.id', '=', 'biens.tranche_id')
                ->leftjoin('blocs', 'blocs.id', '=', 'biens.bloc_id')
                ->leftjoin('immeubles', 'immeubles.id', '=', 'biens.immeuble_id')
                // Left joins for the new bien (only if needed)

                ->leftjoin('tranches as new_bien_tranche', 'new_bien_tranche.id', '=', 'new_biens.tranche_id')
                ->leftjoin('blocs as new_bien_bloc', 'new_bien_bloc.id', '=', 'new_biens.bloc_id')
                ->leftjoin('immeubles as new_bien_immeuble', 'new_bien_immeuble.id', '=', 'new_biens.immeuble_id')

                ->join('reservations', 'reservations.id', '=', 'desistements.reservation_id')
                ->leftJoin('penalites_desistements', 'penalites_desistements.desistement_id', 'desistements.id')
                ->select('desistements.id', 'biens.propriete_dite_bien as bien', 'reservations.code_reservation',
                 'desistements.motif', 'penalites_desistements.montant as penalite', 'desistements.lien_parente',
                 'new_biens.propriete_dite_bien as new_bien', 'desistements.montant_a_ajouter', 'desistements.type', 'desistements.type_dp',
                'tranches.nom as tranche_nom', 'blocs.nom as bloc_nom', 'immeubles.nom as immeuble_nom',
                'new_bien_tranche.nom as new_tranche_nom', 'new_bien_bloc.nom as new_bloc_nom', 'new_bien_immeuble.nom as new_immeuble_nom')
                ->where('desistements.projet_id', $projet_id)
                 ->where('reservations.deleted_at', null)
                ->whereBetween('desistements.created_at', [$dt, $a_dt]);

            if ($user_id) {
                $query->where('desistements.user_id', $user_id);
            }

            $desistements = $query->get();

            return [
                'desistements' => $desistements,
                'sum_penalites' => $desistements->sum('penalite'),
                'sum_mont_a_ajouter' => $desistements->sum('montant_a_ajouter')
            ];
        }

        public function get_visites(Request $request)
        {
            DatabaseHelper::Config();

            $query = Visite::on('temp')
                ->where('etat', 1)
                ->where('interet', $request->interet)
                ->where('statut', $request->statut)
                ->where('projet_id', $request->projet_id);

            // Filtre par utilisateur pour les commerciaux
            if (RoleHelper::Com() || $request->par_commercial == 1) {
                $query->where('user_id', $request->user_id);
            }

            // Gestion de la date et de l'ordre
            if ($request->order == 1) {
                $query->where('old_v_id', null)
                    ->whereBetween('created_at', [$request->de_date, $request->a_date]);
            } else {
                if ($request->statut <= 2) {
                    $query->whereBetween('created_at', [$request->de_date, $request->a_date]);
                } else {
                    $query->whereBetween('updated_at', [$request->de_date, $request->a_date]);
                }
            }

            $nb_visite = $query->count();

            return response()->json(['nb_v' => $nb_visite], 200);
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
                ->whereDate('avances.date_reglement',$date) ->where('reservations.deleted_at', null)
                ->where('reservations.etat', 1)->get();
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
                ->where('avances.user_id',$userAuth->value('id'))->whereDate('avances.date_reglement',$date) ->where('reservations.deleted_at', null)
                ->where('reservations.etat', 1)->get();

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
