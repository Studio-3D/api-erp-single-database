<?php

namespace App\Http\Controllers;

use App\Enum\StatutReservationEnum;
use App\Enum\ModeFinancement;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreAvanceRequest;
use App\Http\Requests\UpdateAvanceRequest;
use App\Models\Avance;
use App\Models\FicheTransmission;
use App\Models\Encaissement;
use App\Models\User;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\NotificationHelper;
use Carbon\Carbon;
use App\Enum\RoleEnum;



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
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
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
    public function getAvances_by_Reservation(Request $request, $reservation_id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);
            $avances = Avance::on('temp')
                ->orderBy('created_at', 'desc')
                ->where('reservation_id', $reservation_id)
                ->paginate($perPage, ['*'], 'page', $page);
            return response()->json(['avances' => $avances], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
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
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $reservation = Reservation::on('temp')->findOrFail($request->reservation_id);
            $avance= new Avance();
            $avance->setConnection('temp');
            $last_num_recu = Avance::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")
            ->get('num_recu')->first();
            if ($last_num_recu!=null) {
                $n_recu = $last_num_recu->num_recu + 1;
                $avance->num_recu = '00' . $n_recu . '';
            } else {
                $avance->num_recu = '001';
            }
            if($request->montant==0){
                $avance->sr=false;
            }else{
                $avance->sr= (bool)$request->sr;
            }
            $avance->mode_paiement=$request->mode_paiement;
            //cheque cheque-banque cheque cetifice
            if($request->mode_paiement==2||$request->mode_paiement==3||$request->mode_paiement==4){
                $avance->numero_paiement=$request->numero_paiement;
                $avance->banque_id=$request->banque_id;
                $avance->echeance=$request->echeance;

            }
            //virement versement
            elseif($request->mode_paiement==5||$request->mode_paiement==6){
                $avance->numero_paiement=$request->numero_paiement;
                $avance->banque_id=$request->banque_id;
            }
            $avance->user_id=$userAuth->value('id');
            $avance->date_reglement=$request->date_reglement;
            $avance->commentaireAvance=$request->commentaireAvance;
            $avance->montant=$request->montant;
            $avance->montant_par_lettre=$request->montant_par_lettre;
            $avance->reservation_id=$request->reservation_id;
            if(RoleHelper::Com()){
                $avance->statut=StatutReservationEnum::EN_ATTENTE->value;
            }
            elseif(RoleHelper::AdminSup()){
                $avance->statut=StatutReservationEnum::VALIDER->value;
                $avance->user_id_valider=$userAuth->value('id');
                $avance->date_validation=Carbon::now();
                $avance->date_encaissement=$request->date_encaissement;
                $avance->num_remise = $request->num_remise;
            }


            if($avance->save()){
                //send notification d'echeance
                if ($avance->echeance != null) {
                    NotificationHelper::storeNotification(
                        '/reservations/show/'.$avance->reservation_id, $avance->echeance,5,'ECHEANCE',Auth::guard('api')->user()->id,null,null,null,$avance->reservation->projet_id,$avance->id,$request->reservation_id
                    );
                }
                //si commercial==> demande validation du paiement
                if (RoleHelper::Com()) {
                    NotificationHelper::storeNotification(
                        '/reservations/show/'.$avance->reservation_id, Carbon::now(),7,'Validation paiement',null,RoleEnum::ADMIN->value,null,null,$avance->reservation->projet_id,$avance->id,$request->reservation_id
                    );
                }
                //store avance to fiche transmission
                $fiche= new FicheTransmission();
                $fiche->setConnection('temp');
                //num recu cree aujourdhui
                $recu_now = FicheTransmission::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")->whereDate('created_at', Carbon::now())
                ->get('num_recu')->first();
                if ($recu_now!=null) {
                    $fiche->num_recu= $recu_now->num_recu;

                } else {
                    //num recu cree != aujourdhui
                    $rec_not_now= FicheTransmission::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")->whereDate('created_at','!=', Carbon::now())
                    ->get('num_recu')->first();
                    if($rec_not_now!=null){
                        $pp = $rec_not_now->num_recu + 1;
                        $fiche->num_recu = '00' . $pp . '';
                    }
                    else{
                        $fiche->num_recu = '001';
                    }

                }
                $fiche->avance_id=$avance->id;
                $fiche->user_id=$userAuth->value('id');
                $fiche->save();

                if(RoleHelper::AdminSup()){
                    //store encaissement
                    if($request->date_encaissement!=null){
                        $encaiss=new Encaissement();
                        $encaiss->setConnection('temp');
                        $encaiss->reservation_id=$request->reservation_id;
                        $encaiss->type_encaissement = $request->type_encaissement;//Avances
                        $encaiss->montant = $avance->montant;
                        $encaiss->avance_id = $avance->id;
                        $encaiss->date_reglement = $avance->created_at;
                        $encaiss->date_encaissement = $request->date_encaissement;
                        $encaiss->user_id_valider= $userAuth->value('id');
                        $encaiss->save();
                    }

                    //store commission a voir
                }

            }
            return $avance;

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
            if ($avance->delete()) {
                return response()->json(['message' => 'avance deleted succesfully'], 200);
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
            $avances=Avance::on('temp')->where('reservation_id',$reservation_id)->get();
            foreach($avances as $av){
                //fiche transmission
                $fich_transmission=FicheTransmission::on('temp')->where('avance_id',$av->id)->get();
                    foreach($fich_transmission as $f){
                        $f->delete();
                    }
                //supprimer avan
                $av->delete();
            }
            //encaissements
              $encaiss=Encaissement::on('temp')->where('reservation_id',$reservation_id)->get();
              foreach($encaiss as $en){
                $en->delete();
              }

            return response()->json(['message'=>'Avance deleted successfully'],200);

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
