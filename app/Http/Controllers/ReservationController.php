<?php

namespace App\Http\Controllers;

use App\Enum\EtatBien;
use App\Enum\StatutReservationEnum;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreAquereurRequest;
use App\Http\Requests\StoreAvanceRequest;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\UpdateReservationRequest;
use App\Models\Aquereur;
use App\Models\Avance;
use App\Models\PiecesJointe;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class ReservationController extends Controller
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
            $reservations=Reservation::on('temp')
                ->orderBy('created_at','desc')
                ->where('projet_id',$projet_id)
               ->paginate($perPage,['*'],'page',$page);
            return response()->json(['reservations'=>$reservations],200);
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
    public function store(StoreReservationRequest $request)
    {

        $user = Auth::user();
        if(RoleHelper::ACSup())
        {
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $reservation=new Reservation();
            $reservation->setConnection('temp');
            $reservation->nb_acquereurs=$request->nb_acquereurs;
            $reservation->code_reservation=$request->code_reservation;
            $reservation->prix=$request->prix;
            $reservation->mode_financement=$request->mode_financement;
            $reservation->date_reservation=$request->date_reservation;
            $reservation->date_limite_reservation=$request->date_limite_reservation;
            $reservation->commentaire=$request->commentaire;
            $reservation->visite_id=$request->visite_id;
            $bienController=new BienController();
            $bien=$bienController->getEtatBien($request->bien_id);
            if($bien==EtatBien::RESERVATION->name)
            {
                return response()->json(['message'=>'ce bien est deja reserver'],400);
            }
            else if($bien==EtatBien::PRE_RESERVATION->name)
            {
                return response()->json(['message'=>'ce bien est deja pre-reserver'],400);
            }
            else{
                $reservation->bien_id=$request->bien_id;
                $reservation->projet_id=$request->projet_id;
                $reservation->user_id=$userAuth->value('id');
                if(RoleHelper::AdminSup()){
                    $reservation->statut=StatutReservationEnum::VALIDER->value;
                }
                if(RoleHelper::Com()){
                    $reservation->statut=StatutReservationEnum::EN_ATTENTE->value;
                }
                if($reservation->save()){
                    $bienController=new BienController();
                    $bienController->reserverBien($reservation->bien_id);
                }
                $clientController = new ClientController();
                $clientRequest = new StoreClientRequest();
                $aquereurController=new AquereurController();
                $aquereurRequest = new StoreAquereurRequest();
                foreach ($request->clients as $clientInfo) {
                    $clientRequest->merge($clientInfo);
                    $clientData=$clientController->store($clientRequest);
                    $dataAquereur = [
                        'pourcentage'=>$clientInfo['pourcentage'],
                        'client_id'=> $clientData->id,
                        'reservation_id'=> $reservation->id
                    ];
                    $aquereurRequest->merge($dataAquereur);
                    $aquereurController->store($aquereurRequest);
                }
                $avanceController=new AvanceController();
                $avanceRequest = new StoreAvanceRequest();
                foreach ($request->avance as $avanceInfo){
                    $avanceRequest->merge($avanceInfo);
                }
                $avanceRequest->merge(['reservation_id'=>$reservation->id]);
                $avanceController->store($avanceRequest);

                return response()->json(['reservation' => $reservation], 200);
            }


        }
        return  response()->json(['error'=>'Unauthorized'],401);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $reservation=Reservation::on('temp')->findOrFail($id);
            return response()->json(['reservation'=>$reservation],200);
        }
        else{
            return response()->json(['error'=>'Unauthorized'],401);
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
    public function update(UpdateReservationRequest $request,$id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $reservation=Reservation::on('temp')->findOrFail($id);
            $update=$request->all();
            foreach ($update as $key => $value){
                $reservation->$key=$value;
            }
            $reservation->save();
            return response()->json(['reservation'=>$reservation],200);
        }
        return response()->json(['error','Unauthorized'],401);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $reservation=Reservation::on('temp')->findOrFail($id);
            $avanceController = new  AvanceController();
            $avanceController->destoryUsingReservationId($id);
            $aquereurController = new AquereurController();
            $aquereurController->destroyAquerreursByReservationId($id);
            $pjController =new PiecesJointeController();
            $pjController->destoryFileUsingReservationId($id);
            if($reservation->delete()){
                return response()->json(['message'=>'reservation supprimée avec succès.'],200);
            }
            else{
                return response()->json(['message'=>"reservation n'est supprimée."],400);
            }
        }
        return  response()->json(['error'=>'Unauthorized'],401);
    }



    public function getAllInformationsReservation($id){
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $reservation=Reservation::on('temp')->findOrFail($id);
            $avances=Avance::on('temp')->where('reservation_id',$id)->get();
            $aquereurs=Aquereur::on('temp')->where('reservation_id',$id)->get();
            $pj=PiecesJointe::on('temp')->where('reservation_id',$id)->get();
            $data=[
                'reservation'=>$reservation,
                'avances'=>$avances,
                'aquereurs'=>$aquereurs,
                'piecesjointes'=>$pj,
            ];

            return response()->json(['data'=> $data],200);
        }
        return response()->json(['error'=>'Unauthorized'],401);
    }
}
