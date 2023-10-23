<?php

namespace App\Http\Controllers;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreProspectRequest;
use App\Http\Requests\UpdateProspectRequest;
use App\Models\Prospect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProspectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (Auth::guard('api')->check())
        {
            DatabaseHelper::Config();
            $perPage=$request->input('pageSize',5);
            $page=$request->input('page',1);
            $prospects = Prospect::on('temp')->orderBy('created_at','desc')->paginate($perPage,['*'],'page',$page);
            return response()->json(['prospects' =>  $prospects]);
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
    public function store(StoreProspectRequest $request)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $prospect= new Prospect();
            $prospect->setConnection("temp");
            $prospect->cin=$request->cin;
            $prospect->nom=$request->nom;
            $prospect->prenom=$request->prenom;
            $prospect->telephone=$request->telephone;
            $prospect->telephone_num2=$request->telephone_num2;
            $prospect->email=$request->email;
            $prospect->source=$request->source;
            $prospect->save();
            return $prospect;
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
            $prospect = Prospect::on('temp')->findOrfail($id);
            return response()->json(['prospect' => $prospect], 200);
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
    public function update(UpdateProspectRequest $request,$id)
    {
      if(RoleHelper::ACSup()){
          DatabaseHelper::Config();
          $prospect=Prospect::on('temp')->findOrFail($id);
          $update=$request->all();
          foreach($update as $key => $value){
              $prospect->$key= $value;
          }
          $prospect->save();
          return response()->json(['prospect'=>$prospect],200);
      }
       else {
           return response()->json(['error' => 'Unauthorized'], 401);
       }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if(RoleHelper::AdminSup()){
            DatabaseHelper::Config();
            $prospect=Prospect::on('temp')->findOrFail($id);
            if($prospect->delete())
            {
                return response()->json(['message'=>'Prospect supprimé avec succès.'],200);
            }
            else{
                return response()->json(['error'=>"Le prospect n'a pas été supprimé."],404);
            }
        }
        else{
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}
