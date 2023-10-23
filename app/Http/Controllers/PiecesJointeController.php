<?php

namespace App\Http\Controllers;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreAquereurRequest;
use App\Http\Requests\StorePiecesJointeRequest;
use App\Http\Requests\UpdatePiecesJointeRequest;
use App\Models\PiecesJointe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use function PHPUnit\Framework\fileExists;

class PiecesJointeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request,$projet_id)
    {
        if(Auth::guard('api')->check()){
            DatabaseHelper::Config();
            $perPage =$request->input('pageSize',5);
            $page=$request->input('page',1);
            $pjs=PiecesJointe::on('temp')
                ->join('reservations','reservations.projet_id','=','aqeureurs.reservation_id')
                ->join('projets','reservations.projet_id','=','projets.id')
                ->where('projets.id',$projet_id)
                ->select('pieces_jointes.*')
                ->orderBy('created_at','desc')
                ->paginate($perPage,['*'],$page);
            return response()->json(['PJs'=> $pjs],200);
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
    public function store(StorePiecesJointeRequest $request)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $pJ = new PiecesJointe();
            $pJ->setConnection('temp');
            if ($request->hasFile('fichier')) {
                $file = time() . '.' . $request->file('fichier')->getClientOriginalName();
                $request->fichier->move(public_path('img/fichier'), $file);
                $pJ->fichier = $file;
                $pJ->type = $request->file('fichier')->getClientOriginalExtension();
            }
            $pJ->avance_id = $request->avance_id;
            $pJ->reservation_id = $request->reservation_id;
            if ($pJ->save()) {
                return response()->json(['PJ' => $pJ], 200);
            } else {
                return response()->json(['error' => 'Échec de la sauvegarde de la PJ'], 500);
            }
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $pJ=PiecesJointe::on('temp')->findOrFail($id);
            return response()->json(['pJ'=>$pJ],200);
        }
        return response()->json(['error','Unauthorized'],401);
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
    public function update(UpdatePiecesJointeRequest $request,$id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $pJ=PiecesJointe::on('temp')->findOrFail($id);

            if ($request->hasFile('fichier')) {
                $file=time() . '.' . $request->file('fichier')->getClientOriginalName();
                $request->file("fichier")->move(public_path('img/fichier'), $file);
                $pJ->fichier = $file;
                $pJ->type = $request->file('fichier')->getClientOriginalExtension();
                $pJ->avance_id = $request->input("avance_id");
                $pJ->reservation_id = $request->input("reservation_id");
            }

            if($pJ->save()) {
                return response()->json(["pJ" => $pJ], 200);
            }
            dd($request->input());
        }
        return  response()->json(['error' => 'Unauthorized'],401);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $pj=PiecesJointe::on('temp')->findOrFail($id);
            if($pj->delete()){
                return response()->json(['message'=>'PJ deleted successfully'],200);
            }
            else{
                return response()->json(['message'=>'PJ non deleted '],400);
            }
        }
        return response()->json(['error'=>'Unauthorized'],401);
    }

    public function getFileUsingReservationId($reservation_id){
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $pj=PiecesJointe::on('temp')->where('reservation_id',$reservation_id)->get();
            if($pj->isEmpty()){
                return response()->json(['message'=>'Aucune PJ dans cette reservation'],400);
            }
            else{
                return response()->json(['pJ'=>$pj],200);
            }
        }
        return response()->json(['error'=>'Unauthorized'],401);
    }
    public function destoryFileUsingReservationId($reservation_id){
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $pj=PiecesJointe::on('temp')->where('reservation_id',$reservation_id);
            if($pj->delete()){
                return response()->json(['message'=>'PJ deleted successfully'],200);
            }
            else{
                return response()->json(['message'=>'PJ non deleted '],400);
            }
        }
        return response()->json(['error'=>'Unauthorized'],401);
    }


}
