<?php

namespace App\Http\Controllers;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreSourceRequest;
use App\Http\Requests\UpdateSourceRequest;
use App\Models\Source;
use Illuminate\Support\Facades\Auth;

class SourceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $sources=Source::on('temp')->get();
            return response()->json(['sources',$sources],200);
        }
       else  return response()->json(['error'=>'Unauthorized'], 401);
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
    public function store(StoreSourceRequest $request)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $source=new Source();
            $source->setConnection('temp');
            $source->source=$request->source;
            $source->save();
            return response()->json(['$source'=>$source],200);
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
            $source = Source::on('temp')->findOrfail($id);
            return response()->json(['source' => $source], 200);
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
    public function update(UpdateSourceRequest $request,$id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $source=Source::on('temp')->findOrFail($id);
            $update=$request->all();
            foreach($update as $key => $value){
                $source->$key= $value;
            }
            $source->save();
            return response()->json(['source'=>$source],200);
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
            $source=Source::on('temp')->findOrFail($id);
            if($source->delete())
            {
                return response()->json(['message'=>'Source supprimée avec succès.'],200);
            }
            else{
                return response()->json(['error'=>"La source n'a pas été supprimée."],404);
            }
        }
        else{
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}
