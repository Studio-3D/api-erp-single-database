<?php

namespace App\Http\Controllers;

use App\Models\CompositionBien;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompositionBienRequest;
use App\Http\Requests\UpdateCompositionBienRequest;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use Illuminate\Http\Request;


class CompositionBienController extends Controller
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

            $CompositionBiens = CompositionBien::on('temp')->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();
                return response()->json(['compositionBien' => $CompositionBiens]);
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
    public function store(StoreCompositionBienRequest $request)
    {
        if (RoleHelper::AdminSup()) {
                       
            DatabaseHelper::Config();               
            $composition_bien = new CompositionBien();
            $composition_bien->setConnection('temp');
            $composition_bien->bien_id = $request->bien_id;
            $composition_bien->nbre_chambres = $request->nbre_chambres;
            $composition_bien->nbre_salons = $request->nbre_salons;
            $composition_bien->nbre_sdb = $request->nbre_sdb;
            $composition_bien->nbre_cuisines = $request->nbre_cuisines;
            $composition_bien->nbre_halls = $request->nbre_halls;
            $composition_bien->nbre_terasses = $request->nbre_terasses;
            $composition_bien->nbre_balcons = $request->nbre_balcons;
            $composition_bien->nbre_buanderies = $request->nbre_buanderies;
            $composition_bien->nbre_placards = $request->nbre_placards;
            $composition_bien->nbre_receptions = $request->nbre_receptions;
            $composition_bien->save();

            return response()->json(['message' => $composition_bien], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show( $id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $compositionBien = compositionBien::on('temp')->findOrfail($id);            
            return response()->json(['compositionBien' => $compositionBien], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit( $id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $compositionBien = compositionBien::on('temp')->findOrfail($id);
            return response()->json(['message' => $compositionBien], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCompositionBienRequest $request,  $id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $compositionBien = compositionBien::on('temp')->findOrfail($id);
            $update = $request->all();
            foreach($update as $key => $value) {
                $compositionBien->$key = $value;
            }
            $compositionBien->save();
            return response()->json(['message' => $compositionBien], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy( $id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $compositionBien = compositionBien::on('temp')->findOrfail($id);             
            if ($compositionBien->delete()) {
                return response()->json(['message' => 'composition Bien deleted succesfully'], 200);
            } else {
                return response()->json(['message' => 'composition Bien non deleted'], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function restoreCompositionBien($compositionBien_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            CompositionBien::on('temp')->where('id', $compositionBien_id)->withTrashed()->restore();

            return response()->json(['message' => 'composition Bien est bien restaurer'], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedCompositionBiens()
    {

        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $compositionBiens = CompositionBien::on('temp')->onlyTrashed()->get();

            return response()->json(['message' => $compositionBiens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getComposition($bien_id)
    {  
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();            
            $CompositionBien = CompositionBien::on('temp')->where('bien_id', $bien_id)->get();
            return response()->json(['message' => $CompositionBien], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

}
