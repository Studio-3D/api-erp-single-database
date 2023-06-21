<?php

namespace App\Http\Controllers;

use App\Models\Immeuble;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImmeubleRequest;
use App\Http\Requests\UpdateImmeubleRequest;
use Illuminate\Support\Facades\Auth;



class ImmeubleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (Auth::guard('api')->check()) {
            $immeubles = Immeuble::all();
            return response()->json(['message' => $immeubles]);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreImmeubleRequest $request)
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            
            $immeuble = new immeuble();
            $immeuble->nom = $request->nom;
            $immeuble->titre_foncier = $request->titre_foncier;
            $immeuble->projet_id = $request->projet_id;
            $immeuble->tranche_id = $request->tranche_id;
            $immeuble->bloc_id = $request->bloc_id;
            $immeuble->nbre_biens = $request->nbre_biens? $request->nbre_biens:0;
            $immeuble->save();
            return response()->json(['message' => $immeuble], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Immeuble $immeuble)
    {
        if (Auth::guard('api')->check()) {
            return response()->json(['message' => $immeuble], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Immeuble $immeuble)
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            return response()->json(['message' => $immeuble], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateImmeubleRequest $request, Immeuble $immeuble)
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            $immeuble->update($request->all());
            return response()->json(['message' => $immeuble], 200);  
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Immeuble $immeuble)
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            
            if ($immeuble->delete()) {
                return response()->json(['message' => 'immeuble deleted succesfully'], 200);
            } else {
                return response()->json(['message' => 'immeuble not deleted'], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function restoreImmeuble($immeuble_id)
    {
        if (Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1) {
            Immeuble::where('id', $immeuble_id)->withTrashed()->restore();
            return response()->json(['message' => 'Immeuble restored'], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedImmeubles()
    {
        if (Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1) {
            $immeubles = Immeuble::onlyTrashed()->get();
            return response()->json(['message' => $immeubles], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}
