<?php

namespace App\Http\Controllers\Api\V1;

use App\Enum\RoleEnum;
use App\Enum\StatutReservationEnum;
use App\Events\NotificationEvent;
use App\Events\NotifMenuEvent;
use App\Events\AvancesEvent;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\NotificationHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreAvanceRequest;
use App\Http\Requests\StorePiecesJointeRequest;
use App\Http\Requests\UpdateAvanceRequest;
use App\Models\Avance;
use App\Models\Bien;
use App\Models\Encaissement;
use App\Models\TvaCollecte;
use App\Models\FicheTransmission;
use App\Models\HistoriqueAvance;
use App\Models\Notification;
use App\Models\Remboursement;
use App\Models\Reservation;
use App\Models\Societe;
use App\Models\StatutAvancePenalite;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use \NumberFormatter;
use App\Http\Helpers\PaginationHelper;

use App\Enum\ModePaiement;
use App\Models\PiecesJointe;
use DB;
use App\Http\Controllers\NotificationController;

class AvanceController extends Controller
{
    /**
     * Display a listing of the resource.
     * get all avances in project
     */
    public function index(Request $request, $projet_id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);
            $avances = Avance::on('temp')->join('reservations', 'avances.reservation_id', '=', 'reservations.id')
                ->whereNull('reservations.deleted_at')
                ->where('reservations.projet_id', $projet_id)
                ->where('reservations.etat', 1)
                ->select('avances.*')->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
            return response()->json(['avances' => $avances], 200);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function getAvancesByReservation(Request $request, $reservation_id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $size = $request->input('size', config('app.default_item_number_perpage'));
            $page = $request->input('page', 1);

            $reservation = Reservation::on('temp')->select('prix', 'etat')->findorfail($reservation_id);
            $sum_avances = 0;
             // Déterminer si on doit utiliser les éléments supprimés
            $withTrashed = $reservation->etat != 1;
            // Construction de la requête de base // Requête principale pour les avances non supprimées

            $query = Avance::on('temp')
                ->with([
                    'last_statut' => function ($query) {
                        $query->without(['avance', 'penalite']); // Désactive les relations prédéfinies
                    },
                    'user' => function ($q) {
                        $q->select('id', 'name', 'prenom');
                    }
                ])
                ->without('reservation')
                ->withCount('historiques')
                ->where('reservation_id', $reservation_id)
                ->orderBy('created_at', 'desc');

            if ($withTrashed) {
                // Requête principale pour les avances non supprimées
                $query->onlyTrashed();
            }
             // Calcul de la somme des avances != refusé avant l'application des filtres
                $avances = $query->get();
                foreach ($avances as $av) {
                    if ($av->statut != StatutReservationEnum::Refusé->value) {
                        $sum_avances += $av->montant;
                    }
                }
                /*sum avances valides if reservation etat !=sum avance supprimé else etat normal */
                $sum_avances_valides = Avance::on('temp')
                ->when($reservation->etat != 1, function ($query) {
                    $query->withTrashed();
                })
                ->where('reservation_id', $reservation_id)
                ->where('statut', StatutReservationEnum::Validé->value)
                ->sum('montant');

            // Application des filtres supplémentaires
            if ($request->filled('numero_paiement')) {
                $query->where('numero_paiement', 'like', '%' . $request->input('numero_paiement') . '%');
            }
            if ($request->filled('montant')) {
                $query->where('montant', 'like', '%' . $request->input('montant') . '%');
            }
            if ($request->filled('mode_paiement')) {
                $query->where('mode_paiement', 'like', '%' . $request->input('mode_paiement') . '%');
            }
            if ($request->filled('cc')) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->input('cc') . '%')
                            ->orWhere('prenom', 'like', '%' . $request->input('cc') . '%');
                    });
                });
            }
            if ($request->filled('date_start')) {
                $start = Carbon::parse($request->input('date_start'));
                $query->whereDate('date_reglement','>=', $start);
            }
            if ($request->filled('date_end')) {
                $end = Carbon::parse($request->input('date_end'));
                $query->whereDate('date_reglement','<=', $end);
            }

            // Récupération des résultats paginés après application des filtres
            $avances = $query->paginate($size, ['*'], 'page', $page);

            // Construction de la pagination
            $pagination = [
                'currentPage' => $avances->currentPage(),
                'totalItems' => $avances->total(),
                'totalPages' => $avances->lastPage(),
            ];

            // Envoi de la réponse
            return response()->json([
                'data' => $avances->items(),
                'pagination' => $pagination,
                'sum_avances' => $sum_avances,
                'sum_avances_valides' => $sum_avances_valides,
                'prix' => $reservation->prix,
                'etat_res' => $reservation->etat,
            ], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function getAvanceHistory(Request $request, $id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $size = $request->input('size', config('app.default_item_number_perpage'));
            $page = $request->input('page', 1);

            $query = HistoriqueAvance::on('temp')->where('avance_id', $id)->with('user', 'banque');
            if ($request->filled('numero_paiement')) {
                $query->where('numero_paiement', 'like', '%' . $request->input('numero_paiement') . '%');
            }
            if ($request->filled('montant')) {
                $query->where('montant', 'like', '%' . $request->input('montant') . '%');
            }
            if ($request->filled('mode_paiement')) {
                $query->where('mode_paiement', 'like', '%' . $request->input('mode_paiement') . '%');
            }
            if ($request->filled('cc')) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->input('cc') . '%')
                            ->orWhere('prenom', 'like', '%' . $request->input('cc') . '%');
                    });
                });
            }

            if ($request->filled('date_start')) {
                $start = Carbon::parse($request->input('date_start'));
                $query->whereDate('date_reglement','>=', $start);
            }
            if ($request->filled('date_end')) {
                $end = Carbon::parse($request->input('date_end'));
                $query->whereDate('date_reglement','<=', $end);
            }
            $historiques = $query->orderBy('created_at', 'desc')
                ->paginate($size, ['*'], 'page', $page);

            $pagination = [
                'currentPage' => $historiques->currentPage(),
                'totalItems' => $historiques->total(),
                'totalPages' => $historiques->lastPage(),
            ];

            $historiques = $historiques->items();

            return response()->json([
                'data' => $historiques,
                'pagination' => $pagination,
            ], 200);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }



    public function get_avances_by_etat($projet_id, $statut, Request $request)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $size = $request->input('size', config('app.default_item_number_perpage'));
            $page = $request->input('page', 1);
            $aa=0;
            if (RoleHelper::AdminSup()) {
                if($statut==3){
                    $query = Avance::on('temp')->with('last_statut','reservation')
                    ->where('mode_paiement','!=',7)->where('montant','>',0) ->orderBy('created_at', 'desc')
                    ->where(function($qq) use ($statut){
                        $qq->where('statut',1)
                            ->orwhere('statut',$statut);
                        });
                    $query->whereHas('reservation', function ($q) use ($projet_id) {
                           $q->where('projet_id', $projet_id)
                                ->where('etat', 1)
                                ->where('statut',StatutReservationEnum::Validé->value);
                    });

                    if ($request->filled('mode_paiement')) {
                        $query->where('mode_paiement', 'like', '%' . $request->input('mode_paiement') . '%');
                    }
                     if ($request->filled('numero_paiement')) {
                        $query->where('numero_paiement', 'like', '%' . $request->input('numero_paiement') . '%');
                    }
                    if ($request->filled('montant')) {
                        $query->where('montant', 'like', '%' . $request->input('montant') . '%');
                    }

                    if ($request->filled('cc')) {
                        $query->whereHas('user', function ($q) use ($request) {
                            $q->where(function ($q) use ($request) {
                                $q->where('name', 'like', '%' . $request->input('cc') . '%')
                                    ->orWhere('prenom', 'like', '%' . $request->input('cc') . '%');
                            });
                        });
                    }
                    if ($request->filled('date_start') && $request->filled('date_end')) {
                        $query->whereBetween('date_reglement', [
                            $request->input('date_start'),
                            $request->input('date_end'),
                        ]);
                    }

                    $array = $query->get();
                    $array_2=array();
                    if(count($array)>0){
                        foreach($array as $ar){
                            if($ar->statut==3){
                                array_push($array_2,$ar);
                            }
                            elseif($ar->last_statut!=null){
                                //$ar->last_statut->num_remise==null &&
                                    if( $ar->last_statut->date_encaissement==null ){
                                        array_push($array_2,$ar);
                                    }
                            }
                        }
                    }

                    // Paginate the array of visites
                    $avances = PaginationHelper::paginate_array($array_2, $size, $page, $request->url());

                }else{
                    $aa=1;
                    $query =Avance::on('temp')->with('last_statut','reservation')
                    ->where('mode_paiement','!=',7)->where('montant','>',0) ->orderBy('created_at', 'desc')
                    ->where('statut', $statut);
                    $query->whereHas('reservation', function ($q) use ($projet_id) {
                           $q->where('projet_id', $projet_id)
                                ->where('etat', 1)
                                ->where('statut',StatutReservationEnum::Validé->value);
                    });

                    $avances = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);
                }


        } elseif (RoleHelper::Com()) {
            $aa=1;
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                $query =Avance::on('temp')->with('last_statut','reservation')
                    ->where('mode_paiement','!=',7)->where('montant','>',0)
                    ->where('statut', $statut)
                    ->where('avances.user_id', $userAuth->value('id'));
                    $query->whereHas('reservation', function ($q) use ($projet_id) {
                           $q->where('projet_id', $projet_id)
                                ->where('etat', 1)
                                ->where('statut',StatutReservationEnum::Validé->value);
                    });
                $avances = $query->orderBy('created_at', 'desc')
                ->paginate($size, ['*'], 'page', $page);

        }
        if($aa==1){
            if ($request->filled('mode_paiement')) {
                $query->where('mode_paiement', 'like', '%' . $request->input('mode_paiement') . '%');
            }
             if ($request->filled('numero_paiement')) {
                $query->where('numero_paiement', 'like', '%' . $request->input('numero_paiement') . '%');
            }
            if ($request->filled('montant')) {
                $query->where('montant', 'like', '%' . $request->input('montant') . '%');
            }

            if ($request->filled('cc')) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->input('cc') . '%')
                            ->orWhere('prenom', 'like', '%' . $request->input('cc') . '%');
                    });
                });
            }
            if ($request->filled('date_start')) {
                $start = Carbon::parse($request->input('date_start'));
                $query->whereDate('date_reglement','>=', $start);
            }
            if ($request->filled('date_end')) {
                $end = Carbon::parse($request->input('date_end'));
                $query->whereDate('date_reglement','<=', $end);
            }

        }
            // Construction de la pagination
            $pagination = [
                'currentPage' => $avances->currentPage(),
                'totalItems' => $avances->total(),
                'totalPages' => $avances->lastPage(),
            ];

            // Envoi de la réponse
            return response()->json([
                'data' => $avances->items(),
                'pagination' => $pagination,

            ], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function traiter_avance($id, Request $request)
    {
        if (RoleHelper::ACSup()) {

            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $avance = Avance::on('temp')->findOrFail($id);
            $reservation = Reservation::on('temp')->findOrFail($avance->reservation_id);
            $bien=Bien::on('temp')->withSum('tva_collectes','tva_a_payer')->findOrFail( $reservation->bien_id);
            $avance->statut = $request->etat;
            if ($avance->save()) {
                //store statut_avances_penalites table=>si validé
                $st_av = new StatutAvancePenalite();
                $st_av->setConnection('temp');
                $st_av->statut = $request->etat;
                if ($request->etat == 1) {
                    $st_av->num_remise = $request->n_remise;
                    $st_av->date_encaissement = $request->date_encaiss;

                } else {
                    $st_av->commentaire = $request->commentaire;
                }

                $st_av->avance_id = $avance->id;
                $st_av->user_id_valider = $userAuth->value('id');
                $st_av->date_validation = Carbon::now();
                $st_av->save();





                Config::set('broadcasting.default', 'pusher_7');
                $reservationId = $avance->reservation_id;
                // Broadcast event to all users subscribed to this reservation
                broadcast(new AvancesEvent($reservationId,null));
                // Get all users who should receive this update (admins, managers, etc.)
                $usersToNotify = User::on('temp')->whereIn('role', [2, 3]) // Adjust roles as needed
                    ->where('id', '!=', $userAuth->value('id')) // Don't notify the current user
                    ->get();
                    // Broadcast to each user's specific channel
                foreach ($usersToNotify as $user) {
                  event(new AvancesEvent(null,$user->user_id_origin)); }// Pass user ID for specific channel

            }

            if ($request->etat == 1) {

                //2 traitement avance
                Config::set('broadcasting.default', 'pusher_5');
                broadcast(new NotifMenuEvent(2));
                if($avance->echeance<=Carbon::now()){
                    //5 echeances
                    broadcast(new NotifMenuEvent(5));
                }
                if ($avance->reservation->user->role == RoleEnum::COMMERCIAL->value) {
                    Config::set('broadcasting.default', 'pusher_3');

                    $data_notif = [
                        'lien' => '/ventes/reservations/' . $avance->reservation_id,
                        'date' => Carbon::now(),
                        'type' => 17,
                        'description' => 'avance validé',
                        'user_id' => $avance->user->user_id_origin,
                        'role' => null,
                        'projet_id' => $avance->reservation->projet_id,
                        'avance_id' => $avance->id,
                        'reservation_id' => $avance->reservation_id,

                    ];
                    $notif_helper = new NotificationHelper();
                    $notif_helper->storeNotification($request->merge($data_notif));
                    broadcast(new NotificationEvent($id));
                }
                //store new notification validé
                $encaiss = new Encaissement();
                $encaiss->setConnection('temp');
                $encaiss->reservation_id = $avance->reservation_id;
                $encaiss->bien_id=$avance->reservation->bien_id;
                $encaiss->type_encaissement = 1; //Avances
                $encaiss->montant = $avance->montant;
                $encaiss->avance_id = $avance->id;
                $encaiss->date_reglement = $avance->created_at;
                $encaiss->date_encaissement = $request->date_encaiss;
                $encaiss->user_id_valider = $userAuth->value('id');
                if($encaiss->save()){
                    if($bien->Bien_Tva!=null){
                        $data=[
                            'montant'=>$avance->montant,
                            'prix'=>$bien->prix,
                            'qp_terrain_valeur'=>$bien->Bien_Tva->qp_terrain_valeur,
                            'ancien_tva_collectes'=>$bien->tva_collectes,
                            'tva_collectes_sum_tva_a_payer'=>$bien->tva_collectes_sum_tva_a_payer,
                            'tva_bien'=>$bien->Bien_Tva->tva,
                            'reservation_id'=>$avance->reservation_id,
                            'bien_id'=>$bien->id,
                            'type'=>'avances',
                            'encaissement_id'=>$encaiss->id
                        ];
                        $this->store_tva_collecte($request->merge($data));
                    }
                }


            } else {
                //2 traitement avance
                Config::set('broadcasting.default', 'pusher_5');
                broadcast(new NotifMenuEvent(2));
                if ($avance->reservation->user->role == RoleEnum::COMMERCIAL->value) {

                    //store new notification rejeté
                    Config::set('broadcasting.default', 'pusher_3');
                    $data_notif = [
                        'lien' => '/ventes/reservations/' . $avance->reservation_id,
                        'date' => Carbon::now(),
                        'type' => 18,
                        'description' => 'avance rejeté',
                        'user_id' => $avance->user->user_id_origin,
                        'role' => null,
                        'projet_id' => $avance->reservation->projet_id,
                        'avance_id' => $avance->id,
                        'reservation_id' => $avance->reservation_id,

                    ];
                    $notif_helper = new NotificationHelper();
                    $notif_helper->storeNotification($request->merge($data_notif));

                    broadcast(new NotificationEvent($id));
                }

            }


            return response()->json(['message' => 'données enregistrés avec succès.'], 200);

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

        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $reservation = Reservation::on('temp')->findOrFail($request->reservation_id);
            $bien=Bien::on('temp')->withSum('tva_collectes','tva_a_payer')->findOrFail($reservation->bien_id);
            $avance = new Avance();
            $avance->setConnection('temp');
            $last_num_recu = Avance::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")
                ->get('num_recu')->first();
            if ($last_num_recu != null) {
                $n_recu = $last_num_recu->num_recu + 1;
                $avance->num_recu = '00' . $n_recu . '';
            } else {
                $avance->num_recu = '001';
            }
           // $avance->sr = (bool) $request->sr;
            if($request->sr=='0'||$request->sr==0){
                $avance->sr=0;
                }
                else{
                    $avance->sr=1;
                }

            $avance->mode_paiement = $request->mode_paiement;
            //cheque cheque-banque cheque cetifice
            if ($request->mode_paiement == 2 || $request->mode_paiement == 3 || $request->mode_paiement == 4) {
                $avance->numero_paiement = $request->numero_paiement;
                $avance->banque_id = $request->banque_id;
                $avance->echeance = $request->echeance;

            }
            //virement versement
            elseif ($request->mode_paiement == 5 || $request->mode_paiement == 6) {
                $avance->numero_paiement = $request->numero_paiement;
                $avance->banque_id = $request->banque_id;
            }
            $avance->user_id = $userAuth->value('id');
            $avance->date_reglement = $request->date_reglement;
            $avance->commentaireAvance = $request->commentaireAvance=='null'?null:$request->commentaireAvance;

            $avance->montant = $request->montant;
            if ($request->montant_par_lettre != null) {
                $avance->montant_par_lettre = $request->montant_par_lettre;
            } else {
                $inWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
                $mnt_lettre = $inWords->format($request->montant);
                $avance->montant_par_lettre = $mnt_lettre;
            }

            $avance->reservation_id = $request->reservation_id;
            if ($request->desistement_id != null) {
                $avance->desistement_id = $request->desistement_id;
                $avance->dossier_id_transfert = $request->dossier_id_transfert;
                $avance->statut = StatutReservationEnum::Validé->value;
               /* $avance->user_id_valider = $userAuth->value('id');
                $avance->date_validation = Carbon::now();
                $avance->date_encaissement = $request->date_encaissement;
                $avance->num_remise = ModePaiement::transfert_dossier->value;*/
                // $avance->mode_transfert = $request->mode_transfert;
            } else {
                if (RoleHelper::Com()) {
                    $avance->statut = StatutReservationEnum::En_Attente->value;
                } elseif (RoleHelper::AdminSup()) {
                    $avance->statut = StatutReservationEnum::Validé->value;
                }
            }
            if($request->montant==0){
                $avance->statut = StatutReservationEnum::Validé->value;
            }

            if ($avance->save()) {

                //store statut_avances table=>si validé
                if($avance->statut==StatutReservationEnum::Validé->value ){
                    $st_avance = new StatutAvancePenalite();
                    $st_avance->setConnection('temp');
                    $st_avance->avance_id=$avance->id;
                    $st_avance->user_id_valider = $userAuth->value('id');
                    $st_avance->date_validation = Carbon::now();
                    $st_avance->date_encaissement = $request->date_encaissement=="null"?null:$request->date_encaissement;
                    $st_avance->num_remise = $request->num_remise=="null"?null:$request->num_remise;
                    $st_avance->save();
                }

                ////storer les pieces jointe de paiement

                 if ($request->has('processed_files') && !empty($request->processed_files)) {
                                $piecesJointeController = new PiecesJointeController();

                                foreach ($request->processed_files as $fileInfo) {
                                    $pieceJointeRequest = new StorePiecesJointeRequest();

                                    $datapieceJointe = [
                                        'fichier' => $fileInfo['file_name'],
                                        'type' => $fileInfo['file_type'],
                                        'avance_id' => $avance->id,
                                        'active' => 1,
                                    ];

                                    $pieceJointeRequest->merge($datapieceJointe);
                                    $piecesJointeController->store($pieceJointeRequest);
                                }
                            }

                            else if ($request->files_avance) {

                                foreach ($request->files_avance as $file) {


                                    $piecesJointeController = new PiecesJointeController();
                                    $pieceJointeRequest = new StorePiecesJointeRequest();
                                    $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                                    $user_connecter = $userAuth->value('user_id_origin');
                                    $user_societes = User::where('id', $user_connecter)->first();
                                    $societe = Societe::findOrfail($user_societes->societe_id);

                                    // Récupérer le nom du fichier
                                    $fileName = $file->getClientOriginalName();
                                    $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/paiements' . '/' . $reservation->code_reservation);
                                    File::makeDirectory($directory, 0755, true, true);
                                    $file->move($directory, $fileName);
                                    $fileType = $file->getClientOriginalExtension();
                                    $datapieceJointe = [
                                        'fichier' => $fileName,
                                        'type' => $fileType,
                                        'avance_id' => $avance->id,
                                        'active' => 1,
                                    ];

                                    $pieceJointeRequest->merge($datapieceJointe);
                                    $piecesJointeController->store($pieceJointeRequest);
                                }
                            }
                //send notification d'echeance
                if ($avance->echeance != null) {
                    Config::set('broadcasting.default', 'pusher_3');
                    $data_notif = [
                        'lien' => '/ventes/reservations/'.$avance->reservation_id,
                        'date' => $avance->echeance,
                        'type' => 5,
                        'description' => 'ECHEANCE',
                        'user_id' => $avance->user->user_id_origin,
                        'role'=>null,
                        'projet_id'=>$avance->reservation->projet_id,
                        'avance_id'=>$avance->id,
                        'reservation_id'=>$request->reservation_id

                    ];
                    $notif_helper = new NotificationHelper();
                    $notif_helper->storeNotification($request->merge($data_notif));
                    broadcast(new NotificationEvent(0));
                    if($avance->echeance<=Carbon::now()){
                        Config::set('broadcasting.default', 'pusher_5');
                        //5 echeances
                        broadcast(new NotifMenuEvent(5));
                    }



                }
                //si avance est cree without reservaation au depart
                //si commercial==> demande validation du paiement
                if($request->avance_with_reservation==false && $avance->reservation->statut==StatutReservationEnum::Validé->value){
                    if (RoleHelper::Com() && $request->montant>0 ) {
                        Config::set('broadcasting.default', 'pusher_3');
                        $data_notif = [
                           // 'lien' => '/reservations/show/'.$avance->reservation_id,
                            'lien'=>'/ventes/reservations/'.$request->reservation_id,
                            'date' => Carbon::now(),
                            'type' => 7,
                            'user_id'=>null,
                            'description' => 'Validation paiement',
                            'role'=>RoleEnum::ADMIN->value,
                            'projet_id'=>$avance->reservation->projet_id,
                            'avance_id'=>$avance->id,
                            'reservation_id'=>$request->reservation_id

                        ];
                        $notif_helper = new NotificationHelper();
                        $notif_helper->storeNotification($request->merge($data_notif));
                        broadcast(new NotificationEvent(0));
                        Config::set('broadcasting.default', 'pusher_5');
                        //2 traitement avance
                        broadcast(new NotifMenuEvent(2));
                    }
                }

                $num_recu='';
                //num recu cree aujourdhui
                $recu_now = FicheTransmission::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")->whereDate('created_at', Carbon::now())
                    ->get('num_recu')->first();
                if ($recu_now != null) {
                    $num_recu = $recu_now->num_recu;

                } else {
                    //num recu cree != aujourdhui
                    $rec_not_now = FicheTransmission::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")->whereDate('created_at', '!=', Carbon::now())
                        ->get('num_recu')->first();
                    if ($rec_not_now != null) {
                        $pp = $rec_not_now->num_recu + 1;
                        $num_recu = '00' . $pp . '';
                    } else {
                        $num_recu = '001';
                    }

                }
                 //store avance to fiche transmission
                $fiche = new FicheTransmission();
                $fiche->setConnection('temp');
                $fiche->num_recu= $num_recu ;
                $fiche->avance_id = $avance->id;
                $fiche->user_id = $userAuth->value('id');
                if ($request->mode_paiement == 2 || $request->mode_paiement == 3 || $request->mode_paiement == 4) {
                    $fiche->date = $request->echeance;
                } else {
                    $fiche->date = Carbon::now();
                }
                $fiche->save();



                if (RoleHelper::AdminSup()) {
                    //store encaissement //&& ($request->num_remise != null && $request->num_remise!="null")
                    if ($request->date_encaissement != null ) {
                        $encaiss = new Encaissement();
                        $encaiss->setConnection('temp');
                        $encaiss->reservation_id = $request->reservation_id;
                        $encaiss->bien_id =$reservation->bien->id;
                        $encaiss->type_encaissement = 1; //Avances
                        $encaiss->montant = $avance->montant;
                        $encaiss->avance_id = $avance->id;
                        $encaiss->date_reglement = $avance->created_at;
                        $encaiss->date_encaissement = $request->date_encaissement;
                        $encaiss->user_id_valider = $userAuth->value('id');
                         //calcul du tva collecte
                            if($encaiss->save()){
                                //get tva du bien
                                if($bien->Bien_Tva!=null){
                                    $data=[
                                        'montant'=>$avance->montant,
                                        'prix'=>$bien->prix,
                                        'qp_terrain_valeur'=>$bien->Bien_Tva->qp_terrain_valeur,
                                        'ancien_tva_collectes'=>$bien->tva_collectes,
                                        'tva_collectes_sum_tva_a_payer'=>$bien->tva_collectes_sum_tva_a_payer,
                                        'tva_bien'=>$bien->Bien_Tva->tva,
                                        'reservation_id'=>$avance->reservation_id,
                                        'bien_id'=>$bien->id,
                                        'type'=>'avances',
                                        'encaissement_id'=>$encaiss->id
                                    ];
                                    $this->store_tva_collecte($request->merge($data));

                                }
                            }
                    }

                    //store commission a voir
                }
            }
                //actualiser avances
                 Config::set('broadcasting.default', 'pusher_7');
                $reservationId = $request->reservation_id;
                // Broadcast event to all users subscribed to this reservation
                broadcast(new AvancesEvent($reservationId,null));
                // Get all users who should receive this update (admins, managers, etc.)
                $usersToNotify = User::on('temp')->whereIn('role', [2, 3]) // Adjust roles as needed
                    ->where('id', '!=', $userAuth->value('id')) // Don't notify the current user
                    ->get();
                    // Broadcast to each user's specific channel
                foreach ($usersToNotify as $user) {
                  event(new AvancesEvent(null,$user->user_id_origin)); }// Pass user ID for specific channel


                //actualiser menu validation avance
                Config::set('broadcasting.default', 'pusher_5');
                broadcast(new NotifMenuEvent(2));
              }
            return response()->json(['avance' => $avance], 200);


        return response()->json(['error' => 'Unauthorized'], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $avance = Avance::on('temp')->with('last_statut')->withcount('historiques')->findOrFail($id);
            return response()->json(['avance' => $avance], 200);
        }
        return response()->json(['error', 'Unauthorized'], 401);
    }

    public function store_tva_collecte(Request $request){
         DatabaseHelper::Config();
         $user = Auth::user();
         $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
       //20% % tva appliqué sur les avances tva/ttc  ou tva/prix HT [0.15 ... 0.20]
       $percent_tva=0.2;
       $avance_terrain=number_format(($request->montant/$request->prix)*$request->qp_terrain_valeur, 2, '.', '');
       $avance_bien_ttc=number_format($request->montant-$avance_terrain, 2, '.', '');
       $avance_bien_ht=number_format($avance_bien_ttc/(1+$percent_tva), 2, '.', '');
       $tva_col_a_ajouter=number_format($percent_tva*$avance_bien_ht, 2, '.', '');
       //exist tva collecte du bien
       if (count($request->ancien_tva_collectes)>0){
             //somme du ancien tva collect
           $tva_collect_sum=$request->tva_collectes_sum_tva_a_payer;
             //difference avec le tva d'apprtement
           $diff=$request->tva_bien-$tva_collect_sum;
           //si diff>tva_coll_a_ajouter_new
               if($diff>=$tva_col_a_ajouter){
                   $tva_c=new TvaCollecte();
                   $tva_c->setConnection('temp');
                   $tva_c->reservation_id=$request->reservation_id;
                   $tva_c->bien_id=$request->bien_id;
                   $tva_c->encaissement_id=$request->encaissement_id;
                   if($request->type=='remboursements'){
                    $tva_c->tva_a_payer=-$tva_col_a_ajouter;  //+ pour avances
                   }else{
                    $tva_c->tva_a_payer=$tva_col_a_ajouter;  //+ pour avances
                   }
                   //added
                   $tva_c->avance_terrain=$avance_terrain;
                   $tva_c->avance_bien_ttc=$avance_bien_ttc;
                   $tva_c->avance_bien_ht=$avance_bien_ht;
                   $tva_c->user_id= $userAuth->value('id');
                   $tva_c->save();
               }
               //else rien a ajouter tva_collecter_new>tva du bien on peut pas ajouter des tva collecte
       }else{
           //a jouter first tva collecte
                   $tva_c=new TvaCollecte();
                   $tva_c->setConnection('temp');
                   $tva_c->reservation_id=$request->reservation_id;
                   $tva_c->bien_id=$request->bien_id;
                   $tva_c->encaissement_id=$request->encaissement_id;
                   if($request->type=='remboursements'){
                    $tva_c->tva_a_payer=-$tva_col_a_ajouter;  //+ pour avances
                   }else{
                    $tva_c->tva_a_payer=$tva_col_a_ajouter;  //+ pour avances
                   }
                   //added
                   $tva_c->avance_terrain=$avance_terrain;
                   $tva_c->avance_bien_ttc=$avance_bien_ttc;
                   $tva_c->avance_bien_ht=$avance_bien_ht;
                   $tva_c->user_id= $userAuth->value('id');
                   $tva_c->save();
       }
    }
    /**
     * Display the specified resource.
     */


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
    public function update(UpdateAvanceRequest $request, $id)
    {

        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $avance = Avance::on('temp')->findOrFail($id);
            $old_date_encaisse=null;
            $old_n_remise=null;
            $old_commmentaire_re=null;
            $old_user_id=null;
            $old_date_valid=null;
            $reservation = Reservation::on('temp')->findOrFail($avance->reservation_id);
            $bien=Bien::on('temp')->withSum('tva_collectes','tva_a_payer')->findOrFail( $reservation->bien_id);

            if($avance->statut==StatutReservationEnum::Validé->value||$avance->statut==StatutReservationEnum::Refusé->value  ){
                $old_st_avance = StatutAvancePenalite::on('temp')->where('avance_id',$id)->orderBy('created_at','desc')->first();
                if($old_st_avance!=null){
                    $old_date_encaisse=$old_st_avance->date_encaissement;
                    $old_n_remise=$old_st_avance->num_remise;
                    $old_commmentaire_re=$old_st_avance->commentaire;
                    $old_user_id=$old_st_avance->user_id_valider;
                    $old_date_valid=$old_st_avance->date_validation;
                }
            }
            //store historique
            $histo = new HistoriqueAvance();
            $histo->setConnection('temp');
            $histo->avance_id = $id;
            $histo->num_recu = $avance->num_recu;
            $histo->sr = $avance->sr;
            $histo->mode_paiement = $avance->mode_paiement;
            $histo->numero_paiement = $avance->numero_paiement;
            $histo->banque_id = $avance->banque_id;
            $histo->echeance = $avance->echeance;
            $histo->user_id = $userAuth->value('id');
            $histo->date_reglement = $avance->date_reglement;
            $histo->commentaireAvance = $avance->commentaireAvance;
            $histo->montant = $avance->montant;
            $histo->montant_par_lettre = $avance->montant_par_lettre;
            $histo->statut = $avance->statut;
            $histo->commentaire_rejete=$old_commmentaire_re;
            $histo->user_id_valider = $old_user_id;
            $histo->date_validation =$old_date_valid;
            $histo->date_encaissement = $old_date_encaisse;
            $histo->num_remise = $old_n_remise;

            if ($histo->save()) {
                $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
                $societe = Societe::findOrfail($user_societes->societe_id);

               //****edit piece jointe***
               if (!$request->file('files_avance')) {
                   $pjController = new PiecesJointeController();
                   $pjController->destoryFileUsingAvanceId($id,$societe);

               }
                           if ($request->file('files_avance')) {

                                //****delete old piece jointe***

                                $pjController = new PiecesJointeController();
                                $pjController->destoryFileUsingAvanceId($id,$societe);

                                foreach ($request->file('files_avance') as $file) {

                                    $piecesJointeController = new PiecesJointeController();
                                    $pieceJointeRequest = new StorePiecesJointeRequest();

                                    // Récupérer le nom du fichier
                                    $Myfile = $file->getClientOriginalName();

                                    $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id  . '/paiements' . '/' . $reservation->code_reservation);
                                    File::makeDirectory($directory, 0755, true, true);
                                    $file->move($directory, $Myfile);
                                    $fileType = $file->getClientOriginalExtension();
                                    $datapieceJointe = [
                                        'fichier' => $Myfile,
                                        'type' => $fileType,
                                        'avance_id' => $avance->id,
                                        'active' => 1,

                                    ];

                                    $pieceJointeRequest->merge($datapieceJointe);
                                    $piecesJointeController->store($pieceJointeRequest);

                                }
                            }
                if($request->sr=='0'){
                    $avance->sr=0;
                }
                else{
                    $avance->sr=1;
                }

                $avance->mode_paiement = $request->mode_paiement;
                //cheque cheque-banque cheque cetifice
                if ($request->mode_paiement == 2 || $request->mode_paiement == 3 || $request->mode_paiement == 4) {
                    $avance->numero_paiement = $request->numero_paiement;
                    $avance->banque_id = $request->banque_id;
                    $avance->echeance = $request->echeance;

                }
                //virement versement
                elseif ($request->mode_paiement == 5 || $request->mode_paiement == 6) {
                    $avance->numero_paiement = $request->numero_paiement;
                    $avance->banque_id = $request->banque_id;
                    $avance->echeance = null;
                } else { //espece
                    $avance->numero_paiement = null;
                    $avance->banque_id = null;
                    $avance->echeance = null;
                }
                $avance->user_id = $userAuth->value('id');
                $avance->commentaireAvance = $request->commentaireAvance=='null'?null:$request->commentaireAvance;
                $avance->montant = $request->montant;
                $inWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
                $mnt_lettre = $inWords->format($request->montant);
                $avance->montant_par_lettre = $mnt_lettre;

                if (RoleHelper::AdminSup()) {
                    //rejete et remodifier par admin
                    if ($avance->statut == StatutReservationEnum::Refusé->value) {
                        $avance->statut = StatutReservationEnum::Validé->value;
                    }
                }
                //si commercial  si deja rejete on fait statut =>en cours
                elseif(RoleHelper::Com()){
                    if ($avance->statut == StatutReservationEnum::Refusé->value) {
                        $avance->statut = StatutReservationEnum::En_Attente->value;
                    }
                }
                if ($request->montant == 0) {
                    $avance->statut = StatutReservationEnum::Validé->value;
                }

                $last_num_recu = Avance::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")
                    ->get('num_recu')->first();
                if ($last_num_recu != null) {
                    $n_recu = $last_num_recu->num_recu + 1;
                    $avance->num_recu = '00' . $n_recu . '';
                } else {
                    $avance->num_recu = '001';
                }

                if ($avance->save()) {
                       // remodifier fiche transmission
                $fiche = FicheTransmission::on('temp')->where('avance_id', $avance->id)->orderby('created_at', 'desc')->first();
                if ($fiche != null) {
                    $fiche->setConnection('temp');
                    if ($request->mode_paiement == 2 || $request->mode_paiement == 3 || $request->mode_paiement == 4) {
                        $fiche->date = $request->echeance;
                    } else {
                        $fiche->date = Carbon::now();
                    }
                    $fiche->save();
                }

                    if(RoleHelper::AdminSup()){
                        //&& ($request->num_remise!=null || $request->num_remise!="null")
                        if($request->date_encaissement!=null   ){
                            if($avance->statut==StatutReservationEnum::Validé->value ){
                                $st_avance = StatutAvancePenalite::on('temp')->where('avance_id',$avance->id)->orderBy('created_at','desc')->first();
                                if($st_avance!=null){
                                    $st_avance->setConnection('temp');
                                    $st_avance->avance_id=$avance->id;
                                    $st_avance->user_id_valider = $userAuth->value('id');
                                    $st_avance->date_validation = Carbon::now();
                                    $st_avance->date_encaissement = $request->date_encaissement=="null"?null:$request->date_encaissement;
                                    $st_avance->num_remise = $request->num_remise=="null"?null:$request->num_remise;
                                    $st_avance->save();
                                }

                            }else{
                                $st_avance = new StatutAvancePenalite();
                                $st_avance->setConnection('temp');
                                $st_avance->avance_id=$avance->id;
                                $st_avance->user_id_valider = $userAuth->value('id');
                                $st_avance->date_validation = Carbon::now();
                                $st_avance->date_encaissement = $request->date_encaissement;
                                $st_avance->num_remise = $request->num_remise=="null"?null:$request->num_remise;
                                $st_avance->save();
                            }

                        //remodifier encaissement

                        $encaiss = Encaissement::on('temp')->where('avance_id', $avance->id)->orderby('created_at', 'desc')->first();

                        if ($encaiss != null) {
                            $encaiss->setConnection('temp');
                            $encaiss->montant = $avance->montant;
                            $encaiss->avance_id = $avance->id;
                            $encaiss->date_reglement = $avance->updated_at;
                            $encaiss->date_encaissement = $request->date_encaissement;
                            $encaiss->user_id_valider = $userAuth->value('id');
                            if($encaiss->save()){
                                //get tva du bien
                                if($bien->Bien_Tva!=null){
                                    //supprime ancien tva collecte by encaisse _id
                                    $tva_collecte=TvaCollecte::on('temp')->where('encaissement_id',$encaiss->id)->first();
                                    if($tva_collecte!=null){
                                        $tva_collecte->forceDelete();
                                        $data=[
                                            'montant'=>$avance->montant,
                                            'prix'=>$bien->prix,
                                            'qp_terrain_valeur'=>$bien->Bien_Tva->qp_terrain_valeur,
                                            'ancien_tva_collectes'=>$bien->tva_collectes,
                                            'tva_collectes_sum_tva_a_payer'=>$bien->tva_collectes_sum_tva_a_payer,
                                            'tva_bien'=>$bien->Bien_Tva->tva,
                                            'reservation_id'=>$avance->reservation_id,
                                            'bien_id'=>$bien->id,
                                            'type'=>'avances',
                                            'encaissement_id'=>$encaiss->id
                                        ];
                                        $this->store_tva_collecte($request->merge($data));
                                    }


                                }
                            }
                        }else{
                            $encaiss = new Encaissement();
                            $encaiss->setConnection('temp');
                            $encaiss->reservation_id = $avance->reservation_id;
                            $encaiss->bien_id=$avance->reservation->bien_id;
                            $encaiss->type_encaissement = 1; //Avances
                            $encaiss->montant = $avance->montant;
                            $encaiss->avance_id = $avance->id;
                            $encaiss->date_reglement = $avance->created_at;
                            $encaiss->date_encaissement =$request->date_encaissement;
                            $encaiss->user_id_valider = $userAuth->value('id');
                            if($encaiss->save()){
                                if($bien->Bien_Tva!=null){
                                    $data=[
                                        'montant'=>$avance->montant,
                                        'prix'=>$bien->prix,
                                        'qp_terrain_valeur'=>$bien->Bien_Tva->qp_terrain_valeur,
                                        'ancien_tva_collectes'=>$bien->tva_collectes,
                                        'tva_collectes_sum_tva_a_payer'=>$bien->tva_collectes_sum_tva_a_payer,
                                        'tva_bien'=>$bien->Bien_Tva->tva,
                                        'reservation_id'=>$avance->reservation_id,
                                        'bien_id'=>$bien->id,
                                        'type'=>'avances',
                                        'encaissement_id'=>$encaiss->id
                                    ];
                                    $this->store_tva_collecte($request->merge($data));

                                }
                            }
                        }
                        }


                    }

                    //delete old notificcation
                    $old_notif = Notification::on('temp')->where('avance_id', $avance->id)->get();
                    if (count($old_notif) > 0) {
                        foreach ($old_notif as $nt) {
                            $nt->delete();
                        }
                    }
                    //notif echeance
                    Config::set('broadcasting.default', 'pusher_3');
                    if ($avance->echeance != null) {
                        $data_notif = [
                            'lien' => '/ventes/reservations/' . $avance->reservation_id,
                            'date' => $avance->echeance,
                            'type' => 5,
                            'description' => 'ECHEANCE',
                            'role'=>null,
                            'user_id'=>$avance->user->user_id_origin,
                            'projet_id'=>$avance->reservation->projet_id,
                            'avance_id'=>$avance->id,
                            'reservation_id'=>$avance->reservation_id

                        ];
                        $notif_helper = new NotificationHelper();
                        $notif_helper->storeNotification($request->merge($data_notif));
                        broadcast(new NotificationEvent($id));
                        if($avance->echeance<=Carbon::now()){
                            Config::set('broadcasting.default', 'pusher_5');
                            broadcast(new NotifMenuEvent(5));
                        }



                    }
                    //si commercial==> demande validation du paiement
                        if (RoleHelper::Com()) {
                            $data_notif = [
                                'lien' => '/ventes/reservations/' . $avance->reservation_id,
                                'date' => Carbon::now(),
                                'type' => 7,
                                'user_id'=>null,
                                'description' => 'Validation paiement',
                                'role'=>RoleEnum::ADMIN->value,
                                'projet_id'=>$avance->reservation->projet_id,
                                'avance_id'=>$avance->id,
                                'reservation_id'=>$avance->reservation_id

                            ];
                            $notif_helper = new NotificationHelper();
                            $notif_helper->storeNotification($request->merge($data_notif));
                            broadcast(new NotificationEvent($id));
                        }
                        if($avance->reservation->statut==StatutReservationEnum::Validé->value){
                            if (RoleHelper::Com() && $request->montant>0 ) {
                                Config::set('broadcasting.default', 'pusher_3');
                                $data_notif = [
                                // 'lien' => '/reservations/show/'.$avance->reservation_id,
                                    'lien'=>'/ventes/reservations/'.$id,
                                    'date' => Carbon::now(),
                                    'type' => 7,
                                    'user_id'=>null,
                                    'description' => 'Validation paiement',
                                    'role'=>RoleEnum::ADMIN->value,
                                    'projet_id'=>$avance->reservation->projet_id,
                                    'avance_id'=>$avance->id,
                                    'reservation_id'=>$request->reservation_id

                                ];
                                $notif_helper = new NotificationHelper();
                                $notif_helper->storeNotification($request->merge($data_notif));
                                                        broadcast(new NotificationEvent($id));

                                Config::set('broadcasting.default', 'pusher_5');
                                //2 traitement avance
                                broadcast(new NotifMenuEvent(2));
                            }
                        }


                            //actualiser avances
                         Config::set('broadcasting.default', 'pusher_7');
                            $reservationId = $request->reservation_id;
                            // Broadcast event to all users subscribed to this reservation
                            broadcast(new AvancesEvent($reservationId,null));
                            // Get all users who should receive this update (admins, managers, etc.)
                            $usersToNotify = User::on('temp')->whereIn('role', [2, 3]) // Adjust roles as needed
                                ->where('id', '!=', $userAuth->value('id')) // Don't notify the current user
                                ->get();
                                // Broadcast to each user's specific channel
                            foreach ($usersToNotify as $user) {
                            event(new AvancesEvent(null,$user->user_id_origin)); }// Pass user ID for specific channel

                    }
                            }

                            return response()->json(['avance' => $avance], 200);
                        }
                        return response()->json(['error', 'Unauthorized'], 401);
                    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::config();
            $avance = Avance::on('temp')->findOrFail($id);
            $dd=$avance;
            if(count($avance->all_piece_jointe)>0){
                foreach($avance->all_piece_jointe as $all_p){
                    $all_p->delete();
                }
            }
            $st = StatutAvancePenalite::on('temp')->where('avance_id', $id)->get();
            foreach ($st as $s) {
                $s->forceDelete();
            }
            $histo = HistoriqueAvance::on('temp')->where('avance_id', $id)->get();
            foreach ($histo as $h) {
                $h->forceDelete();
            }
            $fiche = FicheTransmission::on('temp')->where('avance_id', $id)->get();
            if(count($fiche)>0){
                foreach ($fiche as $f) {
                    $f->forceDelete();
                }
            }

            $encaiss = Encaissement::on('temp')->where('avance_id', $id)->get();

            foreach ($encaiss as $en) {
                $tva_collectes=TvaCollecte::on('temp')->where('encaissement_id',$en->id)->get();
                if(count($tva_collectes)>0){
                    foreach($tva_collectes as $tvc){
                        $tvc->forceDelete();
                    }
                }
                $en->forceDelete();
            }
            $notif = new NotificationController();
            $notif->destory_force_by_column_id('avance', $id);

            if ($avance->forceDelete()) {
            Config::set('broadcasting.default', 'pusher_7');
                $reservationId = $avance->reservation_id;
                // Broadcast event to all users subscribed to this reservation
                broadcast(new AvancesEvent($reservationId,null));
                // Get all users who should receive this update (admins, managers, etc.)

                $user = Auth::user();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                $usersToNotify = User::on('temp')->whereIn('role', [2, 3]) // Adjust roles as needed
                    ->where('id', '!=', $userAuth->value('id')) // Don't notify the current user
                    ->get();
                    // Broadcast to each user's specific channel
                foreach ($usersToNotify as $user) {
                  event(new AvancesEvent(null,$user->user_id_origin)); }// Pass user ID for specific channel


                return response()->json(['message' => 'avance deleted succesfully'], 200);
            } else {
                return response()->json(['message' => 'avance non deleted']);
            }
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function soft_destroy_avances_by_reservationId($reservation_id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::config();
            $avances = Avance::on('temp')->where('reservation_id', $reservation_id)->get();
            foreach ($avances as $avance) {
                $histo = HistoriqueAvance::on('temp')->where('avance_id', $avance->id)->get();
                foreach ($histo as $h) {
                    $h->delete();
                }
                $fiche = FicheTransmission::on('temp')->where('avance_id', $avance->id)->get();
                foreach ($fiche as $f) {
                    $f->delete();
                }
                $encaiss = Encaissement::on('temp')->where('avance_id', $avance->id)->get();

                foreach ($encaiss as $en) {
                    $en->delete();
                }
                $avance->delete();

            }
            return response()->json(['message' => 'Avances supprimés avec succès'], 200);

        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    public function destoryUsingReservationId($reservation_id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $avances = Avance::on('temp') ->where(function ($query)use ($reservation_id){
                $query->where('reservation_id',$reservation_id)
                    ->orwhere('reservation_id_ancien',$reservation_id);})
            ->get();
            foreach ($avances as $av) {
                //fiche transmission
                $fich_transmission = FicheTransmission::on('temp')->where('avance_id', $av->id)->get();
                foreach ($fich_transmission as $f) {
                    $f->delete();
                }
                //supprimer avan
                $av->delete();
            }
            //encaissements
            $encaiss = Encaissement::on('temp')->where('reservation_id', $reservation_id)->get();
            foreach ($encaiss as $en) {
                $en->delete();
            }

            return response()->json(['message' => 'Avance deleted successfully'], 200);

        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    //att validation ou valdé mais att encaissement
    public function get_notif_avances_att_validation($projet_id)
    {

        if (Auth::guard('api')->check() && RoleHelper::ACSup()) {
            DatabaseHelper::Config();

            if (RoleHelper::AdminSup()) {
                //avance en attente et avance  stored by admin(validé) mais sans encaissement
                $query = Avance::on('temp')->with('last_statut','reservation')
                    ->where('mode_paiement','!=',7)->where('montant','>',0) ->orderBy('created_at', 'desc')
                    ->where(function($qq) {
                        $qq->where('statut',1)
                            ->orwhere('statut',3);
                        });
                    $query->whereHas('reservation', function ($q) use ($projet_id) {
                           $q->where('projet_id', $projet_id)
                                ->where('etat', 1)
                                ->where('statut',StatutReservationEnum::Validé->value);
                    });
                    $array = $query->get();
                    $nb_att_validation=0;
                    if(count($array)>0){
                        foreach($array as $ar){
                            if($ar->statut==3){
                                $nb_att_validation+=1;
                            }
                            elseif($ar->last_statut!=null){
                                //$ar->last_statut->num_remise==null &&
                                    if( $ar->last_statut->date_encaissement==null ){
                                        $nb_att_validation+=1;
                                    }
                            }
                        }
                    }

            } else if (RoleHelper::Com()) {
                $user = Auth::user();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();

                $nb_att_validation = Avance::on('temp')->join('reservations', 'avances.reservation_id', '=', 'reservations.id')
                    ->where('reservations.etat', 1)
                    ->whereNull('reservations.deleted_at')
                    ->where('reservations.statut', StatutReservationEnum::Validé->value)
                    ->where('avances.statut', 3)
                    ->where('avances.user_id', $userAuth->value('id'))
                    ->whereNull('reservations.deleted_at')
                    ->where('reservations.projet_id', $projet_id)->count();
            }
            return response()->json(['nb' => $nb_att_validation]);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }

    public function get_echeances($projet_id, Request $request)
    {

        if (Auth::guard('api')->check() && RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $size = $request->input('size', config('app.default_item_number_perpage'));
            $page = $request->input('page', 1);

            if (RoleHelper::AdminSup()) {
                //ADMIN
                    $query =Avance::on('temp')->with('last_statut','reservation')
                    ->where('mode_paiement','!=',7)->where('montant','>',0)
                    ->where('statut', StatutReservationEnum::Validé->value)
                    ->whereDate('echeance', '<=', Carbon::now());
                    $query->whereHas('reservation', function ($q) use ($projet_id) {
                           $q->where('projet_id', $projet_id)
                                ->where('etat', 1)
                                ->where('statut',StatutReservationEnum::Validé->value);
                    });
            } else
            if (RoleHelper::Com()) {
                $user = Auth::user();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();

                    $query =Avance::on('temp')->with('last_statut','reservation')
                    ->where('mode_paiement','!=',7)->where('montant','>',0)
                    ->where('statut', StatutReservationEnum::Validé->value)
                    ->whereDate('echeance', '<=', Carbon::now())  ->where('avances.user_id', $userAuth->value('id'));
                    $query->whereHas('reservation', function ($q) use ($projet_id) {
                           $q->where('projet_id', $projet_id)
                                ->where('etat', 1)
                                ->where('statut',StatutReservationEnum::Validé->value);
                    });

            }

            if ($request->filled('mode_paiement')) {
                $query->where('mode_paiement', 'like', '%' . $request->input('mode_paiement') . '%');
            }
             if ($request->filled('numero_paiement')) {
                $query->where('numero_paiement', 'like', '%' . $request->input('numero_paiement') . '%');
            }
            if ($request->filled('montant')) {
                $query->where('montant', 'like', '%' . $request->input('montant') . '%');
            }

            if ($request->filled('cc')) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->input('cc') . '%')
                            ->orWhere('prenom', 'like', '%' . $request->input('cc') . '%');
                    });
                });
            }
            if ($request->filled('date_start')) {
                $start = Carbon::parse($request->input('date_start'));
                $query->whereDate('date_reglement','>=', $start);
            }
            if ($request->filled('date_end')) {
                $end = Carbon::parse($request->input('date_end'));
                $query->whereDate('date_reglement','<=', $end);
            }

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                $echeances = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Extraire les propriétés du paginateur
                $pagination = [
                    'currentPage' => $echeances->currentPage(),
                    'totalItems' => $echeances->total(),
                    'totalPages' => $echeances->lastPage(),
                ];

                // Extraire les éléments d'utilisateur du paginateur
                $echeances = $echeances->items();

                // Retourner la réponse simplifiée
                return response()->json([
                    'data' => $echeances,
                    'pagination' => $pagination,
                ], 200);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }




    public function get_echeances_menu($projet_id, Request $request)
    {

        if (Auth::guard('api')->check() && RoleHelper::ACSup()) {
            DatabaseHelper::Config();


            if (RoleHelper::AdminSup()) {
                //ADMIN
                $echeances = Avance::on('temp')
                    ->join('reservations', 'avances.reservation_id', '=', 'reservations.id')
                    ->whereNull('reservations.deleted_at')
                    ->where('reservations.projet_id', $projet_id)
                    ->where('avances.mode_paiement','!=',7)->where('avances.montant','>',0)
                    //->where('avances.sr', 1)
                    ->where('avances.statut', StatutReservationEnum::Validé->value)
                    ->whereDate('avances.echeance', '<=', Carbon::now())
                    ->where('reservations.etat', 1)->where('reservations.statut',StatutReservationEnum::Validé->value)->orderBy('created_at', 'desc')
                    ->count();

            } else
            if (RoleHelper::Com()) {
                $user = Auth::user();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                    $echeances = Avance::on('temp')
                    ->join('reservations', 'avances.reservation_id', '=', 'reservations.id')
                    ->whereNull('reservations.deleted_at')
                    ->where('reservations.projet_id', $projet_id)
                    ->where('avances.mode_paiement','!=',7)->where('avances.montant','>',0)
                    //->where('avances.sr', 1)
                    ->where('avances.user_id', $userAuth->value('id'))
                    ->where('avances.statut', StatutReservationEnum::Validé->value)
                    ->whereDate('avances.echeance', '<=', Carbon::now())
                    ->where('reservations.etat', 1)->where('reservations.statut',StatutReservationEnum::Validé->value)->orderBy('created_at', 'desc')
                    ->count();

            }
            return response()->json(['nb' => $echeances]);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }
}
