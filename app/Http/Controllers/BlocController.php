<?php

namespace App\Http\Controllers;

use App\Models\Bloc;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBlocRequest;
use App\Http\Requests\UpdateBlocRequest;
use Illuminate\Support\Facades\Auth;



class BlocController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (Auth::guard('api')->check()) {
            $blocs = Bloc::all();
            return response()->json(['message' => $blocs]);
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
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            
            $bloc = new Bloc();
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
    public function show(Bloc $bloc)
    {
        if (Auth::guard('api')->check()) {
            return response()->json(['message' => $bloc], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Bloc $bloc)
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
             return response()->json(['message' => $bloc], 200);
        }else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBlocRequest $request, Bloc $bloc)
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            $bloc->update($request->all());
            return response()->json(['message' => $bloc], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Bloc $bloc)
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
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
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            Bloc::where('id', $bloc_id)->withTrashed()->restore();
            return response()->json(['message' => 'Bloc restored'], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedBlocs()
    {

        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            $blocs = Bloc::onlyTrashed()->get();
            return response()->json(['message' => $blocs], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}
