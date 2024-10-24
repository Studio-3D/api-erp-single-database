<?php

namespace App\Http\Controllers;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Models\Remboursement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use App\Models\User;
use App\Models\Bien;
use App\Models\Societe;
use Illuminate\Support\Facades\File;
use App\Enum\RoleEnum;
use App\Http\Helpers\NotificationHelper;
use Carbon\Carbon;
use App\Events\NotificationEvent;
use App\Models\Encaissement;
use App\Events\NotifMenuEvent;



class RemboursementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request,$projet_id,$action)    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);

            if(RoleHelper::AdminSup()){
                //demande de remboursement (apres vente)
               /* if($action==0){
                    $remboursements=Remboursement::on('temp')->join('desistements', 'desistements.id', '=', 'remboursements.desistement_id')
                    ->select('remboursements.*','desistements.id as des_id')
                    ->where('desistements.projet_id',$projet_id)->where('remboursements.statut',0)->where('remboursements.etat',1)
                    ->orderBy('remboursements.created_at','desc')
                    ->where(function ($query) use ($request) {
                        $query->where('remboursements.mode_rembourse', 'apres_vente')
                            ->orwhere('remboursements.mode_rembourse', 'transfert_rem_apres_vente')
                        ;})
                        ->where('desistements.deleted_at',NULL)
                    ->paginate($perPage, ['*'], 'page', $page);
                    //3= attente accuses du chéque
                }else*/if($action==3){
                    $remboursements=Remboursement::on('temp')->join('desistements', 'desistements.id', '=', 'remboursements.desistement_id')
                    ->select('remboursements.*','desistements.id as des_id')
                    ->where(function ($query) use ($request) {
                        $query->where('remboursements.statut', 1)
                            ->orwhere('remboursements.statut',0)
                    ;})
                    ->where('desistements.projet_id',$projet_id)->where('remboursements.etat',1)
                    ->where('remboursements.cheque_client_signe',NULL)
                    ->where('remboursements.user_id_remis',NULL)
                    ->orderBy('remboursements.created_at','desc')
                    ->where('desistements.deleted_at',NULL)
                    ->paginate($perPage, ['*'], 'page', $page);

                }
                // att decaissement
                elseif($action==1){
                    $remboursements=Remboursement::on('temp')->join('desistements', 'desistements.id', '=', 'remboursements.desistement_id')
                    ->select('remboursements.*','desistements.id as des_id')
                    ->where('desistements.projet_id',$projet_id)->where('remboursements.statut',2)->where('remboursements.etat',1)
                    ->where('remboursements.user_id_remis','!=',NULL)
                    ->orderBy('remboursements.created_at','desc')
                    ->where('desistements.deleted_at',NULL)
                    ->paginate($perPage, ['*'], 'page', $page);

                }
                //2=Liste des Accusé
                elseif($action==2){
                    $remboursements=Remboursement::on('temp')->join('desistements', 'desistements.id', '=', 'remboursements.desistement_id')
                    ->select('remboursements.*','desistements.id as des_id')->with('banque')
                    ->where('desistements.projet_id',$projet_id)->where('remboursements.statut',3)->where('remboursements.etat',1)
                    ->where('remboursements.date_decaissement','!=',NULL)
                    ->where('remboursements.banque_id','!=',NULL)
                    ->orderBy('remboursements.created_at','desc')
                    ->where('desistements.deleted_at',NULL)
                    ->paginate($perPage, ['*'], 'page', $page);

                }



            }elseif(RoleHelper::Com()){

                $user = Auth::user();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
              //demande de remboursement (apres vente)
              /* if($action==0){
                $remboursements=Remboursement::on('temp')->join('desistements', 'desistements.id', '=', 'remboursements.desistement_id')
                ->select('remboursements.*','desistements.id as des_id')
                ->where('desistements.projet_id',$projet_id)->where('remboursements.statut',0)->where('remboursements.etat',1)
                ->orderBy('remboursements.created_at','desc')
                ->where(function ($query) use ($request) {
                    $query->where('remboursements.mode_rembourse', 'apres_vente')
                        ->orwhere('remboursements.mode_rembourse', 'transfert_rem_apres_vente')
                    ;})
                    ->where('desistements.deleted_at',NULL)
                ->where('desistements.user_id', $userAuth->value('id'))
                ->paginate($perPage, ['*'], 'page', $page);
                   //3==>att accusé chesque par user_id
                }else*/if($action==3){
                    $remboursements=Remboursement::on('temp')->join('desistements', 'desistements.id', '=', 'remboursements.desistement_id')
                    ->select('remboursements.*','desistements.id as des_id')
                    ->where('desistements.projet_id',$projet_id)->where('remboursements.etat',1)
                    ->where('desistements.user_id', $userAuth->value('id'))
                    ->where(function ($query) use ($request) {
                        $query->where('remboursements.statut', 1)
                            ->orwhere('remboursements.statut',0)
                    ;})
                    ->where('remboursements.cheque_client_signe',NULL)
                    ->where('remboursements.user_id_remis',NULL)
                    ->orderBy('remboursements.created_at','desc')
                    ->where('desistements.deleted_at',NULL)
                    ->paginate($perPage, ['*'], 'page', $page);
                }
                //4==> accuses_cheque_traiter par user_id
                elseif($action==4){
                    $remboursements=Remboursement::on('temp')->join('desistements', 'desistements.id', '=', 'remboursements.desistement_id')
                    ->select('remboursements.*','desistements.id as des_id')
                    ->where('desistements.projet_id',$projet_id)->where('remboursements.statut',2)->where('remboursements.etat',1)
                    ->where('desistements.user_id', $userAuth->value('id'))
                    ->where('remboursements.user_id_remis',$userAuth->value('id'))
                    ->orderBy('remboursements.created_at','desc')
                    ->where('desistements.deleted_at',NULL)
                    ->paginate($perPage, ['*'], 'page', $page);
                }

            }
            return response()->json(['remboursements' => $remboursements]);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function traiter_demande_pre_rembourse($id,Request $request)
    {

        if(RoleHelper::ACSup()) {
            DatabaseHelper::Config();
           // Config::set('broadcasting.default', 'pusher_3');
           Config::set('broadcasting.default', 'pusher_5');
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
            $societe = Societe::findOrfail($user_societes->societe_id);

            $remboursement = Remboursement::on('temp')->findOrFail($id);
            //$remboursement->statut=1;
            $remboursement->statut=2;
            //added
            $remboursement->user_id_remis=$userAuth->value('id');;

            $remboursement->user_id_valider=$userAuth->value('id');;
            $remboursement->date_rembourse=$request->date_remboursement;
            $remboursement->mode_rembourse_client=$request->mode_rembourse_client;
            $remboursement->pour_le_compte=$request->pour_le_compte;
            $remboursement->num_paiement=$request->num_paiement;

            if ($request->hasFile('fichier_autorisation')) {
                $remboursement->fichier_autorisation =$request->file('fichier_autorisation')->getClientOriginalName();
                $directory = public_path('Docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/remboursements/fichiers_autorisations');
                File::makeDirectory($directory, 0755, true, true);
                $request->file('fichier_autorisation')->move($directory,$request->file('fichier_autorisation')->getClientOriginalName());
            }
            if ($request->hasFile('cheque_recu')) {
                $remboursement->cheque =$request->file('cheque_recu')->getClientOriginalName();
                $remboursement->cheque_client_signe =$request->file('cheque_recu')->getClientOriginalName();
                $directory = public_path('Docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/remboursements/cheques_reçus');
                File::makeDirectory($directory, 0755, true, true);
                $request->file('cheque_recu')->move($directory,$request->file('cheque_recu')->getClientOriginalName());
            }
            $remboursement->save();
            //4 demande pre rembourse
            broadcast(new NotifMenuEvent(4));
                /*if($remboursement->save()){
                    //store new notification validé
                    NotificationHelper::storeNotification(
                        '/remboursements/accuses_reception', Carbon::now(),20,'pré remboursement /le chèque de remboursement du Bien est prêt',Auth::guard('api')->user()->id,null,null,null,$reservation->projet_id,null,null
                        );
                        broadcast(new NotificationEvent($id));
                }*/

            return response()->json(['message' => 'le chèque de remboursement  est prêt.'], 200);



       } else {
           return response()->json(['error' => 'Unauthorized'], 401);
       }

    }

    public function traiter_accuse($id,Request $request)
    {
       if(RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            Config::set('broadcasting.default', 'pusher_3');
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
            $societe = Societe::findOrfail($user_societes->societe_id);

            $remboursement = Remboursement::on('temp')->findOrFail($id);
            $remboursement->statut=2;
           // $remboursement->remis_le=$request->remis_le;
            $remboursement->user_id_remis=$userAuth->value('id');
            if ($request->hasFile('cheque_client_signe')) {
                $remboursement->cheque_client_signe=$request->file('cheque_client_signe')->getClientOriginalName();
                $directory = public_path('Docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/remboursements/cheques_reçus');
                File::makeDirectory($directory, 0755, true, true);
                $request->file('cheque_client_signe')->move($directory,$request->file('cheque_client_signe')->getClientOriginalName());
            }
            if($remboursement->save()){
                if(RoleHelper::Com()){
                    //si commercial ==> envoi notif au admin que client a pris le cheque de remboursement
                    $data_notif = [
                        'lien' =>  '/remboursements/att_decaissement',
                        'date' => Carbon::now(),
                        'type' =>21,
                        'role'=>RoleEnum::ADMIN->value,
                        'description' => 'client a pris le chéque du remboursement',
                        'projet_id'=>$remboursement->reservation->projet_id,
                        'reservation_id'=>$remboursement->reservation_id

                    ];
                    $notif_helper = new NotificationHelper();
                    $notif_helper->storeNotification($request->merge($data_notif));
                        broadcast(new NotificationEvent($id));
                }
            }

            return response()->json(['message' => 'le Chèque du Remboursement est distribué au client.'], 200);



       } else {
           return response()->json(['error' => 'Unauthorized'], 401);
       }

    }
    public function traiter_decaissement($id,Request $request)
    {


       if(RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            Config::set('broadcasting.default', 'pusher_3');
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $remboursement = Remboursement::on('temp')->findOrFail($id);
            $bien= Bien::on('temp')->withSum('tva_collectes','tva_a_payer')->findorfail($remboursement->reservation->bien_id);
            $remboursement->statut=3;
            $remboursement->date_accuse=Carbon::now();
            $remboursement->date_decaissement=$request->date_decaissement;
            $remboursement->banque_id=$request->banque_id;

            if($remboursement->save()){
                $encaiss = new Encaissement();
                $encaiss->setConnection('temp');
                $encaiss->remboursement_id = $id;
                $encaiss->bien_id=$remboursement->reservation->bien_id;
                $encaiss->reservation_id = $remboursement->reservation_id;
                $encaiss->type_encaissement = 3; //Remboursements
                $encaiss->montant = $remboursement->montant_a_rembourser;
                $encaiss->date_reglement = Carbon::now();
                $encaiss->date_encaissement =$request->date_decaissement;
                $encaiss->user_id_valider = $userAuth->value('id');
                if($encaiss->save()){
                    if($bien->Bien_tva!=null){
                        $data=[
                            'montant'=>$remboursement->montant_a_rembourser,
                            'prix'=>$bien->prix,
                            'qp_terrain_valeur'=>$bien->Bien_tva->qp_terrain_valeur,
                            'ancien_tva_collectes'=>$bien->tva_collectes,
                            'tva_collectes_sum_tva_a_payer'=>$bien->tva_collectes_sum_tva_a_payer,
                            'tva_bien'=>$bien->Bien_tva->tva,
                            'reservation_id'=>$remboursement->reservation_id,
                            'bien_id'=>$bien->id,
                            'type'=>'remboursements',
                            'encaissement_id'=>$encaiss->id
                        ];
                        $tva_c=new AvanceController();
                        $tva_c->store_tva_collecte($request->merge($data));

                    }
                }

            }

            return response()->json(['message' => 'le Chèque du Remboursement est distribué au client.'], 200);

       } else {
           return response()->json(['error' => 'Unauthorized'], 401);
       }

    }

     public function get_detail_transfert(Request $request, $reservation_id)
     {
         if (RoleHelper::ACSup()) {
             DatabaseHelper::Config();
             $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
             $page = $request->input('page', 1);
             $data = Remboursement::on('temp')
                    ->with('dossier_transfert')
                 ->where('reservation_id', $reservation_id)
                 ->select('remboursements.*')->orderBy('created_at','desc')
                 ->paginate($perPage,['*'],'page',$page);
             return response()->json(['remboursement' => $data], 200);

         } else {
             return response()->json(['error' => 'Unauthorized'], 401);

         }
     }
     public function get_notif_demande_pre_remboursement($projet_id){
        DatabaseHelper::Config();
        if (RoleHelper::AdminSup()) {
            $nb_demande = Remboursement::on('temp')->join('desistements', 'desistements.id', '=', 'remboursements.desistement_id')
            ->where('desistements.projet_id',$projet_id)->where('remboursements.statut',0)->where('remboursements.etat',1)
            ->where(function ($query) {
                $query->where('remboursements.mode_rembourse', 'apres_vente')
                    ->orwhere('remboursements.mode_rembourse', 'transfert_rem_apres_vente')
                ;})->count();
                return response()->json(['nb'=>$nb_demande]);
        }elseif(RoleHelper::Com()){
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $nb_demande = Remboursement::on('temp')->join('desistements', 'desistements.id', '=', 'remboursements.desistement_id')
            ->where('desistements.projet_id',$projet_id)->where('remboursements.statut',0)->where('remboursements.etat',1)
            ->where('desistements.user_id', $userAuth->value('id'))
            ->where(function ($query) {
                $query->where('remboursements.mode_rembourse', 'apres_vente')
                    ->orwhere('remboursements.mode_rembourse', 'transfert_rem_apres_vente')
                ;})->count();
            return response()->json(['nb'=>$nb_demande]);

        } else  return response()->json(['error'=>'Unauthorized'], 401);
    }

    public function get_remboursements_dos_transfert($projet_id,Request $request){
        DatabaseHelper::Config();
        $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
        $page = $request->input('page', 1);

        if (RoleHelper::AdminSup()) {
            $dossiers= Remboursement::on('temp')->join('desistements', 'desistements.id', '=', 'remboursements.desistement_id')
            ->join('users', 'users.id', '=', 'desistements.user_id')
            ->join('reservations as dossier_transfert', 'dossier_transfert.id', '=', 'remboursements.dossier_id_transfert')
            ->select('remboursements.reservation_id','remboursements.created_at','remboursements.montant_transfert','desistements.user_id','users.name','users.prenom','dossier_transfert.code_reservation','dossier_transfert.id as id_transfer')
            ->where('desistements.projet_id',$projet_id)->where('remboursements.etat',1)
            ->where(function ($query) {
                $query->where('remboursements.mode_rembourse', 'transfert')
                    ->orwhere('remboursements.mode_rembourse', 'transfert_rem_apres_vente')
                    ->orwhere('remboursements.mode_rembourse', 'transfert_rem_direct')
                ;})->paginate($perPage, ['*'], 'page', $page);
                return response()->json(['dossiers'=>$dossiers]);
        }elseif(RoleHelper::Com()){
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();

            $dossiers= Remboursement::on('temp')->join('desistements', 'desistements.id', '=', 'remboursements.desistement_id')
                ->join('users', 'users.id', '=', 'desistements.user_id')
                ->join('reservations as dossier_transfert', 'dossier_transfert.id', '=', 'remboursements.dossier_id_transfert')
                ->select('remboursements.reservation_id','remboursements.created_at','remboursements.montant_transfert','desistements.user_id','users.name','users.prenom','dossier_transfert.code_reservation','dossier_transfert.id as id_transfer')
                ->where('desistements.projet_id',$projet_id) ->where('desistements.user_id', $userAuth->value('id'))->where('remboursements.etat',1)
                ->where(function ($query) {
                    $query->where('remboursements.mode_rembourse', 'transfert')
                        ->orwhere('remboursements.mode_rembourse', 'transfert_rem_apres_vente')
                        ->orwhere('remboursements.mode_rembourse', 'transfert_rem_direct')
                    ;})->paginate($perPage, ['*'], 'page', $page);
                    return response()->json(['dossiers'=>$dossiers]);



        } else  return response()->json(['error'=>'Unauthorized'], 401);
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
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
