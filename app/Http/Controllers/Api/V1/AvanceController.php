<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Helpers\FichierHelper;  // AJOUTER CETTE LIGNE

use App\Enum\RoleEnum;
use App\Enum\StatutReservationEnum;
use App\Events\NotificationEvent;
use App\Events\NotifMenuEvent;
use App\Events\AvancesEvent;
use Mail;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\NotificationHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreAvanceRequest;
use App\Http\Requests\StorePiecesJointeRequest;
use App\Http\Requests\UpdateAvanceRequest;
use App\Models\Avance;
use App\Models\Bien;
use App\Models\StatutClient;
use App\Models\Aquereur;

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
        if (RoleHelper::ACSup_RC() || RoleHelper::AgentAdmin() || RoleHelper::AgentAdmin()||RoleHelper::Notaire()||RoleHelper::RespoLivraison()||RoleHelper::Comptable()) {
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
        if (RoleHelper::ACSup_RC() || RoleHelper::AgentAdmin() || RoleHelper::AgentAdmin()||RoleHelper::Comptable()) {
            DatabaseHelper::Config();
            $size = $request->input('size', config('app.default_item_number_perpage'));
            $page = $request->input('page', 1);
            $aa=0;
            if (RoleHelper::AdminSup() || RoleHelper::AgentAdmin() || RoleHelper::AgentAdmin()||RoleHelper::Comptable()) {
                if($statut==3){
                    $query = Avance::on('temp')->
                    with([
                        'last_statut' => function($query) {
                            $query->without('avance', 'penalite');
                        },
                        'reservation' => function($query) {
                            $query->with([
                                'bien' => function($query) {
                                    $query->with([
                                        'immeuble' => function($q) {
                                            $q->select('id', 'nom')
                                            ->without(['projet', 'tranche', 'bloc']);
                                        },
                                        'bloc' => function($q) {
                                            $q->select('id', 'nom')
                                            ->without(['projet', 'tranche']);
                                        },
                                        'tranche' => function($q) {
                                            $q->select('id', 'nom')
                                            ->without(['projet']);
                                        }
                                    ])->without('projet', 'typologie', 'vue', 'compositionBien', 'typeBien');
                                }
                            ])->without('user', 'projet', 'historiques', 'piece_jointe', 'aquereurs_ancien');
                        },
                    ])
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
                    $query =Avance::on('temp')->with([
                        'last_statut' => function($query) {
                            $query->without('avance', 'penalite');
                        },
                        'reservation' => function($query) {
                            $query->with([
                                'bien' => function($query) {
                                    $query->with([
                                        'immeuble' => function($q) {
                                            $q->select('id', 'nom')
                                            ->without(['projet', 'tranche', 'bloc']);
                                        },
                                        'bloc' => function($q) {
                                            $q->select('id', 'nom')
                                            ->without(['projet', 'tranche']);
                                        },
                                        'tranche' => function($q) {
                                            $q->select('id', 'nom')
                                            ->without(['projet']);
                                        }
                                    ])->without('projet', 'typologie', 'vue', 'compositionBien', 'typeBien');
                                }
                            ])->without('user', 'projet', 'historiques', 'piece_jointe', 'aquereurs_ancien');
                        },
                    ])
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


        } elseif (RoleHelper::Com()||RoleHelper::RespoCommercial()) {
            $aa=1;
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                $query =Avance::on('temp')
                -> with([
                        'last_statut' => function($query) {
                            $query->without('avance', 'penalite');
                        },
                        'reservation' => function($query) {
                            $query->with([
                                'bien' => function($query) {
                                    $query->with([
                                        'immeuble' => function($q) {
                                            $q->select('id', 'nom')
                                            ->without(['projet', 'tranche', 'bloc']);
                                        },
                                        'bloc' => function($q) {
                                            $q->select('id', 'nom')
                                            ->without(['projet', 'tranche']);
                                        },
                                        'tranche' => function($q) {
                                            $q->select('id', 'nom')
                                            ->without(['projet']);
                                        }
                                    ])->without('projet', 'typologie', 'vue', 'compositionBien', 'typeBien');
                                }
                            ])->without('user', 'projet', 'historiques', 'piece_jointe', 'aquereurs_ancien');
                        },
                    ])
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
        if (RoleHelper::AdminSup() || RoleHelper::AgentAdmin() || RoleHelper::AgentAdmin()||RoleHelper::Comptable()) {

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

                Config::set('broadcasting.default', 'pusher_list');
                $reservationId = $avance->reservation_id;
                $projet_id= $avance->reservation->projet_id;
                // Broadcast event to all users subscribed to this reservation
                broadcast(new AvancesEvent($reservationId,null));
                // Get all users who should receive this update (admins, managers, etc.)
                $usersToNotify = User::on('temp')
                    ->where('id', '!=', $userAuth->value('id')) // Don't notify the current user
                     ->where('role','!=',8)
                    ->whereHas('projets', function($query) use ($projet_id) {
                                        $query->where('projet_id', $projet_id);
                                    })
                    ->get();
                    // Broadcast to each user's specific channel
                foreach ($usersToNotify as $user) {
                  event(new AvancesEvent(null,$user->user_id_origin)); }// Pass user ID for specific channel

            }

            if ($request->etat == 1) {

                //2 traitement avance
                Config::set('broadcasting.default', 'pusher_notify');
                broadcast(new NotifMenuEvent(2));
                if($avance->echeance<=Carbon::now()){
                    //5 echeances
                    broadcast(new NotifMenuEvent(5));
                }
                if ($avance->user->role == RoleEnum::COMMERCIAL->value) {
                    Config::set('broadcasting.default', 'pusher_notify');

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
                Config::set('broadcasting.default', 'pusher_notify');
                broadcast(new NotifMenuEvent(2));
                if ($avance->user->role == RoleEnum::COMMERCIAL->value) {

                    //store new notification rejeté
                    //Config::set('broadcasting.default', 'pusher_notify');
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

        if (RoleHelper::ACSup_RC() || RoleHelper::AgentAdmin()||RoleHelper::Notaire()||RoleHelper::RespoLivraison()||RoleHelper::RespoCommercial()) {
                    DatabaseHelper::Config();
                    DB::connection('temp')->beginTransaction();
                try {
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
                        if (RoleHelper::Com()||RoleHelper::Notaire()||RoleHelper::RespoLivraison()||RoleHelper::RespoCommercial()) {
                            $avance->statut = StatutReservationEnum::En_Attente->value;
                        } elseif (RoleHelper::AdminSup() || RoleHelper::AgentAdmin() ) {
                            $avance->statut = StatutReservationEnum::Validé->value;
                        }
                    }
                    if($request->montant==0){
                        $avance->statut = StatutReservationEnum::Validé->value;
                    }

                    if ($avance->save()) {
                     DB::connection('temp')->commit();
                        $projet_id = $avance->reservation->projet_id;
                        // CREATE STATUTCLIENT FOR EACH CLIENT AFTER AVANCE IS SAVED
                        // Create StatutClient if:
                        // 1. Called from frontend (no origin parameter) OR
                        // 2. Origin exists and is not 'visite'
                        if ((!isset($request->origin) || $request->origin != 'visite')) {
                            if ($avance->statut == StatutReservationEnum::Validé->value) {
                                $this->createStatutClientForAvance($avance, $userAuth);
                            }
                        }
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

                                            FichierHelper::ajouter_fichier(
                                                $file,
                                                $societe->raison_sociale_concatene,
                                                $societe->id,
                                                'paiements/' . $reservation->code_reservation,
                                                $fileName
                                            );

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
                            Config::set('broadcasting.default', 'pusher_notify');
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
                                //5 echeances
                                broadcast(new NotifMenuEvent(5));
                            }



                        }
                        //si avance est cree without reservaation au depart
                        //si commercial==> demande validation du paiement
                        // && $avance->reservation->statut==StatutReservationEnum::Validé->value
                       if($request->avance_with_reservation==false ){
                            if ((RoleHelper::Com()||RoleHelper::Notaire()||RoleHelper::RespoLivraison()||RoleHelper::RespoCommercial()) && $request->montant>0 ) {
                            //send mail to admin et comptable avec etat

                            // Get all admin and comptable users for this project
                            $admins = User::on('temp')
                                ->select('id', 'email', 'name', 'user_id_origin', 'role') // Added 'role' to select
                                ->whereIn('role', [2, 7]) // Get users with role 2 (admin) or role 7 (admins_comptable)
                                ->where('email', '!=', null)
                                ->whereHas('projets', function($query) use ($projet_id) {
                                    $query->where('projet_id', $projet_id);
                                })
                                ->get();

                            // Send emails to all admin and comptable users
                            if($admins->count() > 0){
                                foreach($admins as $admin){
                                    if($admin->email!=null){
                                        try {
                                        $to_email = $admin->email;

                                        $data = [
                                            'adminName' => $admin->name,
                                            'reservationCode' => $avance->reservation->code_reservation,
                                            'avanceNumero' => $avance->num_recu,
                                            'montantAvance' => number_format($request->montant, 2, ',', ' '),
                                            'validationLink' => env('FRONTEND_URL'). '/ventes/reservations/'.$request->reservation_id,
                                            'dateCreation' => Carbon::now()->format('d/m/Y à H:i'),
                                            'createdBy' => $userAuth->first()->name ?? $userAuth->name ?? 'Un commercial',
                                            'projetName' => $avance->reservation->projet->nom ?? 'Non spécifié'
                                        ];

                                        Mail::send('emails.demande_validation_avance', $data, function ($message) use ($to_email, $avance) {
                                            $message->to($to_email)
                                                ->subject('Demande Validation Avance : '.$avance->num_recu.' - Réservation : '.$avance->reservation->code_reservation);
                                            $message->from(env('MAIL_USERNAME'), 'Tracimo ');
                                        });

                                        Log::info("Email de demande de validation avance envoyé à l'admin: {$admin->email}");

                                        } catch (\Exception $e) {
                                            Log::error("Échec de l'envoi de l'email à l'admin {$admin->email}: " . $e->getMessage());
                                        }
                                    }

                                }
                            }

                            // Create notifications for each admin and comptable user
                            Config::set('broadcasting.default', 'pusher_notify');

                            foreach($admins as $admin) {
                                // Set role based on user type
                                // If user is admin (role 2), set role to ADMIN value
                                // If user is comptable (role 7), set role to null
                                $roleValue = ($admin->role == 2) ? RoleEnum::ADMIN->value : RoleEnum::COMPTABLE->value;

                                $data_notif = [
                                    'lien' => '/ventes/reservations/'.$request->reservation_id,
                                    'date' => Carbon::now(),
                                    'type' => 7,
                                    'user_id' => $admin->user_id_origin, // Send to specific user
                                    'description' => 'Validation paiement',
                                    'role' => $roleValue, // Set role based on user type
                                    'projet_id' => $avance->reservation->projet_id,
                                    'avance_id' => $avance->id,
                                    'reservation_id' => $request->reservation_id,
                                    'visite_id' => null,
                                    'prospect_id' => null,
                                    'bien_id' => null,
                                    'traite_appel_id' => null
                                ];

                                $notif_helper = new NotificationHelper();
                                $notif_helper->storeNotification(new \Illuminate\Http\Request($data_notif));

                                // Broadcast to specific user's channel
                                broadcast(new NotificationEvent($admin->user_id_origin));
                            }

                            Config::set('broadcasting.default', 'pusher_notify');
                            //2 traitement avance (update menu counter for pending validations)
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



                       if (RoleHelper::AdminSup() || RoleHelper::AgentAdmin()) {
                            // Check if user can store encaissement based on conditions
                            $canStoreEncaissement = false;

                            if (RoleHelper::AdminSup()) {
                                // Admin can always store encaissement
                                $canStoreEncaissement = true;
                            }elseif (RoleHelper::AgentAdmin()) {
                                // Agent Admin can store encaissement in two scenarios:
                                // 1. With reservation: when avance_with_reservation is true AND prix == prix_final
                                // 2. Without reservation: when avance_with_reservation is false (or not present)

                                if ($request->avance_with_reservation == true && $request->prix == $request->prix_final) {
                                    // Case 1: Store with reservation conditions met
                                    $canStoreEncaissement = true;
                                } elseif ($request->avance_with_reservation == false || !$request->has('avance_with_reservation')) {
                                    // Case 2: Store without reservation (regular avance)
                                    $canStoreEncaissement = true;
                                } else {
                                    $canStoreEncaissement = false;
                                }
                            }

                            // Store encaissement if conditions are met
                            if ($canStoreEncaissement && $request->date_encaissement != null) {
                                $encaiss = new Encaissement();
                                $encaiss->setConnection('temp');
                                $encaiss->reservation_id = $request->reservation_id;
                                $encaiss->bien_id = $reservation->bien->id;
                                $encaiss->type_encaissement = 1; //Avances
                                $encaiss->montant = $avance->montant;
                                $encaiss->avance_id = $avance->id;
                                $encaiss->date_reglement = $avance->created_at;
                                $encaiss->date_encaissement = $request->date_encaissement;
                                $encaiss->user_id_valider = $userAuth->value('id');
                                //calcul du tva collecte
                                if ($encaiss->save()) {
                                    //get tva du bien
                                    if ($bien->Bien_Tva != null) {
                                        $data = [
                                            'montant' => $avance->montant,
                                            'prix' => $bien->prix,
                                            'qp_terrain_valeur' => $bien->Bien_Tva->qp_terrain_valeur,
                                            'ancien_tva_collectes' => $bien->tva_collectes,
                                            'tva_collectes_sum_tva_a_payer' => $bien->tva_collectes_sum_tva_a_payer,
                                            'tva_bien' => $bien->Bien_Tva->tva,
                                            'reservation_id' => $avance->reservation_id,
                                            'bien_id' => $bien->id,
                                            'type' => 'avances',
                                            'encaissement_id' => $encaiss->id
                                        ];
                                        $this->store_tva_collecte($request->merge($data));
                                    }
                                }
                            }
                        }
                         }
                        //actualiser avances
                        Config::set('broadcasting.default', 'pusher_list');
                        $reservationId = $request->reservation_id;

                        // Broadcast event to all users subscribed to this reservation
                        broadcast(new AvancesEvent($reservationId,null));
                        // Get all users who should receive this update (admins, managers, etc.)
                        $usersToNotify = User::on('temp') // Adjust roles as needed
                            ->where('id', '!=', $userAuth->value('id')) // Don't notify the current user
                            ->where('role','!=',8)
                            ->whereHas('projets', function($query) use ($projet_id) {
                                        $query->where('projet_id', $projet_id);
                                    })
                            ->get();
                            // Broadcast to each user's specific channel
                        foreach ($usersToNotify as $user) {
                        event(new AvancesEvent(null,$user->user_id_origin)); }// Pass user ID for specific channel


                        //actualiser menu validation avance
                        Config::set('broadcasting.default', 'pusher_notify');
                        broadcast(new NotifMenuEvent(2));

                    return response()->json(['avance' => $avance], 200);


                } catch (\Exception $e) {
                    // Rollback transaction on any error
                    DB::connection('temp')->rollBack();

                    Log::error('Error storing avance: ' . $e->getMessage());
                    Log::error('Trace: ' . $e->getTraceAsString());

                    return response()->json([
                        'error' => 'Failed to store avance',
                        'message' => $e->getMessage()
                    ], 500);
                }
        }else{
        return response()->json(['error' => 'Unauthorized'], 201);
        }
    }

    private function createStatutClientForAvance($avance, $userAuth)
    {
        try {
            // Get all aquereurs for this reservation
            $aquereurs = Aquereur::on('temp')
                ->where('reservation_id', $avance->reservation_id)
                ->with('client')
                ->get();

            if ($aquereurs->isEmpty()) {
                \Log::warning('No aquereurs found for avance ID: ' . $avance->id . ', Reservation ID: ' . $avance->reservation_id);
                return;
            }

            // Get reservation details
            $reservation = Reservation::on('temp')->find($avance->reservation_id);
            if (!$reservation) {
                \Log::warning('Reservation not found for avance ID: ' . $avance->id);
                return;
            }

            foreach ($aquereurs as $aquereur) {
                // Check if StatutClient already exists for this avance and client
                $existingStatut = StatutClient::on('temp')
                    ->where('avance_id', $avance->id)
                    ->where('client_id', $aquereur->client_id)
                    ->exists();

                if (!$existingStatut) {
                    $statutClient = new StatutClient();
                    $statutClient->setConnection('temp');
                    $statutClient->visite_id = null;
                    $statutClient->client_id = $aquereur->client_id;
                    $statutClient->statut = '1'; // Statut code for avance payment
                    $statutClient->avance_id = $avance->id;
                    $statutClient->reservation_id = $avance->reservation_id;
                    $statutClient->date_traitement = now();
                    $statutClient->user_id_traite = $userAuth->value('id');

                    // Build comment
                    $comment = 'Paiement Avance montant: ' . number_format($avance->montant, 2) .
                            ' DH - Réservation code: ' . $reservation->code_reservation;

                    // Add payment reference if available
                    if ($avance->numero_paiement) {
                        $comment .= ' - Ref paiement: ' . $avance->numero_paiement;
                    }

                    // Add check/echeance info for checks
                    if ($avance->mode_paiement == 2 || $avance->mode_paiement == 3 || $avance->mode_paiement == 4) {
                        if ($avance->echeance) {
                            $comment .= ' - Échéance: ' . Carbon::parse($avance->echeance)->format('d/m/Y');
                        }
                    }

                    $statutClient->commentaire = $comment;

                    $statutClient->save();

                    \Log::info('StatutClient created for avance payment - Client ID: ' . $aquereur->client_id .
                            ', Avance ID: ' . $avance->id .
                            ', Montant: ' . $avance->montant);
                } else {
                    \Log::info('StatutClient already exists for avance ID: ' . $avance->id . ' and client ID: ' . $aquereur->client_id);
                }
            }

        } catch (\Exception $e) {
            \Log::error('Failed to create StatutClient for avance payment: ' . $e->getMessage());
            // Don't throw error to avoid breaking the avance creation
        }
    }
    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (RoleHelper::ACSup_RC() || RoleHelper::AgentAdmin() || RoleHelper::AgentAdmin()) {
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

        if (RoleHelper::ACSup() || RoleHelper::AgentAdmin() || RoleHelper::AgentAdmin()||RoleHelper::Notaire()||RoleHelper::RespoLivraison()||RoleHelper::RespoCommercial()) {
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

                                       // Utiliser FichierHelper
                                    FichierHelper::ajouter_fichier(
                                        $file,
                                        $societe->raison_sociale_concatene,
                                        $societe->id,
                                        'paiements/' . $reservation->code_reservation,
                                        $Myfile
                                    );

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

                if (RoleHelper::AdminSup() || RoleHelper::AgentAdmin() || RoleHelper::AgentAdmin()) {
                    //rejete et remodifier par admin
                    if ($avance->statut == StatutReservationEnum::Refusé->value) {
                        $avance->statut = StatutReservationEnum::Validé->value;
                    }
                }
                //si commercial  si deja rejete on fait statut =>en cours
                elseif (RoleHelper::Com()||RoleHelper::Notaire()||RoleHelper::RespoLivraison()||RoleHelper::RespoCommercial()) {

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
                $projet_id = $avance->reservation->projet_id;

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

                    if(RoleHelper::AdminSup() || RoleHelper::AgentAdmin() || RoleHelper::AgentAdmin()){
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
                    Config::set('broadcasting.default', 'pusher_notify');
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
                            broadcast(new NotifMenuEvent(5));
                        }



                    }
                    //si commercial==> demande validation du paiement
                       //if($avance->reservation->statut == StatutReservationEnum::Validé->value){
                          if ((RoleHelper::Com()||RoleHelper::Notaire()||RoleHelper::RespoLivraison()||RoleHelper::RespoCommercial()) && $request->montant > 0) {


                                // Get all admin and comptable users for this project
                                $admins = User::on('temp')
                                    ->select('id', 'email', 'name', 'user_id_origin', 'role')
                                    ->whereIn('role', [2, 7]) // Get users with role 2 (admin) or role 7 (admins_comptable)
                                    ->where('email', '!=', null)
                                    ->whereHas('projets', function($query) use ($projet_id) {
                                        $query->where('projet_id', $projet_id);
                                    })
                                    ->get();

                                // Send emails to all admin and comptable users
                                if($admins->count() > 0){
                                    foreach($admins as $admin){
                                        try {
                                            $to_email = $admin->email;

                                            $data = [
                                                'adminName' => $admin->name,
                                                'reservationCode' => $avance->reservation->code_reservation,
                                                'avanceNumero' => $avance->num_recu,
                                                'montantAvance' => number_format($request->montant, 2, ',', ' '),
                                                'validationLink' => env('FRONTEND_URL').'/ventes/reservations/'.$request->reservation_id,
                                                'dateCreation' => Carbon::now()->format('d/m/Y à H:i'),
                                                'createdBy' => $userAuth->first()->name ?? $userAuth->name ?? 'Un commercial',
                                                'projetName' => $avance->reservation->projet->nom ?? 'Non spécifié'
                                            ];

                                            Mail::send('emails.demande_validation_avance', $data, function ($message) use ($to_email, $avance) {
                                                $message->to($to_email)
                                                    ->subject('Demande Validation Avance : '.$avance->num_recu.' - Réservation : '.$avance->reservation->code_reservation);
                                                $message->from(env('MAIL_USERNAME'), 'Tracimo ');
                                            });

                                            Log::info("Email de demande de validation avance envoyé à l'admin/comptable: {$admin->email}");

                                        } catch (\Exception $e) {
                                            Log::error("Échec de l'envoi de l'email à l'admin/comptable {$admin->email}: " . $e->getMessage());
                                        }
                                    }
                                }

                                // Create notifications for each admin and comptable user
                                Config::set('broadcasting.default', 'pusher_notify');

                                foreach($admins as $admin) {
                                    // Set role based on user type
                                    // If user is admin (role 2), set role to ADMIN value
                                    // If user is comptable (role 7), set role to null
                                    $roleValue = ($admin->role == 2) ? RoleEnum::ADMIN->value : RoleEnum::COMPTABLE->value;

                                    $data_notif = [
                                        'lien' => '/ventes/reservations/'. $avance->reservation_id,
                                        'date' => Carbon::now(),
                                        'type' => 7,
                                        'user_id' => $admin->user_id_origin, // Send to specific user
                                        'description' => 'Validation paiement',
                                        'role' => $roleValue, // Set role based on user type
                                        'projet_id' => $avance->reservation->projet_id,
                                        'avance_id' => $avance->id,
                                        'reservation_id' => $avance->reservation_id,
                                        'visite_id' => null,
                                        'prospect_id' => null,
                                        'bien_id' => null,
                                        'traite_appel_id' => null
                                    ];

                                    $notif_helper = new NotificationHelper();
                                    $notif_helper->storeNotification(new \Illuminate\Http\Request($data_notif));

                                    // Broadcast to specific user's channel
                                    broadcast(new NotificationEvent($admin->user_id_origin));
                                }
                                //2 traitement avance (update menu counter for pending validations)
                                broadcast(new NotifMenuEvent(2));
                            }
                      //  }

                            //actualiser avances
                         Config::set('broadcasting.default', 'pusher_list');
                            $reservationId = $request->reservation_id;
                            // Broadcast event to all users subscribed to this reservation
                            broadcast(new AvancesEvent($reservationId,null));
                            // Get all users who should receive this update (admins, managers, etc.)
                            //->whereIn('role', [2, 3])
                            $usersToNotify = User::on('temp')// Adjust roles as needed
                                ->where('id', '!=', $userAuth->value('id')) // Don't notify the current user
                               ->where('role','!=',8)
                                ->whereHas('projets', function($query) use ($projet_id) {
                                            $query->where('projet_id', $projet_id);
                                        })
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
        if (RoleHelper::ACSup_RC() || RoleHelper::AgentAdmin() || RoleHelper::AgentAdmin()) {
            DatabaseHelper::config();
            $avance = Avance::on('temp')->findOrFail($id);
            $dd=$avance;
            $projet_id=$avance->reservation->projet_id;
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
            Config::set('broadcasting.default', 'pusher_list');
                $reservationId = $avance->reservation_id;
                // Broadcast event to all users subscribed to this reservation
                broadcast(new AvancesEvent($reservationId,null));
                // Get all users who should receive this update (admins, managers, etc.)

                $user = Auth::user();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                $usersToNotify = User::on('temp') // Adjust roles as needed
                    ->where('id', '!=', $userAuth->value('id')) // Don't notify the current user
                    ->where('role','!=',8)
                    ->whereHas('projets', function($query) use ($projet_id) {
                                        $query->where('projet_id', $projet_id);
                                    })
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
        if (RoleHelper::ACSup_RC() || RoleHelper::AgentAdmin() || RoleHelper::AgentAdmin()) {
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
        if (RoleHelper::ACSup_RC() || RoleHelper::AgentAdmin() || RoleHelper::AgentAdmin()) {
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

        if (Auth::guard('api')->check() && (RoleHelper::ACSup_RC() || RoleHelper::AgentAdmin() || RoleHelper::AgentAdmin()||RoleHelper::Comptable())) {
            DatabaseHelper::Config();

            if (RoleHelper::AdminSup() || RoleHelper::AgentAdmin() || RoleHelper::AgentAdmin()||RoleHelper::Comptable()) {
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

            } else if (RoleHelper::Com()||RoleHelper::RespoCommercial()) {
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

        if (Auth::guard('api')->check() && (RoleHelper::ACSup_RC() || RoleHelper::AgentAdmin() || RoleHelper::AgentAdmin()||RoleHelper::Comptable())) {
            DatabaseHelper::Config();
            $size = $request->input('size', config('app.default_item_number_perpage'));
            $page = $request->input('page', 1);

            if (RoleHelper::AdminSup() || RoleHelper::AgentAdmin() || RoleHelper::AgentAdmin()||RoleHelper::Comptable()) {
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
            if (RoleHelper::Com()||RoleHelper::RespoCommercial()) {
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

        if (Auth::guard('api')->check() && (RoleHelper::ACSup_RC() || RoleHelper::AgentAdmin() || RoleHelper::AgentAdmin()||RoleHelper::Comptable())) {
            DatabaseHelper::Config();


            if (RoleHelper::AdminSup() || RoleHelper::AgentAdmin() || RoleHelper::AgentAdmin()||RoleHelper::Comptable()) {
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
            if (RoleHelper::Com()||RoleHelper::RespoCommercial()) {
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
