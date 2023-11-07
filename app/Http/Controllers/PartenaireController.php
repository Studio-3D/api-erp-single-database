<?php

namespace App\Http\Controllers;

use App\Models\Partenaire;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePartenaireRequest;
use App\Http\Requests\UpdatePartenaireRequest;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use Illuminate\Http\Request;



class PartenaireController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function get_partenaires($projet_id)
    {

        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $partenaires = Partenaire::on('temp')->orderBy('created_at', 'desc')->where('projet_id',$projet_id)
                ->get();
            return response()->json(['partenaires' => $partenaires],200);
        }
        else{
            return response()->json(['error' => 'Unauthorized'], 401);
        }



    }

    public function index(Request $request,$projet_id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', 5); // Get the number of items per page
            $page = $request->input('page', 1);
            $partenaires = Partenaire::on('temp')->orderBy('created_at', 'desc')->where('projet_id',$projet_id)
            ->paginate($perPage, ['*'], 'page', $page);
            return response()->json(['partenaires' => $partenaires]);
        }

        return response()->json(['error' => 'Unauthorized'], 401);

    }

    /**
     * Show the form for creating a new resource.
     */


    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePartenaireRequest $request)
    {

        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            //partenaire unique in this projects
                $part_exist=Partenaire::on('temp')->where('projet_id',$request->projet_id)->where('description',$request->description)->count();
                if($part_exist>0){
                    return response()->json(['error_300' => 'le Partenaire que vous avez saisie existe déja dans ce projet'], 300);
               }
               else{
                $partenaire = new Partenaire();
                $partenaire->setConnection('temp');
                $partenaire->description = $request->description;
                $partenaire->remise = $request->remise;
                $partenaire->projet_id = $request->projet_id;
                $partenaire->save();
                return response()->json(['message' => $partenaire], 200);
               }


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
            $partenaire = Partenaire::on('temp')->findOrfail($id);

            return response()->json(['partenaire' => $partenaire], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePartenaireRequest $request,  $id)
    {
        //unique partenaire
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
             //partenaire unique in this projects
             $part_exist=Partenaire::on('temp')->where('projet_id',$request->projet_id)->where('description',$request->description)->where('id','!=',$id)->count();
             if($part_exist>0){
                 return response()->json(['error_300' => 'le Partenaire que vous avez saisie existe déja dans ce projet'], 300);
             }
             else{
                $partenaire = Partenaire::on('temp')->findOrfail($id);
                $update = $request->all();
                foreach($update as $key => $value) {
                    $partenaire->$key = $value;
                }
                $partenaire->save();

                return response()->json(['message' => $partenaire], 200);
             }

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
            $partenaire = Partenaire::on('temp')->findOrfail($id);

            if ($partenaire->delete()) {
                return response()->json(['message' => 'ce Partenaire est supprimé succesfully'], 200);
            } else {
                return response()->json(['message' => 'ce Partenaire non supprimé'], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function restorePartenaire($partenaire_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            Partenaire::on('temp')->where('id', $partenaire_id)->withTrashed()->restore();
            return response()->json(['message' => 'Partenaire bien restaurer'], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }


}
