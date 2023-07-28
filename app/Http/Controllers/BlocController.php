<?php

namespace App\Http\Controllers;

use App\Models\Bloc;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBlocRequest;
use App\Http\Requests\UpdateBlocRequest;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;



class BlocController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $blocs = Bloc::on('temp')->get();
            return response()->json(['bloc' => $blocs]);
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
    public function store(StoreBlocRequest $request)
    {
        if (RoleHelper::Admin()) {
                       
            DatabaseHelper::Config();            
            $bloc = new Bloc();
            $bloc->setConnection('temp');
            $bloc->nom = $request->nom;
            $bloc->titre_foncier = $request->titre_foncier;
            $bloc->projet_id = $request->projet_id;
            $bloc->tranche_id = $request->tranche_id;
            $bloc->nbre_immeubles = $request->nbre_immeubles? $request->nbre_immeubles:0;
            $bloc->nbre_biens = $request->nbre_biens? $request->nbre_biens:0;
            $bloc->save();
            return response()->json(['message' => $bloc], 200);
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
            $bloc = Bloc::on('temp')->findOrfail($id);
            return response()->json(['bloc' => $bloc], 200);
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
            $bloc = Bloc::on('temp')->findOrfail($id);
             return response()->json(['message' => $bloc], 200);
        }else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBlocRequest $request, $id)
    {
        if (RoleHelper::Admin()) {
            DatabaseHelper::Config();
            $bloc = Bloc::on('temp')->findOrfail($id);
            $update = $request->all();
            foreach($update as $key => $value) {
                $bloc->$key = $value;
            }
            $bloc->save();

            return response()->json(['message' => $bloc], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if (RoleHelper::Admin()) {
            DatabaseHelper::Config();
            $bloc = Bloc::on('temp')->findOrfail($id);            
            if ($bloc->delete()) {
                return response()->json(['message' => 'bloc deleted succesfully'], 200);
            } else {
                return response()->json(['message' => 'bloc not deleted'], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }

    }
    public function restoreBloc($bloc_id)
    {
        if (RoleHelper::Admin()) {
            DatabaseHelper::Config();
            Bloc::on('temp')->where('id', $bloc_id)->withTrashed()->restore();
            return response()->json(['message' => 'Bloc restored'], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedBlocs()
    {

        if (RoleHelper::Admin()) {
            DatabaseHelper::Config();    
            $blocs = Bloc::on('temp')->onlyTrashed()->get();
            return response()->json(['message' => $blocs], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function getBlocsByProjet($projet_id){
        if (RoleHelper::Admin()) {
            DatabaseHelper::Config();
            $blocs = Bloc::on('temp')->where('projet_id', $projet_id)->get();
            return response()->json(['message' => $blocs], 200);
            
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }
    public function getBlocsByTranche($tranche_id){
        if (RoleHelper::Admin()) {
            DatabaseHelper::Config();
            $blocs = Bloc::on('temp')->where('tranche_id', $tranche_id)->get();
            return response()->json(['message' => $blocs], 200);
            
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }
}
