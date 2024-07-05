<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\Projet;
use App\Models\UserProjet;
use Illuminate\Http\Request;
use App\Events\NewProjectEvent;
use App\Http\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\UserProjetHelper;
use Illuminate\Support\Facades\Config;
use App\Http\Requests\StoreProjetRequest;
use App\Http\Requests\UpdateProjetRequest;
use App\Models\Bien;
use App\Models\Immeuble;
use App\Models\Tranche;
use App\Models\Bloc;
use App\Models\TypeBien;
use App\Models\Vue;
use App\Models\Typologie;

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
            $projets = Projet::on('temp')->orderBy('created_at', 'asc')->get();
            // broadcast(new NewProjectEvent($projets->id));
            return response()->json(['projets' => $projets]);
        } else if (RoleHelper::Com()) {
            DatabaseHelper::Config();
            $id_auth = Auth::guard('api')->user()->id;
            $user_id = User::on('temp')->where('user_id_origin', $id_auth)->pluck('id');
            Config::set('broadcasting.default', 'pusher_2');
            $projets = Projet::on('temp')
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

    public function index(Request $request)
    {

        if (RoleHelper::AdminSup()) {
            $size = $request->input('size', config('app.default_item_number_perpage'));
            $page = $request->input('page', 1);
            DatabaseHelper::Config();
            // Démarrer la requête directement sur le modèle
            $query = Projet::on('temp');

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
                $query->whereHas('TypeProjet', function ($subQuery) use ($request) {
                    $subQuery->where('type', 'like', '%' . $request->input('type') . '%');
                });
            }

            $projets = $query->orderBy('created_at', 'desc')
                ->paginate($size, ['*'], 'page', $page);

            // Extraire les propriétés du paginateur
            $pagination = [
                'currentPage' => $projets->currentPage(),
                'totalItems' => $projets->total(),
                'totalPages' => $projets->lastPage(),
            ];

            // Extraire les éléments d'utilisateur du paginateur
            $projets = $projets->items();

            // Retourner la réponse simplifiée
            return response()->json([
                'projets' => $projets,
                'pagination' => $pagination,
            ], 200);
        } else if (RoleHelper::Com()) {
            $size = $request->input('size', config('app.default_item_number_perpage'));
            $page = $request->input('page', 1);
            DatabaseHelper::Config();

            $id_auth = Auth::guard('api')->user()->id;
            $user_id = User::on('temp')->where('user_id_origin', $id_auth)->pluck('id');
            $query = Projet::on('temp');

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

            $projets = $query->orderBy('created_at', 'desc')
                ->join('user_projets', 'user_projets.projet_id', '=', 'projets.id')
                ->where('user_projets.user_id', $user_id)
                ->select('projets.*')
                ->paginate($size, ['*'], 'page', $page);

            // Extraire les propriétés du paginateur
            $pagination = [
                'currentPage' => $projets->currentPage(),
                'totalItems' => $projets->total(),
                'totalPages' => $projets->lastPage(),
            ];

            // Extraire les éléments d'utilisateur du paginateur
            $projets = $projets->items();

            // Retourner la réponse simplifiée
            return response()->json([
                'projets' => $projets,
                'pagination' => $pagination,
            ], 200);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
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
            $projet->nom = $request->nom;
            $projet->code = $request->code;
            $projet->adresse = $request->adresse;
            $projet->date_autorisation_construction = $request->date_autorisation_construction;
            $projet->date_permis_habiter = $request->date_permis_habiter;
            $projet->titre_foncier = $request->titre_foncier;
            $projet->surface_terrain = $request->surface_terrain;
            $projet->prix_acquisition = $request->prix_acquisition;
            $projet->limite_annulation_reservation = $request->limite_annulation_reservation;
            $projet->type_id = $request->type_id;
            $projet->prolongation_reservation = $request->prolongation_reservation ?: 0;
            $projet->nbre_tranches = $request->nbre_tranches ?: 0;
            $projet->nbre_blocs = $request->nbre_blocs ?: 0;
            $projet->nbre_immeubles = $request->nbre_immeubles ?: 0;
            $projet->max_etages = $request->max_etages;
            $projet->nbre_biens = $request->nbre_biens ?: 0;
            if ($projet->save()) {
                $dataArray_donneesTypeBien = json_decode($request->input('donneesTypeBien', '[]'), true);
                $dataArray_donneesVue = json_decode($request->input('donneesVue', '[]'), true);
                $dataArray_donneesTypologie = json_decode($request->input('donneesTypologie', '[]'), true);
                $dataArray_partenaires = json_decode($request->input('partenaires', '[]'), true);
                $dataArray_users = json_decode($request->input('selectedUsers', '[]'), true);

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
                foreach ($dataArray_users as $valeur) {
                    if ($valeur['id'] == 'tous') {
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
                        UserProjetHelper::createUserProjet($projet->id, $valeur['id']);
                    }
                    broadcast(new NewProjectEvent($projet->id));

                    return response()->json(['projet' => $projet], 200);

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
            $projet = Projet::on('temp')->withCount(['bloc', 'tranche', 'immeuble', 'bien'])->findOrfail($id);
            $users = UserProjet::on('temp')->where('projet_id', $id)->get();
            return response()->json(['projet' => $projet, 'users' => $users], 200);
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

            $projet = Projet::on('temp')->findOrfail($id);
            $projet->nom = $request->nom;
            $projet->code = $request->code;
            $projet->adresse = $request->adresse;
            $projet->date_autorisation_construction = $request->date_autorisation_construction;
            $projet->date_permis_habiter = $request->date_permis_habiter;
            $projet->titre_foncier = $request->titre_foncier;
            $projet->surface_terrain = $request->surface_terrain;
            $projet->prix_acquisition = $request->prix_acquisition;
            $projet->limite_annulation_reservation = $request->limite_annulation_reservation;
            $projet->type_id = $request->type_id;
            $projet->prolongation_reservation = $request->prolongation_reservation ?: 0;
            $projet->nbre_tranches = $request->nbre_tranches ?: 0;
            $projet->nbre_blocs = $request->nbre_blocs ?: 0;
            $projet->nbre_immeubles = $request->nbre_immeubles ?: 0;
            $projet->nbre_biens = $request->nbre_biens ?: 0;
            $projet->max_etages = $request->max_etages;
            if ($projet->save()) {
                $user_projets = UserProjet::on('temp')->where('projet_id', $id)->delete();
                $all = 0;
                foreach ($request->selectedUsers as $valeur) {
                    if ($valeur == 'tous') {
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
                    foreach ($request->selectedUsers as $valeur) {
                        UserProjetHelper::createUserProjet($projet->id, $valeur['id']);
                    }
                    broadcast(new NewProjectEvent($id));

                    return response()->json(['projet' => $projet], 200);
                }
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            Config::set('broadcasting.default', 'pusher_2');

            $projet = Projet::on('temp')->findOrfail($id);
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
    }

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
