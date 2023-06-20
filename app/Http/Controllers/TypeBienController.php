<?php

namespace App\Http\Controllers;

use App\Models\TypeBien;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTypeBienRequest;
use App\Http\Requests\UpdateTypeBienRequest;
use Illuminate\Support\Facades\Auth;



class TypeBienController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            $typebiens = TypeBien::all();
            return response()->json(['message' => $typebiens]);
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
    public function store(StoreTypeBienRequest $request)
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            
           
            
            
            $typebien = new typebien();

            $typebien->type = $request['type'];
            
           $typebien->save();

            return response()->json(['message' => 'ce type de bien creer avec succes'], 200);
           
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(TypeBien $typeBien)
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            return response()->json(['message' => $typeBien], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TypeBien $typeBien)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTypeBienRequest $request, TypeBien $typeBien)
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
      
            $typeBien->update($request->all());
            
            return response()->json(['message' => 'typebien updated succesfully'], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TypeBien $typeBien)
    {
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            
            if ($typeBien->delete()) {
                return response()->json(['message' => 'ce type de bien deleted succesfully'], 200);
            } else {
                return response()->json(['message' => 'ce type de bien non deleted'], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function restoreTypeBien($typeBien_id)
    {
        if (Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1) {

            TypeBien::where('id', $typeBien_id)->withTrashed()->restore();

            return response()->json(['message' => 'Type Bien est bien restaurer'], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedTypesBien()
    {

        if (Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1) {
            $typeBiens = TypeBien::onlyTrashed()->get();

            return response()->json(['message' => $typeBiens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}
