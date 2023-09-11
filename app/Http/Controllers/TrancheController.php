<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreTrancheRequest;
use App\Http\Requests\UpdateTrancheRequest;
use App\Models\Tranche;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrancheController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index(Request $request)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $perPage = 20; // Number of items per page
            $page = $request->input('page', 1);

            $tranches = Tranche::on('temp')->orderBy('created_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            return response()->json(['tranches' => $tranches]);
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
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $tranche = new Tranche();
            $tranche->setConnection('temp');
            $tranche->nom = $request->nom;
            $tranche->projet_id = $request->projet_id;
            $tranche->date_lancement = $request->date_lancement;
            $tranche->date_livraison = $request->date_livraison;
            $tranche->niveau_etages = $request->niveau_etages;
            $tranche->nbre_blocs = $request->nbre_blocs ? $request->nbre_blocs : 0;
            $tranche->nbre_immeubles = $request->nbre_immeubles ? $request->nbre_immeubles : 0;
            $tranche->nbre_biens = $request->nbre_biens ? $request->nbre_biens : 0;
            $tranche->save();

            return response()->json(['tranche' => $tranche], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $tranche = Tranche::on('temp')->with('projet')->findOrfail($id);
            return response()->json(['tranche' => $tranche], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $tranche = Tranche::on('temp')->findOrfail($id);
            return response()->json(['message' => $tranche], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTrancheRequest $request, $id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $tranche = Tranche::on('temp')->findOrfail($id);
            $update = $request->all();
            foreach ($update as $key => $value) {
                $tranche->$key = $value;
            }
            $tranche->save();

            return response()->json(['message' => $tranche], 200);
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
            $tranche = Tranche::on('temp')->findOrfail($id);
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
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $tranche = Tranche::on('temp')->where('id', $tranche_id)->withTrashed()->restore();

            return response()->json(['message' => 'Tranche restored succesfully'], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedTranches()
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $tranches = Tranche::on('temp')::onlyTrashed()->get();
            return response()->json(['message' => $tranches], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function getTranchesByProjet($projet_id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $tranches = Tranche::on('temp')->where('projet_id', $projet_id)->get();

            return response()->json(['tranches' => $tranches], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function getTranchesByProjet_paginate(Request $request,$projet_id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', 5); // Get the number of items per page
            $page = $request->input('page', 1);
            $tranches = Tranche::on('temp')
            ->orderBy('created_at', 'desc')
            ->where('projet_id', $projet_id)
            ->paginate($perPage, ['*'], 'page', $page);

            return response()->json(['tranches' => $tranches], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

}
