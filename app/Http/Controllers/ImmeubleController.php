<?php

namespace App\Http\Controllers;

use App\Models\Immeuble;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImmeubleRequest;
use App\Http\Requests\UpdateImmeubleRequest;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;



class ImmeubleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $immeubles = Immeuble::on('temp')->get();
            return response()->json(['immeuble' => $immeubles]);
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
        if (RoleHelper::Admin()) {
                       
            DatabaseHelper::Config();                
            $immeuble = new immeuble();
            $immeuble->setConnection('temp');
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
    public function show($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $immeuble = Immeuble::on('temp')->findOrfail($id);
            return response()->json(['immeuble' => $immeuble], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        if (RoleHelper::Admin()) {
            DatabaseHelper::Config();
            $immeuble = Immeuble::on('temp')->findOrfail($id);
            return response()->json(['message' => $immeuble], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateImmeubleRequest $request,$id)
    {
        if (RoleHelper::Admin()) {
            DatabaseHelper::Config();
            $immeuble = immeuble::on('temp')->findOrfail($id);
            $update = $request->all();
            foreach($update as $key => $value) {
                $immeuble->$key = $value;
            }
            $immeuble->save();
            return response()->json(['message' => $immeuble], 200);  
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy( $id)
    {
        if (RoleHelper::Admin()) {
            DatabaseHelper::Config();
            $immeuble = immeuble::on('temp')->findOrfail($id);             
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
        if (RoleHelper::Admin()) {
            DatabaseHelper::Config();
            Immeuble::on('temp')->where('id', $immeuble_id)->withTrashed()->restore();
            return response()->json(['message' => 'Immeuble restored'], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedImmeubles()
    {
        if (RoleHelper::Admin()) {
            DatabaseHelper::Config();
            $immeubles = Immeuble::on('temp')->onlyTrashed()->get();
            return response()->json(['message' => $immeubles], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function getImmeublesByProjet($projet_id){
        if (RoleHelper::Admin()) {
            DatabaseHelper::Config();
            $immeubles = Immeuble::on('temp')->where('projet_id', $projet_id)->get();
            return response()->json(['message' => $immeubles], 200);
            
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function getImmeublesByTranche($tranche_id){
        if (RoleHelper::Admin()) {
            DatabaseHelper::Config();
            $immeubles = Immeuble::on('temp')->where('tranche_id', $tranche_id)->get();
            return response()->json(['message' => $immeubles], 200);
            
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function getImmeublesByBloc($bloc_id){
        if (RoleHelper::Admin()) {
            DatabaseHelper::Config();
            $immeubles = Immeuble::on('temp')->where('bloc_id', $bloc_id)->get();
            return response()->json(['message' => $immeubles], 200);
            
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

}
