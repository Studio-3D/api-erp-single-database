<?php

namespace App\Http\Controllers;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreVueRequest;
use App\Http\Requests\UpdateVueRequest;
use App\Models\Vue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class VueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (Auth::guard('api')->check())
        {
            DatabaseHelper::Config();
            $vues = Vue::on('temp')->get();
            return response()->json(['vues' =>  $vues]);
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
    public function store(StoreVueRequest $request)
    {

        if(RoleHelper::AdminSup()){
            DatabaseHelper::Config();
            $vue=new Vue();
            $vue->setConnection('temp');
            $vue->vue=$request->vue;
            $vue->projet_id=Session::get('projet_id');
            $vue->save();
            return response()->json(['vue'=>$vue],200);
        }
        else  return response()->json(['error' => 'Unauthorized'], 401);

    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $vue = Vue::on('temp')->findOrfail($id);
            return response()->json(['vue' => $vue], 200);
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
    public function update(UpdateVueRequest $request,$id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $vue=Vue::on('temp')->findOrFail($id);
            $update=$request->all();
            foreach($update as $key => $value){
                $vue->$key= $value;
            }
            $vue->save();
            return response()->json(['vue'=>$vue],200);
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
            $vue=Vue::on('temp')->findOrFail($id);
            if($vue->delete())
            {
                return response()->json(['message'=>'Vue supprimée avec succès.'],200);
            }
            else{
                return response()->json(['error'=>"La vue n'a pas été supprimée."],404);
            }
        }
        else{
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}
