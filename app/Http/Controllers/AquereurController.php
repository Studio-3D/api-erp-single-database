<?php

namespace App\Http\Controllers;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreAquereurRequest;
use App\Http\Requests\UpdateAquereurRequest;
use App\Models\Aquereur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AquereurController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request,$projet_id)
    {
        if(Auth::guard('api')->check()){
            DatabaseHelper::Config();
            $perPage=$request->input('pageSizee',5);
            $page=$request->input('page',1);

            $aquereurs=Aquereur::on('temp')->join("reservations","aquereurs.reservation_id","=","reservations.id")
                ->join("projets","reservations.projet_id","=","projets.id")
                ->where("projets.id",$projet_id)
                ->select('aquereurs.*')
                ->orderBy('created_at','desc')
                ->paginate($perPage,['*'],'page',$page);

            return response()->json(['Aquereurs',$aquereurs],200);
        }
        return response()->json(['error' => 'Unauthorized'],401);
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
    public function store(StoreAquereurRequest $request)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $aquereur=new Aquereur();
            $aquereur->setConnection('temp');
            $aquereur->pourcentage=$request->pourcentage;
            $aquereur->client_id=$request->client_id;
            $aquereur->reservation_id=$request->reservation_id;
            if($aquereur->save()){
                return response()->json(['Aquérreur',$aquereur],200);
            }
        }
        return  response()->json(['error','Unauthorized'],401);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $aquereur=Aquereur::on('temp')->where('id',$id)->get();

            return response()->json(['Aquérreur'=>$aquereur],200);
        }
        return  response()->json(['error','Unauthorized'],401);
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
    public function update(UpdateAquereurRequest $request, $id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $aquereur=Aquereur::on('temp')->findOrFail($id);
            $update=$request->all();
            foreach ($update as $key => $value){
                $aquereur->$key = $value;
            }
            $aquereur->save();
            return response()->json(['Aquérreur'=>$aquereur],200);
        }
        return  response()->json(['error','Unauthorized'],401);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $aquereur=Aquereur::on('temp')->findOrFail($id);

            if($aquereur->delete()){
                return response()->json(['message'=>'Aquérreur supprimé avec succès'],200);
            }
            else{
                return response()->json(['message'=>'Aquérreur non supprimé'],400);
            }
        }
        return response()->json(['error'=>'Unauthorized'],401);
    }

    public function destroyAquerreursByReservationId($reservation_id){
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $aquereurs=Aquereur::on('temp')->where('reservation_id',$reservation_id);
            foreach ($aquereurs as $aquereur){
                if($aquereur->delete()){
                    return response()->json(['message'=>'Aquérreurs supprimés avec succès'],200);
                }
                else{
                    return response()->json(['message'=>'Aquérreurs non supprimés'],400);
                }
            }

        }
        return response()->json(['error'=>'Unauthorized'],401);
    }



    public function getAquerreursByReservationId($reservation_id){
        if(RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $aquereurs_reservation = Aquereur::on('temp')->where('reservation_id', $reservation_id)->get();
            if ($aquereurs_reservation->isEmpty()){
                return response()->json(['message'=>'aucun aquérreur existe dans cette réservation'],400);
            }
            else return response()->json(['aquérreurs'=>$aquereurs_reservation],200);
        }
        return response()->json(['error','Unauthorized'],401);
    }

    public function  nbAquerreursByReservation($reservation_id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $nbreaquerreurs=Aquereur::on('temp')->where('reservation_id',$reservation_id)->count();
            if($nbreaquerreurs!=0){
                return  response()->json(['nb_aquérreur'=>$nbreaquerreurs],200);
            }
        }
        return  response()->json(['error'=>'Unauthorized'],401);

    }
}
