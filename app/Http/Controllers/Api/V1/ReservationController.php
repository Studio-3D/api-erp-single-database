<?php

namespace App\Http\Controllers\Api\V1;

use App\Enum\RoleEnum;
use App\Enum\StatutReservationEnum;
use App\Events\NotificationEvent;
use App\Events\NotifMenuEvent;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Bien_Helper;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\NotificationHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreAquereurRequest;
use App\Http\Requests\StoreAvanceRequest;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\StorePiecesJointeRequest;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\UpdateReservationRequest;
use App\Models\Aquereur;
use App\Models\Avance;
use App\Models\Bien;
use App\Models\Client;
use App\Models\HistoReservation;
use App\Models\PiecesJointe;
use App\Models\Reservation;
use App\Models\Societe;
use App\Models\StatutReservation;
use App\Models\User;
use App\Models\Remboursement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use \NumberFormatter;
use App\Models\Desistement;
class ReservationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, $projet_id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage'));
            $page = $request->input('page', 1);

            $avances = Avance::on('temp')->select('reservation_id', DB::raw('SUM(avances.montant) as sum_avances'))
                ->groupby('reservation_id');

            $reservations = Reservation::on('temp')->with('desistement_att_validation_rejete', 'last_statut', 'first_avance')
                ->joinSub($avances, 'avances_req', function ($join) {
                    $join->on('avances_req.reservation_id', '=', 'reservations.id');
                })
                ->select('reservations.*', 'avances_req.sum_avances')
                ->orderBy('reservations.created_at', 'desc')
                ->where('reservations.projet_id', $projet_id)
                ->where('reservations.etat', 1)
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json(['reservations' => $reservations], 200);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function indexByProjet(Request $request, $projet_id)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', config('app.default_item_number_perpage')); // Default size if not provided
            $page = $request->input('page', 1); // Default page if not provided

            DatabaseHelper::Config();


            $query = Reservation::on('temp')->withSum('avances','montant')->with('desistement_att_validation_rejete','last_statut','first_avance')
            ->orderBy('created_at', 'desc')
                ->where('projet_id', $projet_id)
                ->where('etat', 1);
            // Optional filters (Add more if needed)
            if ($request->filled('code_reservation')) {
                $query->where('code_reservation', 'like', '%' . $request->input('code_reservation') . '%');
            }
            if ($request->filled('date_reservation')) {
                $query->where('date_reservation', $request->input('date_reservation'));
            }
            if ($request->filled('client_id')) {
                $query->whereHas('Aquereurs.client', function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('id', $request->input('client_id'));
                    });
                });
            }
            if ($request->filled('client')) {
                $query->whereHas('Aquereurs.client', function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('nom', 'like', '%' . $request->input('client') . '%')
                            ->orWhere('prenom', 'like', '%' . $request->input('client') . '%');
                    });
                });
            }
            if ($request->filled('cc')) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->input('cc') . '%')
                            ->orWhere('prenom', 'like', '%' . $request->input('cc') . '%');
                    });
                });
            }
            if ($request->filled('bien')) {
                $query->whereHas('bien', function ($q) use ($request) {
                    $q->where('propriete_dite_bien', 'like', '%' . $request->input('bien') . '%');
                });
            }

            if ($request->filled('date_start')) {
                $start = Carbon::parse($request->input('date_start'));
                $query->whereDate('reservations.date_reservation','>=', $start);
            }
            if ($request->filled('date_end')) {
                $end = Carbon::parse($request->input('date_end'));
                $query->whereDate('reservations.date_reservation','<=', $end);
            }

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                // Paginate if size and page are valid
                $reservations = $query->orderBy('reservations.created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Add pagination info
                $pagination = [
                    'currentPage' => $reservations->currentPage(),
                    'totalItems' => $reservations->total(),
                    'totalPages' => $reservations->lastPage(),
                ];

                $reservations = $reservations->items();

                return response()->json([
                    'data' => $reservations,
                    'pagination' => $pagination,
                ], 200);
            } else {
                // Return all results if pagination parameters are not provided or invalid
                $reservations = $query->orderBy('reservations.created_at', 'desc')
                    ->get();

                return response()->json(['reservations' => $reservations], 200);
            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function get_dossiers(Request $request, $projet_id, $dos_id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();

            $avances = Avance::on('temp')->select('reservation_id', DB::raw('SUM(avances.montant) as sum_avances'))
                ->groupby('reservation_id');

            $reservations = Reservation::on('temp')
                ->joinSub($avances, 'avances_req', function ($join) {
                    $join->on('avances_req.reservation_id', '=', 'reservations.id');
                })
                ->select('reservations.*', 'avances_req.sum_avances')
                ->whereColumn('sum_avances', '<', 'reservations.prix')
                ->where('reservations.id', '!=', $dos_id)
                ->orderBy('reservations.created_at', 'desc')
                ->where('reservations.etat', 1)
                ->where('reservations.projet_id', $projet_id)
                ->get();

            return response()->json(['reservations' => $reservations], 200);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /*public function get_reservations_rejets(Request $request, $projet_id)
    {

        if (Auth::guard('api')->check() && RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);
            $avances = Avance::on('temp')->select('reservation_id', DB::raw('SUM(avances.montant) as sum_avances'))
                ->groupby('reservation_id');

            if (RoleHelper::AdminSup()) {
                //ADMIN
                $reservations = Reservation::on('temp')->with('last_statut')
                    ->joinSub($avances, 'avances_req', function ($join) {
                        $join->on('avances_req.reservation_id', '=', 'reservations.id');
                    })
                    ->select('reservations.*', 'avances_req.sum_avances')
                    ->orderBy('reservations.created_at', 'desc')
                    ->where('reservations.etat', 1)
                    ->where('reservations.statut', 2)
                    ->where('reservations.projet_id', $projet_id)
                    ->paginate($perPage, ['*'], 'page', $page);

            } else
            if (RoleHelper::Com()) {
                $user = Auth::user();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                $reservations = Reservation::on('temp')->with('last_statut')
                    ->joinSub($avances, 'avances_req', function ($join) {
                        $join->on('avances_req.reservation_id', '=', 'reservations.id');
                    })
                    ->select('reservations.*', 'avances_req.sum_avances')
                    ->orderBy('reservations.created_at', 'desc')
                    ->where('reservations.etat', 1)
                    ->where('reservations.statut', 2)
                    ->where('reservations.user_id', $userAuth->value('id'))
                    ->where('reservations.projet_id', $projet_id)
                    ->paginate($perPage, ['*'], 'page', $page);
            }
            return response()->json(['reservations' => $reservations], 200);

        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }*/
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
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            //test si le user connecte celui qui a  fait la proposition /etat du bien
            if ($request->bien_id != null) {
                $bien_prop = Bien::on('temp')->findorfail($request->bien_id);

                if ($bien_prop->etat == 'ENCOURS_DE_PROPOSITION' && $bien_prop->is_proposed->user_id != $userAuth->value('user_id_origin')) {
                    return response()->json(['error_33' => 'le bien choisi :' . $bien_prop->propriete_dite_bien . ' est en cours de proposition  par : ' . $bien_prop->is_proposed->user->name . ' ' . $bien_prop->is_proposed->user->prenom], 333);
                }

            }
            $reservation = new Reservation();
            $reservation->setConnection('temp');
            $reservation->nb_acquereurs = $request->nb_acquereurs;
            $reservation->code_reservation = $request->code_reservation;
            $reservation->prix = $request->prix;
            $reservation->mode_financement = $request->mode_financement;
            $reservation->date_reservation = $request->date_reservation;
            $reservation->commentaire = $request->input('commentaire') == "null" ? null : $request->input('commentaire');
            $reservation->visite_id = $request->visite_id;
            $reservation->prix_remise = $request->prix_remise;
            $numberToWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
            $prix_remise_lettre = $numberToWords->format($request->prix_remise);
            $reservation->prix_remise_lettre = $prix_remise_lettre;
            $reservation->prix_forfetaire = $request->prix_forfetaire;
            $prix_forfetaire_lettre = $numberToWords->format($request->prix_forfetaire);
            $reservation->prix_forfetaire_lettre = $prix_forfetaire_lettre;
            $reservation->bien_id = $request->bien_id;
            $reservation->projet_id = $request->projet_id;
            $reservation->user_id = $userAuth->value('id');
            if (RoleHelper::AdminSup()) {
                $reservation->statut = StatutReservationEnum::Validé->value;
            }
            if (RoleHelper::Com()) {
                $reservation->statut = StatutReservationEnum::En_Attente->value;
            }

            if ($reservation->save()) {
                $bienController = new BienController();
                $bienController->reserverBien($reservation->bien_id, null, $reservation->id);
                //si statut=1 ==>store it to table statutReservation
                if ($reservation->statut == StatutReservationEnum::Validé->value) {
                    $statut_R = new StatutReservation();
                    $statut_R->setConnection('temp');
                    $statut_R->reservation_id = $reservation->id;
                    $statut_R->statut = StatutReservationEnum::Validé->value;
                    $statut_R->date_validation = Carbon::now();
                    $statut_R->user_id_valider = $userAuth->value('id');
                    $statut_R->save();
                }
                if (RoleHelper::Com()) {
                    Config::set('broadcasting.default', 'pusher_3');
                    //notifiction to admin de valider dossier d reservation user_id=>null
                    $data_notif = [
                        //'lien' => '/reservations/show/' . $reservation->id,
                        'lien' => '/validation/reservations/attente',
                        'date' => Carbon::now(),
                        'type' => 6,
                        'role' => RoleEnum::ADMIN->value,
                        'description' => 'DEMANDE VALIDATION RESERVATION',
                        'projet_id' => $reservation->projet_id,
                        'reservation_id' => $reservation->id,

                    ];
                    $notif_helper = new NotificationHelper();
                    $notif_helper->storeNotification($request->merge($data_notif));
                    broadcast(new NotificationEvent($reservation->id));
                    Config::set('broadcasting.default', 'pusher_5');
                    //1 traitement reservation
                    broadcast(new NotifMenuEvent(1));
                }
                $clientController = new ClientController();
                $clientRequest = new StoreClientRequest();
                $aquereurController = new AquereurController();
                $aquereurRequest = new StoreAquereurRequest();
                if ($request->origin == 'visite') {
                    $client_exist = Client::on('temp')->where('prospect_id', $request->prospect_id)->orderBy('created_at', 'DESC')->first();
                    if ($client_exist != null) {
                        $clientData = $client_exist;
                    } else {
                        $dataClient = [
                            'cin' => $request->cin,
                            'nom' => $request->nom,
                            'prenom' => $request->prenom,
                            'telephone_num1' => $request->telephone_num1,
                            'telephone_num2' => $request->telephone_num2,
                            'notifie' => $request->notifie,
                            'prospect_id' => $request->prospect_id,
                            'civilite' => $request->civilite,
                            'situation_familliale' => $request->situation_familliale,
                            'type_client' => 1,
                        ];
                        $clientRequest->merge($dataClient);
                        $clientData = $clientController->store($clientRequest);
                    }
                    $dataAquereur = [
                        'pourcentage' => 100,
                        'client_id' => $clientData->id,
                        'reservation_id' => $reservation->id,
                    ];
                    $aquereurRequest->merge($dataAquereur);
                    $aquereurController->store($aquereurRequest);
                } else {
                    // Parse the string back to an array
                    $dataArray_clients = json_decode($request->input('clients'), true);
                    $dataArrayString = $request->input('oldClients', '[]');

                    $dataArray_oldClients = json_decode($dataArrayString, true); // Ensure it's an array

                    // Check if it's an array and not null

                    if ($dataArray_clients) {
                        foreach ($dataArray_clients as $clientInfo) {
                            $clientRequest->merge($clientInfo);
                            $clientData = $clientController->store($clientRequest);
                            $dataAquereur = [
                                'pourcentage' => $clientInfo['pourcentage'],
                                'client_id' => $clientData->id,
                                'reservation_id' => $reservation->id,
                            ];
                            $aquereurRequest->merge($dataAquereur);
                            $aquereurController->store($aquereurRequest);
                        }
                    }
                    if ($dataArray_oldClients) {
                        foreach ($dataArray_oldClients as $clientInfo) {
                            $dataAquereur = [
                                'pourcentage' => $clientInfo['pourcentage1'],
                                'client_id' => $clientInfo['id'],
                                'reservation_id' => $reservation->id,
                            ];
                            $aquereurRequest->merge($dataAquereur);
                            $aquereurController->store($aquereurRequest);
                        }
                    }

                }
                $avanceController = new AvanceController();
                $avanceRequest = new StoreAvanceRequest();
                $inWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
                $mnt_lettre = $inWords->format($request->avance);
                $dataAvance = [
                    //addedd
                    'avance_with_reservation' => true,
                    'desistement_id' => null,
                    'dossier_id_transfert' => null,
                    /////
                    'sr' => $request->sr,
                    'type_encaissement' => 1,
                    'montant' => $request->avance,
                    'mode_paiement' => $request->mode_paiement,
                    // 'mode_transfert' => null,
                    'numero_paiement' => $request->numero_paiement,
                    'date_reglement' => $request->date_reglement,
                    'echeance' => $request->echeance,
                    'banque_id' => $request->banque_id,
                    'montant_par_lettre' => $mnt_lettre,
                    'reservation_id' => $reservation->id,
                    'commentaireAvance' => $request->commentaireAvance,
                    'num_remise' => $request->num_remise,
                    'date_encaissement' => $request->date_encaissement,
                    'files_avance' => $request->file('files_avance'),
                ];
                $avanceRequest->merge($dataAvance);
                $avanceController->store($avanceRequest);
                //****store piece jointe***

                //////storer les pieces jointe de résérvation
                if ($request->file('files_reservation')) {
                    foreach ($request->file('files_reservation') as $file) {
                        $piecesJointeController = new PiecesJointeController();
                        $pieceJointeRequest = new StorePiecesJointeRequest();
                        $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
                        $societe = Societe::findOrfail($user_societes->societe_id);

                        // Récupérer le nom du fichier
                        $fileName = $file->getClientOriginalName();
                        $Myfile = $fileName;
                        $directory = public_path('Docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/reservations');
                        File::makeDirectory($directory, 0755, true, true);
                        $file->move($directory, $Myfile);
                        $fileType = $file->getClientOriginalExtension();
                        $datapieceJointe = [
                            'fichier' => $Myfile,
                            'type' => $fileType,
                            'reservation_id' => $reservation->id,
                            'active' => 1,

                        ];

                        $pieceJointeRequest->merge($datapieceJointe);
                        $piecesJointeController->store($pieceJointeRequest);
                    }
                }

            }
            return response()->json(['reservation' => $reservation], 200);

        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Display the specified resource.
     */

    public function search_reservation_by_code($code)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $reservation = Reservation::on('temp')->where('code_reservation', $code)->where('etat', 1)
                ->get()->first();
            return response()->json(['reservation' => $reservation]);
        }
    }
    /*public function info_reservation($id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $reservation = Reservation::on('temp')->with('remboursement_dd_with_transfert')->findOrFail($id);
            $statut = $reservation->statut;
            $nb_histo = count($reservation->historiques);
            $etat = $reservation->etat;
            $code = $reservation->code_reservation;
            $code_desistement = $reservation->code_desistement;
            $prix = $reservation->prix;
            $user_id = $reservation->user_id;
            if ($reservation->etat > 1) {
                $nb_aq = count($reservation->aquereurs_ancien);
                $nb_pj = count($reservation->piece_jointe_desiste);
            } else {
                $nb_aq = count($reservation->aquereurs);
                $nb_pj = count($reservation->piece_jointe);
            }
            $nb_av = count($reservation->avances);
            return response()->json(['code_res' => $code, 'code_desistement' => $code_desistement, 'prix' => $prix, 'nb_aquer' => $nb_aq, 'nb_av' => $nb_av, 'nb_pj' => $nb_pj, 'etat' => $etat, 'transfert' => $reservation->remboursement_dd_with_transfert, 'statut' => $statut, 'user_id' => $user_id, 'nb_histo' => $nb_histo], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }*/
    public function info_reservation($id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $reservation = Reservation::on('temp')->with('remboursement_dd_with_transfert','compromis_vente')->findOrFail($id);
            $statut=$reservation->statut;
            $nb_histo=count($reservation->historiques);
            $etat=$reservation->etat;
            $code=$reservation->code_reservation;
            $code_desistement=$reservation->code_desistement;
            $prix=$reservation->prix;
            $user_id=$reservation->user_id;
            if($reservation->etat>1){
               $nb_aq=count($reservation->aquereurs_ancien);
               $nb_pj=count($reservation->piece_jointe_desiste);
            }else{
               $nb_aq=count($reservation->aquereurs);
               $nb_pj=count($reservation->piece_jointe);
            }
            $nb_av=count($reservation->avances);

            $sum_avances=0;
             //si dossier desiste
             if($reservation->etat>1){
                foreach($reservation->avances_desist as $av){
                    //avance validé
                    if($av->statut==StatutReservationEnum::Validé->value){
                        $sum_avances+=$av->montant;
                    }
                 }

             }else{
                foreach($reservation->avances as $av){
                    //avance validé
                    if($av->statut==StatutReservationEnum::Validé->value){
                        $sum_avances+=$av->montant;
                    }
                 }
             }

            return response()->json(['code_res' => $code,'code_desistement' => $code_desistement,'prix'=>$prix,'nb_aquer'=>$nb_aq,'nb_av'=>$nb_av,'nb_pj'=>$nb_pj,'etat'=>$etat,'transfert'=>$reservation->remboursement_dd_with_transfert,'statut'=>$statut,'user_id'=>$user_id,'nb_histo'=>$nb_histo
            ,'sum_avances'=>$sum_avances], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function show($id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $reservation = Reservation::on('temp')->with('desistements_ancien')->findOrFail($id);

            //get nom propriete _dite_bien concat
            $propriete = null;
            if ($reservation->bien_id != null) {
                $bien = new VisiteController();
                $propriete = $bien->get_propriete_bien_concat($reservation->bien_id);
            }
            $sum_avances_valides = 0;
           // $sum_avances = 0;
            //si dossier desiste
            if ($reservation->etat > 1) {
                foreach ($reservation->avances_desist as $av) {
                    //avance validé
                    if ($av->statut == StatutReservationEnum::Validé->value) {
                        $sum_avances_valides += $av->montant;
                    }
                    /*//tous les avances !=refuse
                if($av->statut!=StatutReservationEnum::REFUSER->value){
                $sum_avances+=$av->montant;
                }*/
                }
               // $count_avances = Avance::on('temp')->where('reservation_id', $id)->onlyTrashed()->count('id');

            } else {
                foreach ($reservation->avances_valides as $av) {
                        $sum_avances_valides += $av->montant;

                    /*//tous les avances !=refuse
                if($av->statut!=StatutReservationEnum::REFUSER->value){
                $sum_avances+=$av->montant;
                }*/
                }
               // $count_avances = Avance::on('temp')->where('reservation_id', $id)->count('id');

            }

            return response()->json(['reservation' => $reservation, 'propriete_dite_bien' => $propriete, 'sum_avances_valides' => $sum_avances_valides], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }


    public function get_pj_res($id, Request $request)
    {
        if (Auth::guard('api')->check()) {

            DatabaseHelper::Config();
            $pj = PiecesJointe::on('temp')->where('reservation_id', $id)->get();
            return response()->json([
                'data' =>  $pj,


            ], 200);

        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }
    public function getReservationssByProjet($projet_id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $avances = Avance::on('temp')->select('reservation_id', DB::raw('SUM(avances.montant) as sum_avances'))
                ->groupby('reservation_id');

            $reservations = Reservation::on('temp')->with('desistement_att_validation_rejete')
                ->joinSub($avances, 'avances_req', function ($join) {
                    $join->on('avances_req.reservation_id', '=', 'reservations.id');
                })
                ->select('reservations.*', 'avances_req.sum_avances')
                ->orderBy('reservations.created_at', 'desc')
                ->where('reservations.etat', 1)
                ->where('reservations.projet_id', $projet_id)
                ->get();

            return response()->json(['reservations' => $reservations], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

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
    public function update(UpdateReservationRequest $request, $id)
    {
        //return response()->json(['reservation' => $request->all(),$request->input('bien_id'),$request->bien_id], 404);

        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $reservation = Reservation::on('temp')->findOrFail($id);
            $old_bien_id = $reservation->bien_id;
            //test si le user connecte celui qui a  fait la proposition /etat du bien
            if ($old_bien_id != $request->input('bien_id')) {
                $bien_prop = Bien::on('temp')->findorfail($request->input('bien_id'));

                if ($bien_prop->etat == 'ENCOURS_DE_PROPOSITION' && $bien_prop->is_proposed->user_id != $userAuth->value('user_id_origin')) {
                    return response()->json(['error_33' => 'le bien choisi :' . $bien_prop->propriete_dite_bien . ' est en cours de proposition  par : ' . $bien_prop->is_proposed->user->name . ' ' . $bien_prop->is_proposed->user->prenom], 333);
                }

            }
            $reservation->setConnection('temp');
            $reservation->nb_acquereurs = $request->input('nb_acquereurs');
            $reservation->code_reservation = $request->input('code_reservation');
            $reservation->prix = $request->input('prix');
            $reservation->mode_financement = $request->mode_financement;
            $reservation->date_reservation = $request->input('date_reservation');
            $reservation->commentaire = $request->input('commentaire') == "null" ? null : $request->input('commentaire');
            $reservation->prix_remise = $request->input('prix_remise');
            $numberToWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
            $prix_remise_lettre = $numberToWords->format($request->input('prix_remise'));
            $reservation->prix_remise_lettre = $prix_remise_lettre;
            $reservation->prix_forfetaire = $request->input('prix_forfetaire');
            $prix_forfetaire_lettre = $numberToWords->format($request->input('prix_forfetaire'));
            $reservation->prix_forfetaire_lettre = $prix_forfetaire_lettre;
            $reservation->bien_id = $request->input('bien_id');

            if ($reservation->save()) {
                if (RoleHelper::AdminSup()) {
                    //admin /sup admin peut changer le bien et les avances
                    if ($old_bien_id != $request->input('bien_id')) {
                        //reserver new bien
                        $bienController = new BienController();
                        $bienController->reserverBien($request->input('bien_id'), null, $reservation->id);
                        //liberer l'ancien bien
                        Bien_Helper::libererBien($old_bien_id, null, null);
                        //store to historique reservation
                        $histo = new HistoReservation();
                        $histo->setConnection('temp');
                        $histo->reservation_id = $reservation->id;
                        $histo->user_id = $userAuth->value('id');
                        $histo->bien_id = $old_bien_id;
                        $histo->save();
                        //store notif to all commerciaux
                        $commerciaux = User::on('temp')->where('role', 3)->get();
                        foreach ($commerciaux as $comm) {
                            Config::set('broadcasting.default', 'pusher_3');
                            $data_notif = [
                                'lien' => '/reservations/show/' . $id,
                                'date' => Carbon::now(),
                                'type' => 8,
                                'user_id' => $comm->user_id_origin,
                                'description' => 'admin a changé le bien du reservation',
                                'projet_id' => $reservation->projet_id,
                                'reservation_id' => $reservation->id,

                            ];
                            $notif_helper = new NotificationHelper();
                            $notif_helper->storeNotification($request->merge($data_notif));
                            broadcast(new NotificationEvent($id));

                        }

                    }
                }

                //delete aquereurs
                $old_aquereurs = Aquereur::on('temp')->where('reservation_id', $id)->get();
                foreach ($old_aquereurs as $aq) {
                    $aq->forceDelete();
                }
                //store new aqueereur
                $clientController = new ClientController();
                $clientRequest = new StoreClientRequest();
                $aquereurController = new AquereurController();
                $aquereurRequest = new StoreAquereurRequest();
                $dataArray_clients = json_decode($request->input('clients'), true);
                $dataArrayString = $request->input('oldClients', '[]');

                $dataArray_oldClients = json_decode($dataArrayString, true); // Ensure it's an array

                if ($dataArray_clients) {
                    foreach ($dataArray_clients as $clientInfo) {
                        $clientRequest->merge($clientInfo);
                        $clientData = $clientController->store($clientRequest);
                        $dataAquereur = [
                            'pourcentage' => $clientInfo['pourcentage'],
                            'client_id' => $clientData->id,
                            'reservation_id' => $reservation->id,
                        ];
                        $aquereurRequest->merge($dataAquereur);
                        $aquereurController->store($aquereurRequest);
                    }
                }
                if ($dataArray_oldClients) {
                    foreach ($dataArray_oldClients as $clientInfo) {
                        $dataAquereur = [
                            'pourcentage' => $clientInfo['pourcentage1'],
                            'client_id' => $clientInfo['id'],
                            'reservation_id' => $reservation->id,
                        ];
                        $aquereurRequest->merge($dataAquereur);
                        $aquereurController->store($aquereurRequest);
                    }
                }

                //****edit piece jointe***
                $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
                $societe = Societe::findOrfail($user_societes->societe_id);

                if (!$request->file('files_reservation')) {
                    $pjController = new PiecesJointeController();
                    $pjController->destoryFileUsingReservationId($id, $societe);
                }
                if ($request->file('files_reservation')) {

                    //*delete old piece jointe**

                    $pjController = new PiecesJointeController();
                    $pjController->destoryFileUsingReservationId($id, $societe);
                    foreach ($request->file('files_reservation') as $file) {
                        $piecesJointeController = new PiecesJointeController();
                        $pieceJointeRequest = new StorePiecesJointeRequest();

                        // Récupérer le nom du fichier
                        $Myfile = $file->getClientOriginalName();

                        $fileType = $file->getClientOriginalExtension();

                        // Déplacer le fichier vers le répertoire de destination
                        $directory = public_path('Docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/reservations');
                        File::makeDirectory($directory, 0755, true, true);

                        $file->move($directory, $Myfile);

                        $datapieceJointe = [
                            'fichier' => $Myfile,
                            'type' => $fileType,
                            'reservation_id' => $reservation->id,
                            'active' => 1,

                        ];

                        $pieceJointeRequest->merge($datapieceJointe);
                        $piecesJointeController->store($pieceJointeRequest);

                    }
                }
                //store new pieces jointes
            }
            return response()->json(['reservation' => $reservation], 200);
        }
        return response()->json(['error', 'Unauthorized'], 401);
    }

    public function relancer_reservation($id, Request $request)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $reservation = Reservation::on('temp')->findOrFail($id);
            $reservation->statut = StatutReservationEnum::En_Attente->value;
            $reservation->save();
            Config::set('broadcasting.default', 'pusher_3');
            //notifiction to admin de valider dossier d reservation user_id=>null
            $data_notif = [
                'lien' => '/validation/reservations/attente',
                'date' => Carbon::now(),
                'type' => 6,
                'role' => RoleEnum::ADMIN->value,
                'description' => 'DEMANDE VALIDATION RESERVATION',
                'projet_id' => $reservation->projet_id,
                'reservation_id' => $reservation->id,

            ];
            $notif_helper = new NotificationHelper();
            $notif_helper->storeNotification($request->merge($data_notif));
            broadcast(new NotificationEvent($reservation->id));
            Config::set('broadcasting.default', 'pusher_5');
            //1 traitement reservation
            broadcast(new NotifMenuEvent(1));
            return response()->json(['message' => 'reservation relancé avec succès.'], 200);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $reservation = Reservation::on('temp')->findOrFail($id);
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
            $societe = Societe::findOrfail($user_societes->societe_id);

            //bien disponible
            Bien_Helper::libererBien($reservation->bien_id, null, null);
            $avanceController = new AvanceController();
            $avanceController->destoryUsingReservationId($id);
            $tvaColletes = new ComptabiliteController();
            $tvaColletes->destroyTvaCollectesByReservationId($id);
            $aquereurController = new AquereurController();
            $aquereurController->destroyAquerreursByReservationId($id);
            $pjController = new PiecesJointeController();
            $pjController->destoryFileUsingReservationId($id, $user_societes, $societe);
            $notif = new NotificationController();
            $notif->destory_force_by_column_id('reservation', $id);
            $desistements=Desistement::on('temp')->where('reservation_id',$id)->get();
            foreach($desistements as $des){
                if($des->penalite_desistement!=null){
                    $des->penalite_desistement->delete();
                }
                if(count($des->remboursement)>0){
                    foreach($des->remboursement as $remb){
                        $remb->delete();
                    }
                }
                $des->delete();
            }

            if ($reservation->delete()) {
                return response()->json(['message' => 'reservation supprimée avec succès.'], 200);
            } else {
                return response()->json(['message' => "reservation n'est supprimée."], 400);
            }
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }



    public function get_Historiques_by_reservation($id, Request $request)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', config('app.default_item_number_perpage')); // Default size if not provided
            $page = $request->input('page', 1); // Default page if not provided

            DatabaseHelper::Config();


            $query = HistoReservation::on('temp')->with('user','bien')->where('reservation_id', $id);
            // Optional filters (Add more if needed)

            if ($request->filled('date')) {
                $start = Carbon::parse($request->input('date'));
                $query->whereDate('created_at' ,$start);
            }

            if ($request->filled('respo')) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->input('cc') . '%')
                            ->orWhere('prenom', 'like', '%' . $request->input('cc') . '%');
                    });
                });
            }
            if ($request->filled('bien')) {
                $query->whereHas('bien', function ($q) use ($request) {
                    $q->where('propriete_dite_bien', 'like', '%' . $request->input('bien') . '%');
                });
            }


            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                // Paginate if size and page are valid
                $historiques = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Add pagination info
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
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }



    public function get_reservations_by_etat($projet_id, $statut, Request $request)    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', config('app.default_item_number_perpage')); // Default size if not provided
            $page = $request->input('page', 1); // Default page if not provided

            DatabaseHelper::Config();


            $query = Reservation::on('temp')->withSum('avances','montant')->with('desistement_att_validation_rejete','last_statut','first_avance')
            ->orderBy('created_at', 'desc')
                ->where('projet_id', $projet_id)
                ->where('etat', 1)->where('reservations.statut', $statut);
            if (RoleHelper::Com()) {
                $user = Auth::user();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                $query->where('reservations.user_id', $userAuth->value('id'));
            }
            // Optional filters (Add more if needed)
            if ($request->filled('code_reservation')) {
                $query->where('code_reservation', 'like', '%' . $request->input('code_reservation') . '%');
            }
            if ($request->filled('date_reservation')) {
                $query->where('date_reservation', $request->input('date_reservation'));
            }
            if ($request->filled('client_id')) {
                $query->whereHas('Aquereurs.client', function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('id', $request->input('client_id'));
                    });
                });
            }
            if ($request->filled('client')) {
                $query->whereHas('Aquereurs.client', function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('nom', 'like', '%' . $request->input('client') . '%')
                            ->orWhere('prenom', 'like', '%' . $request->input('client') . '%');
                    });
                });
            }
            if ($request->filled('cc')) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->input('cc') . '%')
                            ->orWhere('prenom', 'like', '%' . $request->input('cc') . '%');
                    });
                });
            }
            if ($request->filled('bien')) {
                $query->whereHas('bien', function ($q) use ($request) {
                    $q->where('propriete_dite_bien', 'like', '%' . $request->input('bien') . '%');
                });
            }

            if ($request->filled('date_start')) {
                $start = Carbon::parse($request->input('date_start'));
                $query->whereDate('reservations.date_reservation','>=', $start);
            }
            if ($request->filled('date_end')) {
                $end = Carbon::parse($request->input('date_end'));
                $query->whereDate('reservations.date_reservation','<=', $end);
            }

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                // Paginate if size and page are valid
                $reservations = $query->orderBy('reservations.created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Add pagination info
                $pagination = [
                    'currentPage' => $reservations->currentPage(),
                    'totalItems' => $reservations->total(),
                    'totalPages' => $reservations->lastPage(),
                ];

                $reservations = $reservations->items();

                return response()->json([
                    'data' => $reservations,
                    'pagination' => $pagination,
                ], 200);
            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function get_notif_reservation_att_validation($projet_id)
    {

        if (Auth::guard('api')->check() && RoleHelper::ACSup()) {
            DatabaseHelper::Config();



            if (RoleHelper::AdminSup()) {

                $nb_att_validation = Reservation::on('temp')->withSum('avances','montant')->with('desistement_att_validation_rejete','last_statut','first_avance')
                ->orderBy('created_at', 'desc')
                ->where('projet_id', $projet_id)
                ->where('etat', 1)->where('statut',3)->count();

            } else if (RoleHelper::Com()) {
                $user = Auth::user();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                $nb_att_validation = Reservation::on('temp')->withSum('avances','montant')->with('desistement_att_validation_rejete','last_statut','first_avance')
                ->orderBy('created_at', 'desc')
                    ->where('projet_id', $projet_id)
                    ->where('etat', 1)->where('statut',3)->where('user_id', $userAuth->value('id'))->count();
            }
            return response()->json(['nb' => $nb_att_validation]);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }
    public function traiter_reservation($id, Request $request)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $reservation = Reservation::on('temp')->findOrFail($id);
            $reservation->statut = $request->statut_res;
            if ($reservation->save()) {
                $res_statut = new statutReservation();
                $res_statut->setConnection('temp');
                $res_statut->reservation_id = $id;
                $res_statut->statut = $request->statut_res;
                $res_statut->user_id_valider = $userAuth->value('id');
                $res_statut->date_validation = Carbon::now();
                if ($request->statut_res == 2) {
                    $res_statut->commentaire = $request->commentaire_res;
                }
                $res_statut->save();
            }

            if ($request->statut_res == 1) {
                //store new notification validé
                Config::set('broadcasting.default', 'pusher_3');
                $data_notif = [
                    'lien' => '/reservations/show/' . $id,
                    'date' => Carbon::now(),
                    'type' => 15,
                    'user_id' => $reservation->user->user_id_origin,
                    'description' => 'reservation validé',
                    'projet_id' => $reservation->projet_id,
                    'reservation_id' => $reservation->id,

                ];
                $notif_helper = new NotificationHelper();
                $notif_helper->storeNotification($request->merge($data_notif));

                broadcast(new NotificationEvent($id));
                Config::set('broadcasting.default', 'pusher_5');
                //1 traitement reservation
                broadcast(new NotifMenuEvent(1));

            } else {
                //store new notification rejeté
                Config::set('broadcasting.default', 'pusher_3');
                $data_notif = [
                    'lien' => '/reservations/show/' . $id,
                    'date' => Carbon::now(),
                    'type' => 16,
                    'user_id' => $reservation->user->user_id_origin,
                    'description' => 'reservation rejeté',
                    'projet_id' => $reservation->projet_id,
                    'reservation_id' => $reservation->id,

                ];
                $notif_helper = new NotificationHelper();
                $notif_helper->storeNotification($request->merge($data_notif));
                broadcast(new NotificationEvent($id));
                Config::set('broadcasting.default', 'pusher_5');
                //1 traitement reservation
                broadcast(new NotifMenuEvent(1));

            }
            //traiter reservation with avance
            if ($request->with_avance == 1) {
                $avanceController = new AvanceController();
                $data_avance = [
                    'etat' => $request->statut_av,
                    'n_remise' => $request->n_remise,
                    'date_encaiss' => $request->date_encaiss,
                    'commentaire' => $request->commentaire_av,
                ];
                $avanceController->traiter_avance($request->av_id, $request->merge($data_avance));
            }

            return response()->json(['message' => 'données enregistrés avec succès.'], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }

}
