<?php
namespace App\Http\Controllers\Api\V1;

use App\Events\NewProjectEvent;
use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Helpers\UserProjetHelper;
use App\Http\Requests\StoreProjetRequest;
use App\Http\Requests\UpdateProjetRequest;
use App\Models\Avance;
use App\Models\Bien;
use App\Models\Bloc;
use App\Models\Desistement;
use App\Models\HistoriqueBien;
use App\Models\HistoriqueDesistement;
use App\Models\Immeuble;
use App\Models\PreReservation;
use App\Models\Projet;
use App\Models\Tranche;
use App\Models\User;
use App\Models\UserProjet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

class ProjetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function get_projets()
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            Config::set('broadcasting.default', 'pusher_2');
            $projets = Projet::on('temp')->with('typesBien')->orderBy('created_at', 'asc')->get();
            // broadcast(new NewProjectEvent($projets->id));
            return response()->json(['projets' => $projets]);
        } else if (RoleHelper::Com()) {
            DatabaseHelper::Config();
            $id_auth = Auth::guard('api')->user()->id;
            $user_id = User::on('temp')->where('user_id_origin', $id_auth)->pluck('id');
            Config::set('broadcasting.default', 'pusher_2');
            $projets = Projet::on('temp')->with('typesBien')
                ->join('user_projets', 'user_projets.projet_id', '=', 'projets.id')
                ->where('user_projets.user_id', $user_id)
                ->select('projets.*')
                ->get();
            //  broadcast(new NewProjectEvent($projets->id));

            return response()->json(['projets' => $projets]);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /*public function get_projets_user($societe_id,$user_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config($societe_id);
            $projets_user=null;
            $projets = Projet::on('temp')->orderBy('created_at', 'asc')->get();
            if($user_id!=0){
                $user=User::on('temp')->where('user_id_origin',$user_id)->first();
                $projets_user=UserProjet::on('temp')->with('projet')->where('user_id',$user->id)->get();
            }
            return response()->json(['projets' => $projets,'projets_user'=>$projets_user]);
        }

        else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }*/

    public function get_projets_user($user_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $projets = Projet::on('temp')->orderBy('created_at', 'asc')->get();
            if ($user_id != 0) {
                $projets_user = UserProjet::on('temp')->with('projet')->where('user_id', $user_id)->get();
            }
            return response()->json(['projets' => $projets, 'projets_user' => $projets_user]);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function index(Request $request)
    {
        if (! RoleHelper::AdminSup() && ! RoleHelper::Com()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $size = $request->input('size', null);
        $page = $request->input('page', null);
        DatabaseHelper::Config();

        // Démarrer la requête
        $query = Projet::on('temp')->with('typesBien');

        // Appliquer les filtres
        if ($request->filled('nom')) {
            $query->where('nom', 'like', '%' . $request->input('nom') . '%');
        }
        if ($request->filled('adresse')) {
            $query->where('adresse', 'like', '%' . $request->input('adresse') . '%');
        }
        if ($request->filled('code')) {
            $query->where('code', 'like', '%' . $request->input('code') . '%');
        }
        if ($request->filled('type')) {
            $query->where('type_id', 'like', '%' . $request->input('type') . '%');
        }
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        // Restriction par rôle
        if (RoleHelper::Com()) {
            // Commercial connecté : voir uniquement ses projets
            $id_auth = Auth::guard('api')->user()->id;
            $user_id = User::on('temp')->where('user_id_origin', $id_auth)->pluck('id');

            $query->join('user_projets', 'user_projets.projet_id', '=', 'projets.id')
                ->whereIn('user_projets.user_id', $user_id)
                ->select('projets.*');
        } elseif (RoleHelper::AdminSup() && $request->filled('user_id')) {
            // Récupération de l'ID réel du user à partir du user_id_origin
            $user_ = User::on('temp')->where('user_id_origin', $request->user_id)->pluck('id');

            if ($user_) {
                $query->join('user_projets', 'user_projets.projet_id', '=', 'projets.id')
                    ->where('user_projets.user_id', 19)
                    ->select('projets.*'); // évite les doublons/erreurs de colonnes

            } else {
                // Aucun user trouvé : renvoyer 0 résultat
                $query->whereRaw('1                                                                                                                                                                                                                                                                                                         = 0');
            }
        }
        // sinon : Admin sans user_id -> voit tout (pas de restriction)

        // Pagination
        if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
            $projetsPaginated = $query->orderBy('created_at', 'desc')
                ->paginate($size, ['*'], 'page', $page);

            return response()->json([
                'projets'    => $projetsPaginated->items(),
                'pagination' => [
                    'currentPage' => $projetsPaginated->currentPage(),
                    'totalItems'  => $projetsPaginated->total(),
                    'totalPages'  => $projetsPaginated->lastPage(),
                ],
            ]);
        } else {
            // Sans pagination
            $projets = $query->orderBy('created_at', 'desc')->get();
            return response()->json(['projets' => $projets]);
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
    public function store(StoreProjetRequest $request)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            Config::set('broadcasting.default', 'pusher_2');

            $projet = new Projet();
            $projet->setConnection('temp');
            $projet->nom                            = $request->nom;
            $projet->code                           = $request->code;
            $projet->adresse                        = $request->adresse;
            $projet->date_autorisation_construction = $request->date_autorisation_construction;
            $projet->date_permis_habiter            = $request->date_permis_habiter;
            $projet->titre_foncier                  = $request->titre_foncier;
            $projet->surface_terrain                = $request->surface_terrain;
            $projet->prix_acquisition               = $request->prix_acquisition;
            $projet->limite_annulation_reservation  = $request->limite_annulation_reservation;
            $projet->type_id                        = $request->type_id;
            $projet->prolongation_reservation       = $request->prolongation_reservation ?: 0;
            $projet->nbre_tranches                  = $request->nbre_tranches ?: 0;
            $projet->nbre_blocs                     = $request->nbre_blocs ?: 0;
            $projet->nbre_immeubles                 = $request->nbre_immeubles ?: 0;
            $projet->max_etages                     = $request->max_etages;
            $projet->nbre_biens                     = $request->nbre_biens ?: 0;
            if ($projet->save()) {
                $dataArray_donneesTypeBien  = json_decode($request->input('donneesTypeBien', '[]'), true);
                $dataArray_donneesVue       = json_decode($request->input('donneesVue', '[]'), true);
                $dataArray_donneesTypologie = json_decode($request->input('donneesTypologie', '[]'), true);
                $dataArray_partenaires      = json_decode($request->input('partenaires', '[]'), true);
                $dataArray_users            = json_decode($request->input('selectedUsers', '[]'), true);

                if ($dataArray_donneesTypeBien) {
                    foreach ($dataArray_donneesTypeBien as $typeBien) {
                        TypeBienController::AjouterTypeBien($typeBien, $projet->id);
                    }
                }
                if ($dataArray_donneesVue) {
                    foreach ($dataArray_donneesVue as $vue) {
                        VueController::AjouterVue($vue, $projet->id);
                    }
                }
                if ($dataArray_donneesTypologie) {
                    foreach ($dataArray_donneesTypologie as $Typologie) {
                        TypologieController::AjouterTypologie($Typologie, $projet->id);
                    }
                }
                if ($dataArray_partenaires) {
                    foreach ($dataArray_partenaires as $Partenaire) {
                        PartenaireController::AjouterPartenaire($Partenaire, $projet->id);
                    }
                }

                $all = 0;
                if (is_array($dataArray_users)) {
                    foreach ($dataArray_users as $valeur) {
                        $userId = is_array($valeur) ? ($valeur['id'] ?? null) : $valeur;

                        if ($userId == 'tous') {
                            $all = 1;
                            break;
                        }
                    }

                    if ($all == 1) {
                        DatabaseHelper::Config();
                        $users = User::on('temp')->get(['id']);
                        foreach ($users as $us) {
                            UserProjetHelper::createUserProjet($projet->id, $us->id);
                        }
                        return response()->json(['projet' => $projet], 200);
                    } else {
                        foreach ($dataArray_users as $valeur) {
                            $userId = is_array($valeur) ? ($valeur['id'] ?? null) : $valeur;

                            if ($userId) {
                                UserProjetHelper::createUserProjet($projet->id, $userId);
                            }
                        }
                        broadcast(new NewProjectEvent($projet->id));
                        return response()->json(['projet' => $projet], 200);
                    }
                }
            }

        } else {
            return response()->json(['errors' => 'Unauthorized'], 401);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $projet = Projet::on('temp')->with('tranche', 'bloc', 'immeuble', 'typesBien')->withCount(['bloc', 'tranche', 'immeuble', 'bien'])->findOrfail($id);
            return response()->json(['projet' => $projet], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $projet = Projet::on('temp')->findOrfail($id);
            return response()->json(['message' => $projet], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProjetRequest $request, $id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            Config::set('broadcasting.default', 'pusher_2');

            $projet                                 = Projet::on('temp')->findOrfail($id);
            $projet->nom                            = $request->nom;
            $projet->code                           = $request->code;
            $projet->adresse                        = $request->adresse;
            $projet->date_autorisation_construction = $request->date_autorisation_construction;
            $projet->date_permis_habiter            = $request->date_permis_habiter;
            $projet->titre_foncier                  = $request->titre_foncier;
            $projet->surface_terrain                = $request->surface_terrain;
            $projet->prix_acquisition               = $request->prix_acquisition;
            $projet->limite_annulation_reservation  = $request->limite_annulation_reservation;
            $projet->type_id                        = $request->type_id;
            $projet->prolongation_reservation       = $request->prolongation_reservation ?: 0;
            $projet->nbre_tranches                  = $request->nbre_tranches ?: 0;
            $projet->nbre_blocs                     = $request->nbre_blocs ?: 0;
            $projet->nbre_immeubles                 = $request->nbre_immeubles ?: 0;
            $projet->nbre_biens                     = $request->nbre_biens ?: 0;
            $projet->max_etages                     = $request->max_etages;
            if ($projet->save()) {

                // Supprime les anciens liens user_projet liés à ce projet
                UserProjet::on('temp')->where('projet_id', $id)->delete();

                if (! empty($request->selectedUsers)) {
                    // Décoder la chaîne JSON en tableau PHP
                    $array_user = json_decode($request->selectedUsers, true);

                    // Vérifier que c'est bien un tableau
                    if (is_array($array_user)) {
                        // Créer les liens user_projet pour chaque utilisateur sélectionné
                        foreach ($array_user as $userId) {
                            UserProjetHelper::createUserProjet($projet->id, $userId);
                        }
                    }}}





        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */

    public function destroy($id)
    {
        if (! RoleHelper::AdminSup()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        DatabaseHelper::Config();
        Config::set('broadcasting.default', 'pusher_2');

        $projet = Projet::on('temp')->findOrFail($id);
        $projet->setConnection('temp');

        $projet->load([
            'bloc', 'immeuble', 'bien', 'typesBien', 'typologies',
            'prospects', 'partenaires', 'visites', 'reservations',
            'appels', 'clients', 'notifications', 'fournisseurs',
            'decomptes', 'factures', 'cps', 'credits', 'objectifs',
            'reclamations', 'remise_cles', 'import', 'echeance_projet',
        ]);

        // Suppression des tranches
        $tranches = Tranche::on('temp')->where('projet_id', $id)->get();
        foreach ($tranches as $tr) {
            (new TrancheController())->destroy($tr->id);
        }

        // Suppression des blocs
        foreach ($projet->bloc as $bloc) {
            (new BlocController())->destroy($bloc->id);
        }

        // Suppression des immeubles
        foreach ($projet->immeuble as $imm) {
            (new ImmeubleController())->destroy($imm->id);
        }

        // Suppression des biens
        foreach ($projet->bien as $bi) {
            (new BienController())->destroy($bi->id);
        }

        // Suppression des types de bien
        foreach ($projet->typesBien as $type) {
            $type->setConnection('temp')->delete();
        }

        // Suppression des typologies
        foreach ($projet->typologies as $typologie) {
            $typologie->setConnection('temp')->delete();
        }

        // Suppression des prospects
        foreach ($projet->prospects as $prospect) {
            $prospect->setConnection('temp')->delete();
        }

        // Suppression des affectations utilisateurs
        $user_projets = UserProjet::on('temp')->where('projet_id', $id)->get();
        foreach ($user_projets as $userProjet) {
            $userProjet->delete();
        }

        // Suppression des partenaires
        foreach ($projet->partenaires as $partenaire) {
            $partenaire->setConnection('temp')->delete();
        }

        // Suppression des visites
        foreach ($projet->visites as $visite) {
            (new VisiteController())->destroy($visite->id);
        }

        // Suppression des réservations
        foreach ($projet->reservations as $res) {
            (new ReservationController())->destroy($res->id);
        }

        // Suppression des appels
        foreach ($projet->appels as $appel) {
            (new AppelController())->destroy($appel->id);
        }

        // Suppression des clients
        foreach ($projet->clients as $client) {
            (new ClientController())->destroy($client->id);
        }

        // Suppression des notifications
        foreach ($projet->notifications as $notif) {
            $notif->delete();
        }

        // Suppression des désistements
        $desistements = Desistement::on('temp')->where(function ($query) use ($id) {
            $query->where('bien_id_ancien', $id)->orWhere('bien_id_new', $id);
        })->get();

        foreach ($desistements as $des) {
            $biens = Bien::on('temp')->where('desistement_id', $des->id)->get();
            foreach ($biens as $bien) {
                $bien->setConnection('temp')->desistement_id = null;
                $bien->save();
            }

            $des->penalite_desistement?->delete();

            foreach ($des->remboursement as $item) {
                $item->delete();
            }

            foreach ($des->aquereurs_desisteurs as $item) {
                $item->delete();
            }

            foreach ($des->aquereurs_non_desisteurs as $item) {
                $item->delete();
            }

            foreach ($des->aquereurs_profits as $item) {
                $item->delete();
            }

            foreach ($des->aquereurs_partiel as $item) {
                $item->delete();
            }

            foreach ($des->nouvel_aquereurs_desistements as $item) {
                $item->delete();
            }

            foreach ($des->Piece_jointes as $item) {
                $item->delete();
            }

            $historiques = HistoriqueDesistement::on('temp')->where('desistement_id', $des->id)->get();
            foreach ($historiques as $hist) {
                $hist->delete();
            }

            $hist_biens = HistoriqueBien::on('temp')->where('desistement_id', $des->id)->get();
            foreach ($hist_biens as $hist) {
                $hist->delete();
            }

            $avances = Avance::on('temp')->where('desistement_id', $des->id)->get();
            foreach ($avances as $av) {
                $av->setConnection('temp')->desistement_id = null;
                $av->save();
            }

            $preRes = PreReservation::on('temp')->where('desistement_id', $des->id)->get();
            foreach ($preRes as $pr) {
                $pr->delete();
            }

            $des->delete();
        }

        // Suppression des autres entités simples
        foreach ($projet->fournisseurs as $item) {
            $item->delete();
        }

        foreach ($projet->decomptes as $item) {
            $item->delete();
        }

        foreach ($projet->factures as $item) {
            $item->delete();
        }

        foreach ($projet->cps as $item) {
            $item->delete();
        }

        foreach ($projet->credits as $item) {
            $item->delete();
        }

        foreach ($projet->objectifs as $item) {
            $item->delete();
        }

        foreach ($projet->reclamations as $item) {
            $item->delete();
        }

        foreach ($projet->remise_cles as $item) {
            $item->delete();
        }

        foreach ($projet->import as $item) {
            $item->delete();
        }

        foreach ($projet->echeance_projet as $item) {
            $item->delete();
        }

        // Suppression finale du projet
        if ($projet->delete()) {
            //broadcast(new NewProjectEvent($id));
            return response()->json(['message' => 'Projet supprimé avec succès'], 200);
        }

        return response()->json(['message' => "Projet n'a pas été supprimé"], 404);
    }

//il y a bcp des prb dans cette destroy
    /* public function destroy($id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            Config::set('broadcasting.default', 'pusher_2');

            $projet = Projet::on('temp')->findOrfail($id);
            //tranche
            if(count($projet->tranche)){
                $tr_d=neW TrancheController();
                foreach($projet->tranche as $tr){
                    $tr_d->destroy($tr->id);

                }
            }

            //bloc
            if(count($projet->bloc)){
                $bcl=neW BlocController();
                foreach($projet->bloc as $b){
                    $bcl->destroy($b->id);
                }
            }

            //immeuble
            if(count($projet->immeuble)){
                $imm=neW ImmeubleController();
                foreach($projet->immeuble as $i){
                    $imm->destroy($i->id);
                }
            }
            //biens
            if(count($projet->bien)){
                $bcl=neW BienController();
                foreach($projet->bien as $bi){
                    $bcl->destroy($bi->id);
                }
            }
            //typesBien
            if(count($projet->typesBien)>0){
                foreach($projet->typesBien as $tp_b){
                    $tp_b->destroy($tp_b->id);
                }
            }
            //typologies
            if(count($projet->typologies)>0){
                foreach($projet->typologies as $tp_l){
                    $tp_l->destroy($tp_l->id);
                }
            }
            //prospects

            if(count($projet->prospects)>0){
                foreach($projet->prospects as $pr){
                    $pr->destroy($pr->id);
                }
            }
            //userProjet

            $user_projets=UserProjet::on('temp')->where('projet_id',$id)->get();
            if(count($user_projets)>0){
                foreach($user_projets as $us){
                    $us->delete();
                }
            }
            //partenaires
            if(count($projet->partenaires)>0){
                foreach($projet->partenaires as $pr){
                    $pr->destroy($pr->id);
                }
            }
            //visites
            if(count($projet->visites)>0){
                $vis=new VisiteController();
                foreach($projet->visites as $v){
                    $vis->destroy($v->id);
                }
            }
            //reservations
            if(count($projet->reservations)>0){
                $res=new ReservationController();
                foreach($projet->reservations as $r){
                    $res->destroy($r->id);
                }
            }
            //appels
            if(count($projet->appels)>0){
                $app=new AppelController();
                foreach($projet->appels as $ap){
                    $app->destroy($ap->id);
                }
            }
            //clients
            if(count($projet->clients)>0){
                $cl=new ClientController();
                foreach($projet->clients as $ap){
                    $cl->destroy($ap->id);
                }
            }
            //notifi

             if(count($projet->notifications)>0){
                foreach($projet->notifications as $ap){
                    $ap->delete();
                }
            }
             //desistements
             $desistements=Desistement::on('temp')->where(function ($query)use ($id){
                $query->where('bien_id_ancien',$id)
                    ->orwhere('bien_id_new',$id);})
                ->get();
                if(count($desistements)>0){

                    //biens desistements_id
                    foreach($desistements as $des){
                         $biens=Bien::on('temp')->where('desistement_id',$des->id)->get();
                         if(count($biens)>0){
                            foreach($biens as $bi){
                                $bi->setConnection('temp');
                                $bi->desistement_id=null;
                                $bi->save();
                            }
                         }

                        if($des->penalite_desistement!=null){
                            $des->penalite_desistement->delete();
                        }
                        if(count($des->remboursement)>0){
                            foreach($des->remboursement as $remb){
                                $remb->delete();
                            }
                        }
                        if(count($des->aquereurs_desisteurs)>0){
                            foreach($des->aquereurs_desisteurs as $aq){
                                $aq->delete();
                            }
                        }
                        if(count($des->aquereurs_non_desisteurs)>0){
                            foreach($des->aquereurs_non_desisteurs as $aqn){
                                $aqn->delete();
                            }
                        }
                        if(count($des->aquereurs_profits)>0){
                            foreach($des->aquereurs_profits as $aqpr){
                                $aqpr->delete();
                            }
                        }
                        if(count($des->aquereurs_partiel)>0){
                            foreach($des->aquereurs_partiel as $aqp){
                                $aqp->delete();
                            }
                        }
                        if(count($des->nouvel_aquereurs_desistements)>0){
                            foreach($des->nouvel_aquereurs_desistements as $n_aq){
                                $naq->delete();
                            }
                        }
                        if(count($des->Piece_jointes)>0){
                            foreach($des->Piece_jointes as $pj_d){
                                $pj_d->delete();
                            }
                        }
                        $hdes=HistoriqueDesistement::on('temp')->where('desistement_id',$des->id)->get();
                        if(count($hdes)>0){
                           foreach($hdes as $hbi){
                               $hbi->delete();
                           }
                        }

                        $hbiens=HistoriqueBien::on('temp')->where('desistement_id',$des->id)->get();
                        if(count($hbiens)>0){
                           foreach($hbiens as $hbi){
                               $hbi->delete();
                           }
                        }

                        $avances=Avance::on('temp')->where('desistement_id',$des->id)->get();
                        if(count($avances)>0){
                           foreach($avances as $av){
                               $av->setConnection('temp');
                               $av->desistement_id=null;
                               $av->save();
                           }
                        }
                        $pre=PreReservation::on('temp')->where('desistement_id',$des->id)->get();
                        if(count($pre)>0){
                           foreach($pre as $hbi){
                               $hbi->delete();
                           }
                        }

                        $des->delete();
                    }

                }

            //fournisseur
            if(count($projet->fournisseurs)>0){
                foreach($projet->fournisseurs as $f){
                    $f->delete();
                }
            }
            //decomptes
            if(count($projet->decomptes)>0){
                foreach($projet->decomptes as $d){
                    $d->delete();
                }
            }
            //factures
            if(count($projet->factures)>0){
                foreach($projet->factures as $fc){
                    $fc->delete();
                }
            }
            //cps
            if(count($projet->cps)>0){
                foreach($projet->cps as $c){
                    $c->delete();
                }
            }
            //credits
            if(count($projet->credits)>0){
                foreach($projet->credits as $c){
                    $c->delete();
                }
            }
            //objectifs
            if(count($projet->objectifs)>0){
                foreach($projet->objectifs as $o){
                    $o->delete();
                }
            }
            //reclamations
            if(count($projet->reclamations)>0){
                foreach($projet->reclamations as $r){
                    $r->delete();
                }
            }
            //remise cles
            if(count($projet->remise_cles)>0){
                foreach($projet->remise_cles as $r){
                    $r->delete();
                }
            }
            //import
            if(count($projet->import)>0){
                foreach($projet->import as $i){
                    $i->delete();
                }
            }
            //echeance
            if(count($projet->echeance_projet)>0){
                foreach($projet->echeance_projet as $e){
                    $e->delete();
                }
            }
            if ($projet->delete()) {
                $projets = Projet::on('temp')->orderBy('created_at', 'desc')->get();
                broadcast(new NewProjectEvent($id));

                return response()->json(['message' => 'Projet supprimé avec succès'], 200);
            } else {
                return response()->json(['message' => "Projet n'a pas été supprimé"], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    } */

    public function restoreProjet($projet_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $projet = Projet::on('temp')->where('id', $projet_id)->withTrashed()->restore();
            return response()->json(['message' => 'Projet restauré avec succès'], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedProjets()
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $projet = Projet::on('temp')->onlyTrashed()->get();

            return response()->json(['message' => $projet], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

}
