<?php

namespace App\Http\Controllers;

use App\Models\Projet;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjetRequest;
use App\Http\Requests\UpdateProjetRequest;
use Illuminate\Support\Facades\Auth;



class ProjetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            $projets = Projet::all();
            return response()->json(['message' => $projets]);
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
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            if ($request['nbr_tranches'] == "") {
                $request['nbr_tranches'] = '0';
            }
            if ($request['nbr_blocs'] == "") {
                $request['nbr_blocs'] = '0';
            }
            if ($request['nbr_immeubles'] == "") {
                $request['nbr_immeubles'] = '0';
            }
            if ($request['nbr_biens'] == "") {
                $request['nbr_biens'] = '0';
            }

            
            $projet = new projet();

            $projet->nom = $request['nom'];
            $projet->code = $request['code'];
            $projet->adresse = $request['adresse'];
            $projet->date_autorisation_construction = $request['date_autorisation_construction'];
            $projet->date_permis_habiter = $request['date_permis_habiter'];
            $projet->titre_foncier = $request['titre_foncier'];
            $projet->surface_terrain = $request['surface_terrain'];
            $projet->prix_acquisition = $request['prix_acquisition'];
            $projet->limite_annulation_reservation = $request['limite_annulation_reservation'];
            $projet->nbr_tranches = $request['nbr_tranches'];
            $projet->nbr_blocs = $request['nbr_blocs'];
            $projet->nbr_immeubles = $request['nbr_immeubles'];
            $projet->nbr_biens = $request['nbr_biens'];
            $projet->societe_id = $request['societe_id'];
            $projet->save();

            return response()->json(['message' => 'Projet creer avec succes'], 200);
           
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Projet $projet)
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            return response()->json(['message' => $projet], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Projet $projet)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProjetRequest $request, Projet $projet)
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
      
            $projet->update($request->all());
            
            return response()->json(['message' => 'projet updated succesfully'], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
    
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Projet $projet)
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            
            if ($projet->delete()) {
                return response()->json(['message' => 'Projet deleted succesfully'], 200);
            } else {
                return response()->json(['message' => 'Projet non deleted'], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
        
    }

    public function restoreProjet($projet_id)
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {

            Projet::where('id', $projet_id)->withTrashed()->restore();

            return response()->json(['message' => 'Projet est bien restaurer'], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedProjets()
    {

        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            $projets = Projet::onlyTrashed()->get();

            return response()->json(['message' => $projets], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}
