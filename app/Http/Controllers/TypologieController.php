<?php

namespace App\Http\Controllers;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreTypologieRequest;
use App\Models\Typologie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class TypologieController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (Auth::guard('api')->check())
        {
            DatabaseHelper::Config();
            $typologies = Typologie::on('temp')->get();
            return response()->json(['typlogies' => $typologies]);
        }
        else return response()->json(['error' => 'Unauthorized'], 401);

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
    public function store(StoreTypologieRequest $request)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $typologie=new Typologie();
            $typologie->setConnection('temp');
            $typologie->typologie=$request->typologie;
            $typologie->projet_id=Session::get('projet_id');
            $typologie->save();
            return  response()->json(['typologie'=>$typologie],200);
        }
        else return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $typologie = Typologie::on('temp')->findOrfail($id);
            return response()->json(['typologie' => $typologie], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $typologie=Typologie::on('temp')->findOrFail($id);
            $update=$request->all();
            foreach($update as $key => $value){
                $typologie->$key= $value;
            }
            $typologie->save();
            return response()->json(['typologie'=>$typologie],200);
        }
        else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if(RoleHelper::AdminSup()){
            DatabaseHelper::Config();
            $typologie=Typologie::on('temp')->findOrFail($id);
            if($typologie->delete())
            {
                return response()->json(['message'=>'Typologie supprimée avec succès.'],200);
            }
            else{
                return response()->json(['error'=>"La typologie n'a pas été supprimée."],404);
            }
        }
        else{
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}
