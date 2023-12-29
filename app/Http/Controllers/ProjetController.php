<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Helpers\typeBienProjetHelper;
use App\Http\Helpers\UserProjetHelper;
use App\Http\Requests\StorePartenaireRequest;
use App\Http\Requests\StoreProjetRequest;
use App\Http\Requests\StoreTypeBienRequest;
use App\Http\Requests\StoreTypologieRequest;
use App\Http\Requests\StoreVueRequest;
use App\Http\Requests\UpdateProjetRequest;
use App\Models\Projet;
use App\Models\User;
use App\Models\UserProjet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Events\NewProjectEvent;
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
            $projets = Projet::on('temp')->orderBy('created_at', 'desc')->get();
            broadcast(new NewProjectEvent($projets));
            return response()->json(['projets' => $projets]);
        } else if (RoleHelper::Com()) {
            DatabaseHelper::Config();
            $id_auth=Auth::guard('api')->user()->id;
            $user_id=User::on('temp')->where('user_id_origin', $id_auth)->pluck('id');
            Config::set('broadcasting.default', 'pusher_2');
            $projets = Projet::on('temp')
            ->join('user_projets', 'user_projets.projet_id', '=', 'projets.id')
            ->where('user_projets.user_id',$user_id)
            ->select('projets.*')
            ->get();
            broadcast(new NewProjectEvent($projets));


            return response()->json(['projets'=>  $projets]);


        } else{
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function index(Request $request)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);
            $projets = Projet::on('temp')->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

            return response()->json(['projets' => $projets]);
        } else if (RoleHelper::Com()) {
            DatabaseHelper::Config();

            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);

            $id_auth=Auth::guard('api')->user()->id;
            $user_id=User::on('temp')->where('user_id_origin', $id_auth)->pluck('id');
            $projets = Projet::on('temp')
            ->orderBy('created_at', 'desc')
            ->join('user_projets', 'user_projets.projet_id', '=', 'projets.id')
            ->where('user_projets.user_id',$user_id)
            ->select('projets.*')
            ->orderBy('created_at', 'desc')
           ->paginate($perPage, ['*'], 'page', $page);
            return response()->json(['projets'=>  $projets]);

        } else{
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
    public function store(StoreProjetRequest $request)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
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
                if($projet->save()){
                    if($request->donneesTypeBien){
                        foreach ($request->donneesTypeBien as $typeBien) {
                            TypeBienController::AjouterTypeBien($typeBien, $projet->id);
                        }    
                    }
                    if($request->donneesVue){
                        foreach ($request->donneesVue as $vue) {
                            VueController::AjouterVue($vue, $projet->id);
                        }
                    }
                    if($request->donneesTypologie){
                        foreach ($request->donneesTypologie as $Typologie) {
                            TypologieController::AjouterTypologie($Typologie, $projet->id);
                        }
                    }
                    if($request->partenaires){
                        foreach ($request->partenaires as $Partenaire) {
                            PartenaireController::AjouterPartenaire($Partenaire, $projet->id);
                        }
                    }
                    //nombre de bien par type de bien
                    if ($request->selectedtypeBien){
                            foreach($request->selectedtypeBien as $valeur){
                                if($valeur[0])
                                    {typeBienProjetHelper::createTypeBienProjet((int)$valeur[0],$projet->id,(int)$valeur[1]);
                                }
                                else{
                                    return response()->json(['error' => 'Veuillez choisir le type de bien'], 422);//error not errors pour ne pas donner des prb dans le frontend
                                }
                        }
                    }    
                        $all=0;
                        foreach($request->selectedUsers as $valeur) {
                            if($valeur['id']=='tous') {
                                $all=1;
                                break;
                            }
                        }
                        if($all==1){
                                DatabaseHelper::Config();
                                $users = User::on('temp')->get(['id']);
                                foreach($users as $us){
                                    UserProjetHelper::createUserProjet($projet->id, $us->id);
                                }
                                return response()->json(['projet' => $projet], 200);
                        }

                        else{

                            foreach($request->selectedUsers as $valeur) {
                                UserProjetHelper::createUserProjet($projet->id, $valeur['id']);
                            }
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
            $projet = Projet::on('temp')->withCount(['bloc','tranche','immeuble','bien'])->findOrfail($id);
            $users=UserProjet::on('temp')->where('projet_id',$id)->get();
            return response()->json(['projet' => $projet,'users'=>$users], 200);
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
            if($projet->save()){
                $user_projets=UserProjet::on('temp')->where('projet_id',$id)->delete();
                $all=0;
                foreach($request->selectedUsers as $valeur) {
                    if($valeur=='tous') {
                        $all=1;
                        break;
                    }
                }
                if($all==1){
                        DatabaseHelper::Config();
                        $users = User::on('temp')->get(['id']);
                        foreach($users as $us){
                            UserProjetHelper::createUserProjet($projet->id, $us->id);
                        }
                        return response()->json(['projet' => $projet], 200);
                }
                else{
                    foreach($request->selectedUsers as $valeur) {
                        UserProjetHelper::createUserProjet($projet->id, $valeur);
                    }
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
            $projet = Projet::on('temp')->findOrfail($id);
            if ($projet->delete()) {
                Config::set('broadcasting.default', 'pusher_2');
                $projets = Projet::on('temp')->orderBy('created_at', 'desc')->get();
                broadcast(new NewProjectEvent($projets));

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