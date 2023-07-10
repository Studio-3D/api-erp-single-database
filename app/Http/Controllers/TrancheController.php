<?php

namespace App\Http\Controllers;

use App\Models\Tranche;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrancheRequest;
use App\Http\Requests\UpdateTrancheRequest;
use Illuminate\Support\Facades\Auth;



class TrancheController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (Auth::guard('api')->check()) {
            $tranches = Tranche::all();
            return response()->json(['message' => $tranches]);
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
    public function store(StoreTrancheRequest $request)
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            $tranche = new Tranche();

            $tranche->nom = $request->nom;
            $tranche->projet_id = $request->projet_id;
            $tranche->date_lancement = $request->date_lancement;
            $tranche->date_livraison = $request->date_livraison;
            $tranche->niveau_etages = $request->niveau_etages;
            $tranche->nbre_blocs = $request->nbre_blocs? $request->nbre_blocs:0;
            $tranche->nbre_immeubles = $request->nbre_immeubles? $request->nbre_immeubles:0;
            $tranche->nbre_biens = $request->nbre_biens? $request->nbre_biens:0;
            $tranche->save();

            return response()->json(['message' => $tranche], 200);
           
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Tranche $tranche)
    {
        if (Auth::guard('api')->check()) {
            return response()->json(['message' => $tranche], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }       
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Tranche $tranche)
    {
        if (Auth::guard('api')->check()) {
            return response()->json(['message' => $tranche], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }  
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTrancheRequest $request, Tranche $tranche)
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
      
            $tranche->update($request->all());
            
            return response()->json(['message' => $tranche], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tranche $tranche)
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            
            if ($tranche->delete()) {
                return response()->json(['message' => 'tranche deleted succesfully'], 200);
            } else {
                return response()->json(['message' => 'tranche not deleted'], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }


    public function restoreTranche($tranche_id)
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            Tranche::where('id', $tranche_id)->withTrashed()->restore();
            return response()->json(['message' => 'Tranche restored succesfully'], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedTranches()
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            $tranches = Tranche::onlyTrashed()->get();
            return response()->json(['message' => $tranches], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function getTranchesByProjet($projet_id){
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2 || Auth::guard('api')->user()->type == 3)) {
            $tranches = Tranche::where('projet_id', $projet_id)->get();
            return response()->json(['message' => $tranches], 200);
            
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }
}
