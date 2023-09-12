<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Helpers\UserProjetHelper;
use App\Http\Requests\StoreProjetRequest;
use App\Http\Requests\UpdateProjetRequest;
use App\Models\Projet;
use App\Models\User;
use App\Models\UserProjet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function get_projets()
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $projets = Projet::on('temp')->orderBy('created_at', 'desc')
                ->get();
            return response()->json(['projets' => $projets]);
        } else if (RoleHelper::Com()) {
            DatabaseHelper::Config();
            $id_auth=Auth::guard('api')->user()->id;
            $user_id=User::on('temp')->where('user_id_origin', $id_auth)->pluck('id');
            $projets = Projet::on('temp')
            ->join('user_projets', 'user_projets.projet_id', '=', 'projets.id')
            ->where('user_projets.user_id',$user_id)
            ->select('projets.*')
            ->get();
            return response()->json(['projets'=>  $projets]);


        } else{
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function index(Request $request)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', 5); // Get the number of items per page
            $page = $request->input('page', 1);
            $projets = Projet::on('temp')->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

            return response()->json(['projets' => $projets]);
        } else if (RoleHelper::Com()) {
            DatabaseHelper::Config();

            $perPage = $request->input('pageSize', 5); // Get the number of items per page
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
            $projet->nbre_biens = $request->nbre_biens ?: 0;
            if($request->verification==true){
                    if($projet->save()){
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

            }
            else{
                return response()->json(['error' => 'Attention nombre de bien par type différent de nombre de bien total'], 422);//error not errors pour ne pas donner des prb dans le frontend

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
            $projet = Projet::on('temp')->findOrfail($id);
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
