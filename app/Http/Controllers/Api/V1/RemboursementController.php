<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Models\Remboursement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use App\Models\User;
use App\Models\Bien;
use App\Models\StatutClient;
use App\Models\Banque;

use App\Http\Controllers\Controller;
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

    public function indexByProjet(Request $request,$projet_id,$action)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', null);
            $page = $request->input('page', null);
            DatabaseHelper::Config();

        // Exclude transfert_rem_direct AND transfert_rem_apres_vente with montant_a_rembourser = 0
            $query = Remboursement::on('temp')->with('desistement_not_trashed','aquereur','banque')
                    ->where('archive',0)
                    ->where('etat', 1)
                    ->where('mode_rembourse','!=','transfert')
                ->where(function($q) {
                        $q->whereNotIn('mode_rembourse', ['transfert_rem_direct', 'transfert_rem_apres_vente'])
                        ->orWhere('montant_a_rembourser', '>', 0);
                    });

                $query->whereHas('desistement_not_trashed', function ($q) use ($projet_id) {
                    $q->where('projet_id', $projet_id);
                });


           if (RoleHelper::AdminSup()) {
                switch ($action) {
                    case 0: // Demande de pré-remboursement
                        $query->where('remboursements.statut', 0)
                            ->whereNull('cheque_client_signe')
                            ->whereNull('user_id_remis');
                         break;

                    case 1: // Remboursements remis
                        $query->whereNotNull('user_id_remis')
                            ->where('statut', 2);
                        break;

                    case 2: // Liste des accusés
                        $query->where('statut', 3)
                            ->whereNotNull('date_decaissement')
                            ->whereNotNull('banque_id');
                        break;

                    case 3: // Attente accusés du chèque
                        $query->where('remboursements.statut', 1)
                            ->whereNull('cheque_client_signe')
                            ->whereNull('user_id_remis');
                        break;
                }
            } elseif (RoleHelper::Com()) {
                $user = Auth::user();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();

                $query->whereHas('desistement_not_trashed', fn($q) => $q->where('user_id', $userAuth->id));

                switch ($action) {
                    case 0: // Demande de pré-remboursement
                        $query->where('statut', 0)
                            ->whereNull('cheque_client_signe')
                            ->whereNull('user_id_remis');
                        break;

                    case 3: // Attente accusés du chèque
                        $query->where('statut', 1)
                            ->whereNull('cheque_client_signe')
                            ->whereNull('user_id_remis');
                        break;

                    case 4: // Accusés chèque traités
                        $query->where('statut', 2)
                            ->where('user_id_remis', $userAuth->id);
                        break;
                }
            }
            if ($request->filled('bien')) {
                $query->whereHas('desistement_not_trashed.bien_ancien', function ($q) use ($request) {
                    $q->where('propriete_dite_bien','like',$request->input('bien'));
                });
            }

            if ($request->filled('responsable')) {
                $query->whereHas('desistement_not_trashed.user', function ($q) use ($request) {
                    $q->where('name','like', '%' . $request->input('responsable').'%' )
                    ->orWhere('prenom','like', '%' .$request->input('responsable').'%');
                });
            }
            if ($request->filled('client')) {
                $query->whereHas('aquereur.client', function ($q) use ($request) {
                    $q->where('nom', 'like', '%' .$request->input('client').'%' )
                    ->orWhere('prenom', 'like', '%' . $request->input('client').'%');
                });
            }
            if ($request->filled('montant_a_rembourser')) {
                $query->where('montant_a_rembourser', $request->input('montant_a_rembourser'));
            }


            if ($request->filled('type_remb')) {
                $mode_remb=null;
                switch($request->input('type_remb')){
                    case 1:
                        $mode_remb='direct';
                        break;
                    case 2:
                        $mode_remb='apres_vente';
                        break;
                    case 3:
                        $mode_remb='transfert';
                        break;
                    case 4:
                        $mode_remb='transfert_rem_apres_vente';
                        break;
                    case 5:
                        $mode_remb='transfert_rem_direct';
                        break;
                }

                $query->where('mode_rembourse', $mode_remb);
            }

            if ($request->filled('date_remb')) {
                $start = Carbon::parse($request->input('date_remb'));
                $query->whereDate('date_rembourse', $start);
            }
            if ($request->filled('num_paiement')) {
                $query->where('num_paiement', 'like', '%' . $request->input('num_paiement').'%');
            }
            if ($request->filled('pour_le_compte')) {
                $query->where('pour_le_compte', 'like', '%' . $request->input('pour_le_compte').'%');
            }
            if ($request->filled('date_decaissement')) {
                $start = Carbon::parse($request->input('date_decaissement'));
                $query->whereDate('date_decaissement', $start);
            }

            if ($request->filled('date_accuse')) {
                $start = Carbon::parse($request->input('date_accuse'));
                $query->whereDate('date_accuse', $start);
            }
            if ($request->filled('banque')) {
                $query->whereHas('banque', function ($q) use ($request) {
                    $q->where('nom', 'like', '%' . $request->input('banque').'%');
                });
            }

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                $remb = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Extraire les propriétés du paginateur
                $pagination = [
                    'currentPage' => $remb->currentPage(),
                    'totalItems' => $remb->total(),
                    'totalPages' => $remb->lastPage(),
                ];

                // Extraire les éléments d'utilisateur du paginateur
                $remb = $remb->items();

                // Retourner la réponse simplifiée
                return response()->json([
                    'data' => $remb,
                    'pagination' => $pagination,
                ], 200);
            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
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
            $codeReservation = $remboursement->reservation->code_reservation;

            if ($request->hasFile('fichier_autorisation')) {
                $remboursement->fichier_autorisation =$request->file('fichier_autorisation')->getClientOriginalName();
                $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/remboursements/fichiers_autorisations/' .$codeReservation);
                File::makeDirectory($directory, 0755, true, true);
                $request->file('fichier_autorisation')->move($directory,$request->file('fichier_autorisation')->getClientOriginalName());
            }
            if ($request->hasFile('cheque_recu')) {
                $remboursement->cheque =$request->file('cheque_recu')->getClientOriginalName();
                $remboursement->cheque_client_signe =$request->file('cheque_recu')->getClientOriginalName();
                $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/remboursements/cheques_reçus/'.$codeReservation);
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
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();
            $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
            $societe = Societe::findOrfail($user_societes->societe_id);

            $remboursement = Remboursement::on('temp')->findOrFail($id);
            $remboursement->statut=2;
            $codeReservation = $remboursement->reservation->code_reservation;

           // $remboursement->remis_le=$request->remis_le;
            $remboursement->user_id_remis=$userAuth->value('id');
            if ($request->hasFile('cheque_client_signe')) {
                $remboursement->cheque_client_signe=$request->file('cheque_client_signe')->getClientOriginalName();
                $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/remboursements/cheques_reçus/' .$codeReservation);
                File::makeDirectory($directory, 0755, true, true);
                $request->file('cheque_client_signe')->move($directory,$request->file('cheque_client_signe')->getClientOriginalName());
            }
            if($remboursement->save()){
                if(RoleHelper::Com()){
                    //si commercial ==> envoi notif au admin que client a pris le cheque de remboursement
                    $data_notif = [
                        'lien' =>  '/ventes/remboursements/att_decaissement',
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
                 //new Statut Client
                    $statutClient = new StatutClient();
                    $statutClient->setConnection('temp');
                    $statutClient->client_id = $remboursement->aquereur->client_id;
                    $statutClient->statut = 16;//Remis du Rembourse a signe le cheque
                    $statutClient->remboursement_id = $remboursement->id;
                    $statutClient->desistement_id = $remboursement->desistement_id;
                    $statutClient->reservation_id = $remboursement->reservation_id;
                    $statutClient->date_traitement = Carbon::now();
                    $statutClient->user_id_traite = $userAuth->id;

                    // Build comment

                        // Construction du commentaire pour StatutClient
                        $comment = 'Le client a reçu le chèque de remboursement ';

                        // Ajouter les détails du remboursement
                        $comment .= ' - Montant: ' . number_format($remboursement->montant_a_rembourser, 0, ',', ' ') . ' ' . ('MAD');

                        // Ajouter la référence du remboursement
                        if ($remboursement->num_paiement) {
                            $comment .= ' - Réf: ' . $remboursement->num_paiement;
                        }

                       // Ajouter le mode de remboursement avec libellé explicite
                        $modePaiementLabels = [
                            1 => 'Espèce',
                            2 => 'Chèque',
                            3 => 'Chèque banque',
                            4 => 'Chèque certifié',
                            5 => 'Virement',
                            6 => 'Versement',
                            7 => 'Transfert dossier'
                        ];

                        if ($remboursement->mode_paiement && isset($modePaiementLabels[$remboursement->mode_paiement])) {
                            $comment .= ' - Mode de remboursement: ' . $modePaiementLabels[$remboursement->mode_paiement];
                        } elseif ($remboursement->mode_paiement) {
                            $comment .= ' - Mode de remboursement: ' . $remboursement->mode_paiement;
                        }


                        // Ajouter la référence de la réservation
                        $comment .= ' - Réservation: ' . $codeReservation;

                        // Ajouter le projet concerné
                        if ($remboursement->reservation && $remboursement->reservation->projet) {
                            $comment .= ' - Projet: ' . $remboursement->reservation->projet->nom;
                        }
                         // Ajouter la date de remise
                        if ($request->remis_le) {
                            $comment .= ' - Date remise: ' . Carbon::parse($request->remis_le)->format('d/m/Y');
                        }

                        // Ajouter le nom de l'agent qui a remis le chèque
                        if ($userAuth && $userAuth->value('name')) {
                            $comment .= ' - Commercial: ' . $userAuth->value('name').' '.$userAuth->value('prenom');
                        }

                        // Si le chèque signé a été uploadé
                        if ($request->hasFile('cheque_client_signe')) {
                            $comment .= ' - Chèque signé reçu';
                        }

                        $statutClient->commentaire = $comment;
                        $statutClient->save();

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
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();
            if (!$userAuth) {
                return response()->json(['error' => 'Utilisateur non trouvé'], 404);
            }
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
                $encaiss->user_id_valider = $userAuth->id;
                if($encaiss->save()){
                    if($bien->Bien_Tva!=null){
                        $data=[
                            'montant'=>$remboursement->montant_a_rembourser,
                            'prix'=>$bien->prix,
                            'qp_terrain_valeur'=>$bien->Bien_Tva->qp_terrain_valeur,
                            'ancien_tva_collectes'=>$bien->tva_collectes,
                            'tva_collectes_sum_tva_a_payer'=>$bien->tva_collectes_sum_tva_a_payer,
                            'tva_bien'=>$bien->Bien_Tva->tva,
                            'reservation_id'=>$remboursement->reservation_id,
                            'bien_id'=>$bien->id,
                            'type'=>'remboursements',
                            'encaissement_id'=>$encaiss->id
                        ];
                        $tva_c=new AvanceController();
                        $tva_c->store_tva_collecte($request->merge($data));

                    }
                }

                 // AJOUT DU STATUT CLIENT
                $statutClient = new StatutClient();
                $statutClient->setConnection('temp');

                // Récupération des informations nécessaires
                $codeReservation = $remboursement->reservation->code_reservation;
                $nomProjet = $remboursement->reservation->projet->nom ?? 'N/A';
                $banque = Banque::on('temp')->find($request->banque_id);

                // Libellés des modes de paiement
                $modePaiementLabels = [
                    1 => 'Espèce',
                    2 => 'Chèque',
                    3 => 'Chèque banque',
                    4 => 'Chèque certifié',
                    5 => 'Virement',
                    6 => 'Versement',
                    7 => 'Transfert dossier'
                ];

                // Construction du commentaire
                $comment = 'Décaissement effectué pour le remboursement ';
                $comment .= ' - Montant: ' . number_format($remboursement->montant_a_rembourser, 0, ',', ' ') . ' MAD';

                if ($remboursement->num_paiement) {
                    $comment .= ' - Référence: ' . $remboursement->num_paiement;
                }

                if ($remboursement->mode_paiement && isset($modePaiementLabels[$remboursement->mode_paiement])) {
                    $comment .= ' - Mode: ' . $modePaiementLabels[$remboursement->mode_paiement];
                }

                if ($banque) {
                    $comment .= ' - Banque: ' . $banque->nom;
                }

                $comment .= ' - Date décaissement: ' . Carbon::parse($request->date_decaissement)->format('d/m/Y');
                $comment .= ' - Réservation: ' . $codeReservation;
                $comment .= ' - Projet: ' . $nomProjet;

                if ($userAuth->name) {
                    $comment .= ' - Traité par: ' . $userAuth->name . ' ' . ($userAuth->prenom ?? '');
                }

                // Attribution des valeurs au StatutClient
                $statutClient->client_id = $remboursement->aquereur->client_id;
                $statutClient->statut = 17; // Statut pour "Décaissement effectué"
                $statutClient->remboursement_id = $remboursement->id;
                $statutClient->desistement_id = $remboursement->desistement_id;
                $statutClient->reservation_id = $remboursement->reservation_id;
                $statutClient->date_traitement = Carbon::now();
                $statutClient->user_id_traite = $userAuth->id;
                $statutClient->commentaire = $comment;

                $statutClient->save();
            }


         return response()->json(['message' => 'Le décaissement du remboursement a été effectué avec succès.'], 200);
    } else {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
}



     public function get_detail_transfert($reservation_id, Request $request)
     {
         if (Auth::guard('api')->check()) {
             $size = $request->input('size', config('app.default_item_number_perpage')); // Default size if not provided
             $page = $request->input('page', 1); // Default page if not provided

             DatabaseHelper::Config();

             $query =  Remboursement::on('temp')
            ->whereNotIn('mode_rembourse', ['direct', 'apres_vente'])
            ->without('aquereur','desistement','reservation')
            ->with(['dossier_transfert' => function ($query) {
                $query->select('id', 'code_reservation')
                      ->without('user', 'projet', 'historiques', 'bien', 'aquereurs', 'aquereurs_ancien', 'piece_jointe');
            }])
            ->where('reservation_id', $reservation_id)
            ->where('archive',0);
             // Optional filters (Add more if needed)

             if ($request->filled('date')) {
                 $start = Carbon::parse($request->input('date'));
                 $query->whereDate('created_at' ,$start);
             }
             if ($request->filled('dossier')) {
                $query->whereHas('dossier_transfert', function ($q) use ($request) {
                    $q->where('code_reservation', 'like', '%' . $request->input('dossier') . '%');
                });
            }

             if ($request->filled('montant')) {
                 $query->where('montant_transfert','like', '%' . $request->input('montant') . '%');
             }


             if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                 // Paginate if size and page are valid
                 $transfert = $query->orderBy('created_at', 'desc')
                     ->paginate($size, ['*'], 'page', $page);

                 // Add pagination info
                 $pagination = [
                     'currentPage' => $transfert->currentPage(),
                     'totalItems' => $transfert->total(),
                     'totalPages' => $transfert->lastPage(),
                 ];

                 $transfert = $transfert->items();

                 return response()->json([
                     'data' => $transfert,
                     'pagination' => $pagination,
                 ], 200);
             }
         }

         return response()->json(['error' => 'Unauthorized'], 401);
     }

     public function get_notif_demande_pre_remboursement($projet_id){
        DatabaseHelper::Config();
        if (RoleHelper::AdminSup()) {
            $nb_demande = Remboursement::on('temp')->with('desistement_not_trashed')
            ->whereHas('desistement_not_trashed', function ($q) use ($projet_id) {
                $q->where('projet_id', $projet_id);
            })
            ->where('statut',0)->where('etat',1)->where('archive',0)
            ->where(function ($query) {
                $query->where('mode_rembourse', 'apres_vente')
                    ->orwhere('mode_rembourse', 'transfert_rem_apres_vente')
                ;})->count();
                return response()->json(['nb'=>$nb_demande]);
        }elseif(RoleHelper::Com()){
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $nb_demande = Remboursement::on('temp')->with('desistement_not_trashed')
            ->whereHas('desistement_not_trashed', function ($q) use ($projet_id,$userAuth) {
                $q->where('projet_id', $projet_id)->where('user_id', $userAuth->value('id'));
            })
            ->where('statut',0)->where('etat',1)->where('archive',0)

            ->where(function ($query) {
                $query->where('mode_rembourse', 'apres_vente')
                    ->orwhere('mode_rembourse', 'transfert_rem_apres_vente')
                ;})->count();
            return response()->json(['nb'=>$nb_demande]);

        } else  return response()->json(['error'=>'Unauthorized'], 401);
    }



    public function get_remboursements_dos_transfert(Request $request,$projet_id)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', null);
            $page = $request->input('page', null);
            DatabaseHelper::Config();

            // Démarrer la requête directement sur le
            $query = Remboursement::on('temp')->with('desistement_not_trashed','dossier_transfert','desistement_not_trashed.user')->where('etat',1)->where('archive',0);
            $query->whereHas('desistement_not_trashed', function ($q) use ($projet_id) {
                $q->where('projet_id', $projet_id);
            });
            $query->where(function ($s) {
                $s->where('mode_rembourse', 'transfert')
                    ->orwhere('mode_rembourse', 'transfert_rem_apres_vente')
                    ->orwhere('mode_rembourse', 'transfert_rem_direct')
                ;});
                if ($request->filled('montant')) {
                    $query->where('montant_transfert','like', '%' . number_format($request->input('montant')).'%' );
                }

            if(RoleHelper::Com()){

                $user = Auth::user();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                $query->whereHas('desistement_not_trashed', function ($q) use ($userAuth) {
                    $q->where('user_id',  $userAuth->value('id'));
                });

            }

            if ($request->filled('responsable')) {
                $query->whereHas('desistement_not_trashed.user', function ($q) use ($request) {
                    $q->where('name','like', '%' . $request->input('responsable').'%' )
                    ->orWhere('prenom','like', '%' .$request->input('responsable').'%');
                });
            }

            if ($request->filled('ancien_dossier')) {
                $query->whereHas('reservation', function ($q) use ($request) {
                    $q->where('code_reservation','like', '%' . $request->input('ancien_dossier').'%' );
                });
            }
            if ($request->filled('nouveau_dossier')) {
                $query->whereHas('dossier_transfert', function ($q) use ($request) {
                    $q->where('code_reservation','like', '%' . $request->input('nouveau_dossier').'%' );
                });
            }


            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                $remb = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Extraire les propriétés du paginateur
                $pagination = [
                    'currentPage' => $remb->currentPage(),
                    'totalItems' => $remb->total(),
                    'totalPages' => $remb->lastPage(),
                ];

                // Extraire les éléments d'utilisateur du paginateur
                $remb = $remb->items();

                // Retourner la réponse simplifiée
                return response()->json([
                    'data' => $remb,
                    'pagination' => $pagination,
                ], 200);
            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
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
