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
        //
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Projet $projet)
    {
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
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Projet $projet)
    {
        //
    }

    public function restoreProjet($projet_id)
    {
        if (Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1) {

            Projet::where('id', $projet_id)->withTrashed()->restore();

            return response()->json(['message' => 'Projet est bien restaurer'], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedProjets()
    {

        if (Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1) {
            $projets = Projet::onlyTrashed()->get();

            return response()->json(['message' => $projets], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}
