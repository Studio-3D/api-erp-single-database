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
use App\Models\Notification;
use App\Http\Helpers\PaginationHelper;
use App\Models\HistoriqueAvance;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\NotificationHelper;
use Carbon\Carbon;
use App\Enum\RoleEnum;
use \NumberFormatter;
use App\Enum\ModePaiement;




class AvanceController  extends Controller
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
            $reservation=Reservation::on('temp')->select('prix','etat')->findorfail($reservation_id);
            if($reservation->etat==1){
                $avances = Avance::on('temp')
                ->withcount('historiques')
                ->orderBy('created_at', 'desc')
                ->where('reservation_id', $reservation_id)
                ->get();
                $sum_avances=0;
                foreach($avances as $av){
                    //tous les avances !=refuse
                    if($av->statut!=StatutReservationEnum::Refusé->value){
                        $sum_avances+=$av->montant;
                    }
                 }
            }else{
                //si dossier desiste
                $avances = Avance::on('temp')
                ->withcount('historiques')
                ->orderBy('created_at', 'desc')
                ->onlyTrashed()
                ->where('reservation_id', $reservation_id)
                ->get();
                $sum_avances=0;
                foreach($avances as $av){
                    //tous les avances !=refuse
                    if($av->statut!=StatutReservationEnum::Refusé->value){
                        $sum_avances+=$av->montant;
                    }
                 }
            }

            $data = PaginationHelper::paginate_array($avances->toArray(),$perPage,$page,$request->url());
            return response()->json(['avances' => $data,'sum_avances'=>$sum_avances,'prix'=>$reservation->prix,'etat_res'=>$reservation->etat], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function historiques_avance(Request $request,$id)
    {
        if(Auth::guard('api')->check()){
            DatabaseHelper::Config();
            $perPage=$request->input('pageSize',config('app.default_item_number_perpage'));
            $page=$request->input('page',1);
            $historiques= HistoriqueAvance::on('temp')->where('avance_id',$id)->with('user','banque')->orderby('created_at','desc')->paginate($perPage, ['*'], 'page', $page);;
            return response()->json(['historiques'=>$historiques],200);
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
            $avance->sr= (bool)$request->sr;
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
            if($request->montant_par_lettre!=null){
                $avance->montant_par_lettre=$request->montant_par_lettre;
            }else{
                $inWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
                $mnt_lettre = $inWords->format($request->montant);
                $avance->montant_par_lettre=$mnt_lettre;
            }

            $avance->reservation_id=$request->reservation_id;
            if($request->desistement_id!=null){
                $avance->desistement_id=$request->desistement_id;
                $avance->dossier_id_transfert=$request->dossier_id_transfert;
                $avance->statut=StatutReservationEnum::Validé->value;
                $avance->user_id_valider=$userAuth->value('id');
                $avance->date_validation=Carbon::now();
                $avance->date_encaissement=$request->date_encaissement;
                $avance->num_remise =ModePaiement::transfert_dossier->value;
               // $avance->mode_transfert = $request->mode_transfert;
            }
            else{
                if(RoleHelper::Com()){
                    $avance->statut=StatutReservationEnum::En_Attente->value;
                }
                elseif(RoleHelper::AdminSup()){
                    $avance->statut=StatutReservationEnum::Validé->value;
                    $avance->user_id_valider=$userAuth->value('id');
                    $avance->date_validation=Carbon::now();
                    $avance->date_encaissement=$request->date_encaissement;
                    $avance->num_remise = $request->num_remise;
                }
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
                if($request->mode_paiement==2||$request->mode_paiement==3||$request->mode_paiement==4){
                    $fiche->date = $request->echeance;
                }
                else{
                    $fiche->date = Carbon::now();
                }
                $fiche->save();

                if(RoleHelper::AdminSup()){
                    //store encaissement
                    if($request->date_encaissement!=null && $request->num_remise!=null){
                        $encaiss=new Encaissement();
                        $encaiss->setConnection('temp');
                        $encaiss->reservation_id=$request->reservation_id;
                        $encaiss->type_encaissement = 1;//Avances
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
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $avance=Avance::on('temp')->findOrFail($id);

            $statut=1;
            //store historique
            $histo= new HistoriqueAvance();
            $histo->setConnection('temp');
            $histo->avance_id = $id;
            $histo->num_recu = $avance->num_recu;
            $histo->sr = $avance->sr;
            $histo->mode_paiement = $avance->mode_paiement;
            $histo->numero_paiement=$avance->numero_paiement;
            $histo->banque_id=$avance->banque_id;
            $histo->echeance=$avance->echeance;
            $histo->user_id=$userAuth->value('id');
            $histo->date_reglement=$avance->date_reglement;
            $histo->commentaireAvance=$avance->commentaireAvance;
            $histo->montant=$avance->montant;
            $histo->montant_par_lettre=$avance->montant_par_lettre;
            $histo->statut=$avance->statut;
            $histo->user_id_valider=$userAuth->value('id');
            $histo->date_validation=Carbon::now();
            $histo->date_encaissement=$request->date_encaissement;
            $histo->num_remise = $request->num_remise;
            $histo->fichier = $request->fichier;

            if( $histo->save()){
                $avance->sr= (bool)$request->sr;
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
                    $avance->echeance=null;
                }
                else{//espece
                    $avance->numero_paiement=null;
                    $avance->banque_id=null;
                    $avance->echeance=null;
                }
                $avance->user_id=$userAuth->value('id');
                $avance->commentaireAvance=$request->commentaireAvance;
                $avance->montant=$request->montant;
                $inWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
                $mnt_lettre = $inWords->format($request->montant);
                $avance->montant_par_lettre=$mnt_lettre;

                if(RoleHelper::AdminSup()){
                    $avance->user_id_valider=$userAuth->value('id');
                    $avance->date_validation=Carbon::now();
                    $avance->date_encaissement=$request->date_encaissement;
                    $avance->num_remise = $request->num_remise;
                    //rejete et remodifier par admin
                    if($avance->statut==StatutReservationEnum::Refusé->value){
                        $avance->statut=StatutReservationEnum::Validé->value;
                    }
                }
                if($request->montant==0){
                    $avance->statut=StatutReservationEnum::Validé->value;
                }

                    $last_num_recu = Avance::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")
                    ->get('num_recu')->first();
                    if ($last_num_recu!=null) {
                        $n_recu = $last_num_recu->num_recu + 1;
                        $avance->num_recu = '00' . $n_recu . '';
                    } else {
                        $avance->num_recu = '001';
                    }

                // remodifier fiche transmission
                $fiche=FicheTransmission::on('temp')->where('avance_id',$avance->id)->orderby('created_at','desc')->firstOrFail();
                if($fiche!=null){
                    $fiche->setConnection('temp');
                    if($request->mode_paiement==2||$request->mode_paiement==3||$request->mode_paiement==4){
                        $fiche->date = $request->echeance;
                    }
                    else{
                        $fiche->date = Carbon::now();
                    }
                    $fiche->save();
                }


                if($avance->save()){
                        //delete old notificcation
                        $old_notif=Notification::on('temp')->where('avance_id',$avance->id)->get();
                        if(count($old_notif)>0){
                            foreach($old_notif as $nt){
                                $nt->delete();
                            }
                        }
                        //notif echeance
                        if ($avance->echeance != null) {
                            NotificationHelper::storeNotification(
                                '/reservations/show/'.$avance->reservation_id, $avance->echeance,5,'ECHEANCE',Auth::guard('api')->user()->id,null,null,null,$avance->reservation->projet_id,$avance->id,$avance->reservation_id
                            );
                        }
                        //si commercial==> demande validation du paiement
                        if (RoleHelper::Com()) {
                            NotificationHelper::storeNotification(
                                '/reservations/show/'.$avance->reservation_id, Carbon::now(),7,'Validation paiement',null,RoleEnum::ADMIN->value,null,null,$avance->reservation->projet_id,$avance->id,$avance->reservation_id
                            );
                        }
                        //Encaisseùment
                        if(RoleHelper::AdminSup()){
                            //store encaissement
                            if($request->date_encaissement!=null ||  $request->num_remise!=null){
                                $encaiss=Encaissement::on('temp')->where('avance_id',$id)->get();
                                foreach($encaiss as $en){
                                    $en->delete();
                                }
                            }}
                }
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
            $histo=HistoriqueAvance::on('temp')->where('avance_id',$id)->get();
            foreach($histo as $h){
                $h->forceDelete();
            }
            $fiche=FicheTransmission::on('temp')->where('avance_id',$id)->get();
            foreach($fiche as $f){
                $f->forceDelete();
            }
            $encaiss=Encaissement::on('temp')->where('avance_id',$id)->get();

            foreach($encaiss as $en){
                $en->forceDelete();
            }

            if ($avance->forceDelete()) {
                return response()->json(['message' => 'avance deleted succesfully'], 200);
            }
            else{
                return response()->json(['message'=>'avance non deleted']);
            }
        }
        return response()->json(['error'=>'Unauthorized'],401);
    }

    public function soft_destroy_avances_by_reservationId($reservation_id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::config();
            $avances=Avance::on('temp')->where('reservation_id',$reservation_id)->get();
            foreach ($avances as $avance){
                $histo=HistoriqueAvance::on('temp')->where('avance_id',$avance->id)->get();
                foreach($histo as $h){
                    $h->delete();
                }
                $fiche=FicheTransmission::on('temp')->where('avance_id',$avance->id)->get();
                foreach($fiche as $f){
                    $f->delete();
                }
                $encaiss=Encaissement::on('temp')->where('avance_id',$avance->id)->get();

                foreach($encaiss as $en){
                    $en->delete();
                }
                $avance->delete();

            }
            return response()->json(['message'=>'Avances supprimés avec succès'],200);

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
                $avance->statut=StatutReservationEnum::Validé->value;
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
                $avance->statut = StatutReservationEnum::Refusé->value;
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
