<?php

namespace App\Http\Controllers;

use App\Enum\StatutReservationEnum;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreAvanceRequest;
use App\Http\Requests\UpdateAvanceRequest;
use App\Models\Avance;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AvanceController extends Controller
{
    /**
     * Display a listing of the resource.
     * get all avances in project
     */
    public function index(Request $request,$projet_id)
    {
        if(Auth::guard('api')->check()){
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', 5); // Get the number of items per page
            $page = $request->input('page', 1);
            $avances=Avance::on('temp')->join('reservations','avances.reservation_id','=','reservations.id')
                ->join('projets','reservations.projet_id','=','projets.id')
                ->where('projets.id',$projet_id)
                ->select('avances.*')->orderBy('created_at','desc')
                ->paginate($perPage,['*'],'page',$page);
            return response()->json(['avances'=>$avances],200);
        }
        return response()->json(['error'=>'Unauthorized'],401);
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
    public function store(StoreAvanceRequest $request)
    {

        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $reservation = Reservation::on('temp')->findOrFail($request->reservation_id);
            $avance= new Avance();
            $avance->setConnection('temp');
            $avance->sr= (bool)$request->sr;
            $avance->montant=$request->montant;
            $avance->mode_paiement=$request->mode_paiement;
            $avance->date_reglement=$request->date_reglement;
            $avance->echeance=$request->echeance;
            $avance->banque_id=$request->banque_id;
            $avance->reservation_id=$request->reservation_id;
            if(RoleHelper::Com()){
                $avance->statut=StatutReservationEnum::EN_ATTENTE->value;
            }
            elseif(RoleHelper::AdminSup()){
                $avance->statut=StatutReservationEnum::VALIDER->value;
            }
            if($avance->save()){
                $reservation->montant_encaisse +=$avance->montant;
                $reservation->save();
                return $avance;
            }

        }
        return  response()->json(['error'=>'Unauthorized'],201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $avance=Avance::on('temp')->findOrFail($id);
            return response()->json(['avance'=>$avance],200);
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
    public function update(UpdateAvanceRequest $request,$id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $avance=Avance::on('temp')->findOrFail($id);
            $reservation = Reservation::on('temp')->where('id',$avance->reservation_id)->get();
            $reservation->montant_encaisse-=$avance->montant;
            $update=$request->all();
            foreach ($update as $key => $value){
                $avance->$key=$value;
            }
            if( $avance->save()){
                $reservation->montant_encaisse+=$avance->montant;
                $reservation->save();
            }

            return response()->json(['avance'=>$avance],200);
        }
        return  response()->json(['error','Unauthorized'],401);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::config();
            $avance=Avance::on('temp')->findOrFail($id);
            $reservation = Reservation::on('temp')->where('id',$avance->reservation_id)->get();
            $reservation->montant_encaisse-=$avance->montant;
            if($avance->delete()){
                $reservation->save();
                return response()->json(['message'=>'Avance deleted successfully']);
            }
            else{
                return response()->json(['message'=>'avance non deleted']);
            }
        }
        return response()->json(['error'=>'Unauthorized'],401);
    }

    public function destoryUsingReservationId($reservation_id){
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $avances=Avance::on('temp')->where('reservation_id',$reservation_id);
            $reservation = Reservation::on('temp')->where('id',$avances->reservation_id)->get();
            $reservation->montant_encaisse-=$avances->montant;
            if($avances->delete()){
                $reservation->save();
                return response()->json(['message'=>'Avance deleted successfully'],200);
            }
            else{
                return response()->json(['message'=>'Avance non deleted '],400);
            }
        }
        return response()->json(['error'=>'Unauthorized'],401);
    }

    public function valideAvance($id){
        if(RoleHelper::AdminComptableSup()){
            DatabaseHelper::Config();
            $avance=Avance::on('temp')->findOrFail($id);
            if($avance->exists()){
                $avance->statut=StatutReservationEnum::VALIDER->value;
                if($avance->save())
                {
                    return response()->json(['message'=>'Advance has been validated'],200);
                }
                else{
                    return response()->json(['message'=>"Advance hasn't been validated."],400);
                }
            }
        }
        return  response()->json(['error'=>'Unauthorized'],401);
    }

    public function refuseAvance($id){

        if(RoleHelper::AdminComptableSup()) {
            DatabaseHelper::Config();
            $avance = Avance::on('temp')->findOrFail($id);
            if ($avance->exists) {
                $avance->statut = StatutReservationEnum::REFUSER->value;
                if ($avance->save()) {
                    return response()->json(['message' => 'The advance has been refused'], 200);
                } else {
                    return response()->json(['message' => "The advance hasn't been refused"], 400);
                }
            }
        }
        return response()->json(['error'=>'Unauthorized'],401);

    }
}
