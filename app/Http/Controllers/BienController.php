<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBienRequest;
use App\Http\Requests\UpdateBienRequest;
use App\Models\Bien;
use App\Models\HistoriqueBien;
use Illuminate\Support\Facades\Auth;


class BienController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            $biens = Bien::all();
            return response()->json(['message' => $biens]);
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
    public function store(StoreBienRequest $request)
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            

            $bien = new bien();

            $bien->propriete_dite_bien = $request->propriete_dite_bien;
            $bien->numero = $request->numero;
            $bien->niveau = $request->niveau;
            $bien->orientation = $request->orientation;
            $bien->conventionne = $request->conventionne;
            $bien->prix_unitaire = $request->prix_unitaire;
            $bien->prix = $request->prix;
            $bien->superficie_architecte = $request->superficie_architecte;
            $bien->superficie_habitable = $request->superficie_habitable;
            $bien->nbre_facades = $request->nbre_facades;
            $bien->superficie_parking = $request->superficie_parking;
            $bien->superficie_box = $request->superficie_box;
            $bien->superficie_terrasse = $request->superficie_terrasse;
            $bien->superficie_jardin = $request->superficie_jardin;
            $bien->titre_foncier = $request->titre_foncier;
            $bien->etat = $request->etat;
            $bien->type_id = $request->type_id;
            $bien->projet_id = $request->projet_id;
            $bien->tranche_id = $request->tranche_id;
            $bien->bloc_id = $request->bloc_id;
            $bien->immeuble_id = $request->immeuble_id;

            $bien->save();

            return response()->json(['message' => $bien], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Bien $bien)
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            return response()->json(['message' => $bien], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Bien $bien)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBienRequest $request, Bien $bien)
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {

            $bien->update($request->all());

            return response()->json(['message' => $bien], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Bien $bien)
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {

            if ($bien->delete()) {
                return response()->json(['message' => 'bien deleted succesfully'], 200);
            } else {
                return response()->json(['message' => 'bien non deleted'], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }
    public function restoreBien($bien_id)
    {
        if (Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1) {

            Bien::where('id', $bien_id)->withTrashed()->restore();

            return response()->json(['message' => 'Bien est bien restaurer'], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedBiens()
    {

        if (Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1) {
            $biens = Bien::onlyTrashed()->get();

            return response()->json(['message' => $biens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function bloquerBien($bien_id)
    {  
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2 )) {
            $bien = Bien::findOrFail($bien_id);
            $bien->etat=4;
            $bien->save();

            $Historique_bien = new HistoriqueBien();
            $Historique_bien->action =4;
            $Historique_bien->description = "bloquer";
            $Historique_bien->user_id = Auth::user()->id;
            $Historique_bien->bien_id = $bien_id;
            $Historique_bien->save();

            return response()->json(['message' => $bien], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function reserverBien($bien_id)
    {  
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2 || Auth::guard('api')->user()->type == 3)) {
            $bien = Bien::findOrFail($bien_id);
            $bien->etat=3;
            $bien->save();

            $Historique_bien = new HistoriqueBien();
            $Historique_bien->action =3;
            $Historique_bien->description = "reserver";
            $Historique_bien->user_id = Auth::user()->id;
            $Historique_bien->bien_id = $bien_id;
            $Historique_bien->save();
            return response()->json(['message' => $bien], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function prereserverBien($bien_id)
    {  
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2 || Auth::guard('api')->user()->type == 3)) {
            $bien = Bien::findOrFail($bien_id);
            $bien->etat=2;
            $bien->save();

            $Historique_bien = new HistoriqueBien();
            $Historique_bien->action =2;
            $Historique_bien->description = "pre_reserver";
            $Historique_bien->user_id = Auth::user()->id;
            $Historique_bien->bien_id = $bien_id;
            $Historique_bien->save();
            return response()->json(['message' => $bien], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function libererBien($bien_id)
    {  
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2 || Auth::guard('api')->user()->type == 3)) {
            $bien = Bien::findOrFail($bien_id);
            $bien->etat=1;
            $bien->save();

            $Historique_bien = new HistoriqueBien();
            $Historique_bien->action =1;
            $Historique_bien->description = "liberer";
            $Historique_bien->user_id = Auth::user()->id;
            $Historique_bien->bien_id = $bien_id;
            $Historique_bien->save();
            return response()->json(['message' => $bien], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function getHistoriqueBien($bien_id)
    {  
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2 || Auth::guard('api')->user()->type == 3)) {
            $Historique_bien = HistoriqueBien::where('bien_id', $bien_id)->get();
            return response()->json(['message' => $Historique_bien], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function getBiensByProjet($projet_id){
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2 || Auth::guard('api')->user()->type == 3)) {
            $biens = Bien::where('projet_id', $projet_id)->get();
            return response()->json(['message' => $biens], 200);
            
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function getBiensByTranche($tranche_id){
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2 || Auth::guard('api')->user()->type == 3)) {
            $biens = Bien::where('tranche_id', $tranche_id)->get();
            return response()->json(['message' => $biens], 200);
            
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function getBiensByBloc($bloc_id){
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2 || Auth::guard('api')->user()->type == 3)) {
            $biens = Bien::where('bloc_id', $bloc_id)->get();
            return response()->json(['message' => $biens], 200);
            
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function getBiensByImmeuble($immeuble_id){
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2 || Auth::guard('api')->user()->type == 3)) {
            $biens = Bien::where('immeuble_id', $immeuble_id)->get();
            return response()->json(['message' => $biens], 200);
            
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }



}
