<?php

namespace App\Http\Controllers\Api\V1;

use App\Enum\RoleEnum;
use App\Enum\EtatBien;
use App\Models\Proposition;
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
use App\Models\StatutClient;

use App\Models\Avance;
use App\Models\Bien;
use App\Models\Client;
use App\Models\Visite;
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
use Illuminate\Validation\Rule;
use App\Models\TraitementAppel;
use App\Models\HistoriqueBien;
use App\Models\PreReservation;
use App\Models\HistoriqueDesistement;
use App\Models\Rendez_vous;
use Mail;
use Illuminate\Support\Facades\Log;

use App\Models\Compromis_vente;
use App\Models\Contrat_vente;

use App\Http\Controllers\NotificationController;

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

            $reservations = Reservation::on('temp')->with('desistement_att_validation_rejete', 'last_statut', 'first_avance','contrat_vente')
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


            $query = Reservation::on('temp')->withSum('avances','montant')->with('desistement_att_validation_rejete','last_statut','first_avance','contrat_vente')
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
            if ($request->filled('user_id')) {
                $realUserId = User::on('temp')
                    ->where('user_id_origin', $request->user_id)
                    ->value('id');

                if ($realUserId) {
                    $query->where('user_id', $realUserId)
                        ->where('etat', 1); // ou 'statut' selon le champ de ta base
                }
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
               // ->select('reservations.*', 'avances_req.sum_avances')
                ->select('reservations.id', 'reservations.code_reservation','avances_req.sum_avances','reservations.etat','reservations.prix')
                ->whereColumn('sum_avances', '<', 'reservations.prix')
                ->where('reservations.id', '!=', $dos_id)
                ->orderBy('reservations.created_at', 'desc')
                ->where('reservations.etat', 1)
                ->where('reservations.projet_id', $projet_id)
                ->without( 'user', 'projet','historiques','piece_jointe','bien','aquereurs','aquereurs_ancien')
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
        if (!RoleHelper::ACSup()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        DatabaseHelper::Config();
        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();
        $societe_id = Auth::guard('api')->user()->societe_id;
        $societe = Societe::findOrfail($societe_id);
        $DatabaseName = 'Erp_'.$societe->raison_sociale_concatene.'_'.$societe_id;
        $reservation = null;

        DB::connection('temp')->beginTransaction();

        try {
            // Validate bien status if provided
            if ($request->bien_id) {
                $bien_prop = Bien::on('temp')->findOrFail($request->bien_id);
                if ($bien_prop->etat == 'ENCOURS_DE_PROPOSITION' &&
                    $bien_prop->is_proposed->user_id != $userAuth->user_id_origin) {
                    return response()->json([
                        'error_33' => 'le bien choisi :' . $bien_prop->propriete_dite_bien .
                        ' est en cours de proposition par : ' .
                        $bien_prop->is_proposed->user->name . ' ' .
                        $bien_prop->is_proposed->user->prenom
                    ], 333);
                }
            }

            // Validate unique code if provided
            if ($request->has('code_reservation')) {
                $request->validate([
                    'code_reservation' => [
                        Rule::unique('temp.'.$DatabaseName.'.reservations')
                                            ->where('etat', 1)->whereNull('deleted_at'),
                    ],
                ]);
            }

            // Create temporary reservation with minimal data
            $reservation = $this->createTemporaryReservation($request, $userAuth);

            // PROCESS FILES IMMEDIATELY AFTER CREATING RESERVATION
            $this->processReservationFiles($reservation, $request, $societe);

            // Process all dependent operations
            $this->processDependencies($reservation, $request, $userAuth);

            // Finalize the reservation
            $this->finalizeReservation($reservation, $userAuth);

            // Commit transaction
            DB::connection('temp')->commit();

            return response()->json(['reservation' => $reservation], 200);

        } catch (\Exception $e) {
            DB::connection('temp')->rollBack();

            if ($reservation !== null) {
                $this->rollbackReservationCreation($reservation);
            }

            \Log::error("Reservation creation failed: " . $e->getMessage());
            return response()->json(['error' => 'Reservation creation failed: ' . $e->getMessage()], 500);
        }
    }

/**
 * Process reservation files immediately
 */
private function processReservationFiles($reservation, $request, $societe)
{
    if (!$request->hasFile('files_reservation')) {
        return;
    }

    $files = $request->file('files_reservation');

    // Check if files are still valid
    if (!is_array($files) || empty($files)) {
        \Log::warning('No valid files found in request');
        return;
    }

    foreach ($files as $file) {
        if (!$file->isValid()) {
            \Log::error('File upload error: ' . $file->getError());
            continue;
        }

        try {
            $piecesJointeController = new PiecesJointeController();
            $pieceJointeRequest = new StorePiecesJointeRequest();

            // Get file name and type
            $fileName = $file->getClientOriginalName();
            $fileType = $file->getClientOriginalExtension();

            // Create directory
            $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/reservations/'.$reservation->code_reservation);

            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true, true);
            }

            // Move file with error handling
            $filePath = $directory . '/' . $fileName;

            if (!file_exists($file->getPathname())) {
                throw new \Exception("Temporary file does not exist: " . $file->getPathname());
            }

            // Copy file instead of move to preserve original
            if (!copy($file->getPathname(), $filePath)) {
                throw new \Exception("Failed to copy file to destination: " . $filePath);
            }

            $datapieceJointe = [
                'fichier' => $fileName,
                'type' => $fileType,
                'reservation_id' => $reservation->id,
                'active' => 1,
            ];

            $pieceJointeRequest->merge($datapieceJointe);
            $piecesJointeController->store($pieceJointeRequest);

            \Log::info('File processed successfully: ' . $fileName);

        } catch (\Exception $e) {
            \Log::error('Error processing file: ' . $e->getMessage());
            throw $e; // Re-throw to trigger rollback
        }
    }
}

// Update finalizeReservation to remove file processing
        private function finalizeReservation($reservation, $userAuth)
        {
             if (RoleHelper::Com()) {
            //create histo reservation en attente
                $histo = new HistoReservation();
                $histo->setConnection('temp');
                $histo->reservation_id = $reservation->id;
                $histo->user_id = $userAuth->id;
                $histo->bien_id = $reservation->bien_id;
                $histo->action = 1;//en attente
                $histo->description = null;
                $histo->save();
             }
            // Create status record if validated
            if ($reservation->statut == StatutReservationEnum::Validé->value) {
                //Validation
                $histo = new HistoReservation();
                $histo->setConnection('temp');
                $histo->reservation_id = $reservation->id;
                $histo->user_id = $userAuth->id;
                $histo->bien_id = $reservation->bien_id;
                $histo->action = 2;//Validation
                $histo->description = null;
                $histo->save();

                $res_statut = new statutReservation();
                $res_statut->setConnection('temp');
                $res_statut->reservation_id = $reservation->id;
                $res_statut->statut = StatutReservationEnum::Validé->value;
                $res_statut->user_id_valider = $userAuth->id;
                $res_statut->date_validation = Carbon::now();
                $res_statut->save();
            }



            // Send notifications if needed
            if (RoleHelper::Com()) {
                Config::set('broadcasting.default', 'pusher_3');

                $data_notif = [
                    'lien' => '/ventes/reservations/'.$reservation->id,
                    'date' => Carbon::now(),
                    'type' => 6,
                    'role' => RoleEnum::ADMIN->value,
                    'description' => 'DEMANDE VALIDATION RESERVATION',
                    'projet_id' => $reservation->projet_id,
                    'reservation_id' => $reservation->id,
                ];

                (new NotificationHelper())->storeNotification(request()->merge($data_notif));
                broadcast(new NotificationEvent($reservation->id));

                Config::set('broadcasting.default', 'pusher_5');
                broadcast(new NotifMenuEvent(1));

                //send mail to admin avec etat
                $admins = User::on('temp')->select('id','email','name')->where('role',2)->where('email','!=',null)->get();
                if($admins->count() > 0){
                    foreach($admins as $admin){
                        try {
                            $to_email = $admin->email;
                            $data = [
                                'adminName' => $admin->name,
                                'reservationCode' => $reservation->code_reservation,
                                'validationLink' => env('APP_URL').'/ventes/reservations/'.$reservation->id,
                                'dateCreation' => Carbon::now()->format('d/m/Y à H:i'),
                                'createdBy' => $userAuth->name ?? 'Un commercial'
                            ];

                            Mail::send('emails.demande_validation_reservation', $data, function ($message) use ($to_email, $reservation) {
                                $message->to($to_email)
                                    ->subject('Demande Validation Réservation : '.$reservation->code_reservation);
                                $message->from(env('MAIL_USERNAME'), 'Immobilier Immo');
                            });

                            Log::info("Email de demande de validation envoyé à l'admin: {$admin->email}");

                        } catch (\Exception $e) {
                            Log::error("Échec de l'envoi de l'email à l'admin {$admin->email}: " . $e->getMessage());
                        }
                    }
                }
            }
        }



            private function rollbackReservationCreation($reservation)
            {
                try {
                    // Delete all related records
                    //meter bien en proposition
                        $bien = Bien::on('temp')->findOrFail($reservation->bien_id);
                        $bien->etat = EtatBien::ENCOURS_DE_PROPOSITION->value;
                        if ($bien->save()) {
                            $bien_propose = new Proposition();
                            $bien_propose->setConnection('temp');
                            $bien_propose->bien_id = $bien_id;
                            $bien_propose->user_id = Auth::guard('api')->user()->id;
                            $bien_propose->save();
                        }
                    Aquereur::on('temp')->where('reservation_id', $reservation->id)->delete();
                    Avance::on('temp')->where('reservation_id', $reservation->id)->delete();
                    PiecesJointe::on('temp')->where('reservation_id', $reservation->id)->delete();

                    // Delete the provisional reservation
                    $reservation->delete();

                } catch (\Exception $e) {
                    \Log::error("Failed to clean up failed reservation: " . $e->getMessage());
                }
            }

            /**
             * Create temporary reservation with minimal data
             */
           private function createTemporaryReservation($request, $userAuth)
                {
                    $reservation = new Reservation();
                    $reservation->setConnection('temp');

                    // Set fields individually
                    $reservation->prix = $request->prix;
                    $reservation->mode_financement =$request->mode_financement;
                    $reservation->nb_acquereurs = $request->nb_acquereurs;
                    $reservation->code_reservation = $request->code_reservation;
                    $reservation->bien_id = $request->bien_id;
                    $reservation->projet_id = $request->projet_id;
                    $reservation->user_id = $userAuth->id;
                    $reservation->visite_id=$request->visite_id;
                    $reservation->date_reservation = $request->date_reservation;


                    // Update with all remaining fields individually

                $reservation->commentaire = $request->commentaire== "null" ? null : $request->commentaire;
                $reservation->prix_remise = $request->prix_remise;
                $reservation->prix_remise_lettre = (new NumberFormatter('fr', NumberFormatter::SPELLOUT))->format($request->prix_remise);
                $reservation->prix_forfetaire = $request->prix_forfetaire;
                $reservation->prix_forfetaire_lettre = (new NumberFormatter('fr', NumberFormatter::SPELLOUT))->format($request->prix_forfetaire);

                    $reservation->statut = RoleHelper::AdminSup()
                            ? StatutReservationEnum::Validé->value
                            : StatutReservationEnum::En_Attente->value;
                    if (!$reservation->save()) {
                        throw new \Exception('Failed to create temporary reservation');
                    }
                    return $reservation;
                }

            /**
             * Process all dependent operations
             */
            private function processDependencies($reservation, $request, $userAuth)
            {
                // Reserve the bien if specified
                if ($request->bien_id) {
                    (new BienController())->reserverBien($request->bien_id, null, $reservation->id);
                }

                // Process clients and aquereurs
                $this->processClients($reservation, $request,$userAuth);

                // Process payment if specified
                if ($request->avance) {
                    $this->processPayment($reservation, $request);
                }
}

            /**
             * Process client data
             */
            private function createStatutClient(
                        int $clientId,
                        int $reservationId,
                        int $userId,
                        string $codeReservation,
                        ?string $additionalComment = null
                    ): StatutClient {
                        $statutClient = new StatutClient();
                        $statutClient->setConnection('temp');
                        $statutClient->visite_id = null;
                        $statutClient->client_id = $clientId;
                        $statutClient->statut = '5'; // création reservation
                        $statutClient->avance_id = null;
                        $statutClient->reservation_id = $reservationId;
                        $statutClient->date_traitement = now();
                        $statutClient->user_id_traite = $userId;

                        // Build comment with optional additional information
                        $comment = 'Création Réservation code : ' . $codeReservation;
                        if ($additionalComment) {
                            $comment .= ' - ' . $additionalComment;
                        }
                        $statutClient->commentaire = $comment;

                        $statutClient->save();

                        return $statutClient;
                    }
            private function processClients($reservation, $request,$userAuth)
            {
                $clientController = new ClientController();
                $aquereurController = new AquereurController();
                $clientRequest = new StoreClientRequest();
                $aquereurRequest = new StoreAquereurRequest();


                if ($request->origin == 'visite') {
                    $client_exist = Client::on('temp')
                        ->where('prospect_id', $request->prospect_id)
                        ->orderBy('created_at', 'DESC')
                        ->first();

                    if ($client_exist) {
                        $clientData = $client_exist;
                        \Log::info('Using existing client ID: ' . $clientData->id);

                    } else {
                         \Log::info('Creating new client for prospect: ' . $request->prospect_id);

                        // Debug: Log what data is being sent
                        \Log::info('Client creation data:', [
                            'cin' => $request->cin,
                            'nom' => $request->nom,
                            'prenom' => $request->prenom,
                            'prospect_id' => $request->prospect_id,
                            'projet_id' => $request->projet_id,
                            'telephone_num1' => $request->telephone_num1
                        ]);
                        $aquereurRequest = new StoreAquereurRequest();

                        $dataClient = [
                            'cin' => $request->cin,
                            'nom' => $request->nom,
                            'prenom' => $request->prenom,
                            'telephone_num1' => $request->telephone_num1,
                            'telephone_num2' => $request->telephone_num2,
                            'notifie' => $request->notifie??0,
                            'prospect_id' => $request->prospect_id,
                            'civilite' => $request->civilite,
                            'situation_familliale' => $request->situation_familliale,
                            'type_client' => 1,
                            'projet_id' => $request->projet_id,
                            'email' => $request->email ?? '',
                            'ville' => $request->ville ?? '',
                        ];
                        $clientRequest->merge($dataClient);
                         $clientData = $clientController->store($clientRequest);
                            // Create statut client using the new function
                            $this->createStatutClient(
                                clientId: $clientData->id,
                                reservationId: $reservation->id,
                                userId: $userAuth->value('id'),
                                codeReservation: $reservation->code_reservation
                            );
                        \Log::info('Client created successfully with ID: ' . $clientData->id);
                    }
                    $dataAquereur = [
                        'pourcentage' => 100,
                        'client_id' => $clientData->id,
                        'reservation_id' => $reservation->id,
                    ];
                    $aquereurRequest->merge($dataAquereur);
                     $aquereurResult = $aquereurController->store($aquereurRequest);

                    \Log::info('Aquereur created for reservation: ' . $reservation->id);
                } else {
                    $dataArray_clients = json_decode($request->input('clients'), true);
                    $dataArray_oldClients = json_decode($request->input('oldClients', '[]'), true);

                    if ($dataArray_clients) {
                        foreach ($dataArray_clients as &$clientInfo) {
                              if (empty($clientInfo['pourcentage']) || !is_numeric($clientInfo['pourcentage']) || $clientInfo['pourcentage'] <= 0) {
                                    continue; // Skip this client
                                }


                            $clientInfo['projet_id'] = $request->projet_id;
                            $clientRequest->merge($clientInfo);
                            $clientData = $clientController->store($clientRequest);
                            // Create statut client using the new function
                                    $this->createStatutClient(
                                        clientId: $clientData->id,
                                        reservationId: $reservation->id,
                                        userId: $userAuth->value('id'),
                                        codeReservation: $reservation->code_reservation
                                    );
                            $dataAquereur = [
                                'pourcentage' => $clientInfo['pourcentage'],
                                'client_id' => $clientData->id,
                                'reservation_id' => $reservation->id,
                            ];
                            $aquereurRequest->merge($dataAquereur);
                            $aquereurController->store($aquereurRequest);
                        }
                        unset($clientInfo);
                    }

                    if ($dataArray_oldClients) {
                        foreach ($dataArray_oldClients as $clientInfo) {

                                // Skip if percentage is empty or invalid
                                    $pourcentage = $clientInfo['pourcentage1'] ?? $clientInfo['pourcentage'] ?? 0;
                                    if (empty($pourcentage) || !is_numeric($pourcentage) || $pourcentage <= 0) {
                                        continue; // Skip this client
                                    }

                                   // Create statut client using the new function
                                    $this->createStatutClient(
                                        clientId: $clientInfo['id'],
                                        reservationId: $reservation->id,
                                        userId: $userAuth->value('id'),
                                        codeReservation: $reservation->code_reservation
                                    );
                                    $dataAquereur = [
                                        'pourcentage' => $pourcentage,
                                        'client_id' => $clientInfo['id'],
                                        'reservation_id' => $reservation->id,
                                    ];
                                    $aquereurRequest->merge($dataAquereur);
                                    $aquereurController->store($aquereurRequest);
                            }

                     }
                    }
            }
            /**
             * Process payment data
             */
            private function processPayment($reservation, $request)
            {
                $avanceController = new AvanceController();
                $avanceRequest = new StoreAvanceRequest();
                $inWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
                $mnt_lettre = $inWords->format($request->avance);
                // Process avance files immediately and store file names
                    $avanceFileNames = [];
                    if ($request->file('files_avance')) {
                        $avanceFileNames = $this->processAvanceFiles($reservation, $request);
                    }

                $dataAvance = [
                    'avance_with_reservation' => true,
                    'desistement_id' => null,
                    'dossier_id_transfert' => null,
                    'sr' => $request->sr,
                    'type_encaissement' => 1,
                    'montant' => $request->avance,
                    'mode_paiement' => $request->mode_paiement,
                    'numero_paiement' => $request->numero_paiement,
                    'date_reglement' => $request->date_reglement,
                    'echeance' => $request->echeance,
                    'banque_id' => $request->banque_id,
                    'montant_par_lettre' => $mnt_lettre,
                    'reservation_id' => $reservation->id,
                    'commentaireAvance' => $request->commentaireAvance,
                    'num_remise' => $request->num_remise,
                    'date_encaissement' => $request->date_encaissement,
                    //'files_avance' => $request->file('files_avance'),
                    'processed_files' => $avanceFileNames, // Pass processed file names instead of file objects

                ];

                $avanceRequest->merge($dataAvance);
                $avanceController->store($avanceRequest);
            }

            /**
             * Process file uploads
             */


                private function processAvanceFiles($reservation, $request)
                {
                    $processedFiles = [];
                    $societe_id = Auth::guard('api')->user()->societe_id;
                    $societe = Societe::findOrfail($societe_id);

                    foreach ($request->file('files_avance') as $file) {
                        if (!$file->isValid()) {
                            \Log::error('Avance file upload error: ' . $file->getError());
                            continue;
                        }

                        try {
                            // Get file name and type
                            $fileName = $file->getClientOriginalName();
                            $fileType = $file->getClientOriginalExtension();

                            // Create directory
                            $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/paiements/' . $reservation->code_reservation);

                            if (!File::exists($directory)) {
                                File::makeDirectory($directory, 0755, true, true);
                            }

                            $filePath = $directory . '/' . $fileName;

                            // Copy file to permanent location
                            if (!copy($file->getPathname(), $filePath)) {
                                throw new \Exception("Failed to copy avance file to destination: " . $filePath);
                            }

                            $processedFiles[] = [
                                'file_name' => $fileName,
                                'file_type' => $fileType,
                                'file_path' => $filePath,
                            ];

                            \Log::info('Avance file processed successfully: ' . $fileName);

                        } catch (\Exception $e) {
                            \Log::error('Error processing avance file: ' . $e->getMessage());
                            throw $e;
                        }
                    }

                    return $processedFiles;
                }

                public function search_reservation_by_code($code)
                {
                    if (RoleHelper::ACSup()) {
                        DatabaseHelper::Config();
                        $reservation = Reservation::on('temp')->where('code_reservation', $code)->where('etat', 1)
                            ->get()->first();
                        return response()->json(['reservation' => $reservation]);
                    }
                }

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

private function getAllHistoriquesWithAncien($reservationId)
{
    $allHistoriques = collect();

    // Get historiques for current reservation
    $currentHistoriques = HistoReservation::on('temp')
        ->select('id', 'reservation_id', 'ancien_id', 'bien_id', 'user_id', 'action', 'description', 'created_at')
        ->with([
            'user' => function($q) {
                $q->select('id', 'name', 'prenom')->without('societe');
            },
            'bien' => function($q) {
                $q->select('id', 'propriete_dite_bien', 'tranche_id', 'bloc_id', 'immeuble_id')
                  ->with([
                      'immeuble' => function($t) { $t->select('id', 'nom'); },
                      'bloc' => function($b) { $b->select('id', 'nom'); },
                      'tranche' => function($i) { $i->select('id', 'nom'); }
                  ]);
            }
        ])
        ->where('reservation_id', $reservationId)
        ->get();

    $allHistoriques = $allHistoriques->merge($currentHistoriques);

    // Check for ancien_id chain
    $ancienIds = [];
    $nextId = $currentHistoriques->firstWhere('ancien_id', '!=', null)?->ancien_id;

    // Follow the chain of ancien_ids
    while ($nextId !== null && !in_array($nextId, $ancienIds)) {
        $ancienIds[] = $nextId;

        $ancienHistoriques = HistoReservation::on('temp')
            ->select('id', 'reservation_id', 'ancien_id', 'bien_id', 'user_id', 'action', 'description', 'created_at')
            ->with([
                'user' => function($q) {
                    $q->select('id', 'name', 'prenom')->without('societe');
                },
                'bien' => function($q) {
                    $q->select('id', 'propriete_dite_bien', 'tranche_id', 'bloc_id', 'immeuble_id')
                      ->with([
                          'immeuble' => function($t) { $t->select('id', 'nom'); },
                          'bloc' => function($b) { $b->select('id', 'nom'); },
                          'tranche' => function($i) { $i->select('id', 'nom'); }
                      ]);
                }
            ])
            ->where('reservation_id', $nextId)
            ->get();

        $allHistoriques = $allHistoriques->merge($ancienHistoriques);

        // Get next ancien_id
        $nextId = $ancienHistoriques->firstWhere('ancien_id', '!=', null)?->ancien_id;
    }

    // Sort by ID descending (highest ID = most recent first)
    return $allHistoriques->sortByDesc('id')->values();
}
    /*'historiques' => function($query) {
                        $query->select('id', 'reservation_id', 'bien_id', 'user_id', 'action', 'description', 'created_at','ancien_id')
                            ->with([
                                'user' => function($q) {
                                    $q->select('id', 'name', 'prenom')->without('societe');
                                },
                                'bien' => function($q) {
                                    $q->select('id', 'propriete_dite_bien', 'tranche_id', 'bloc_id', 'immeuble_id')
                                    ->without('projet','typologie','vue','compositionBien','typeBien')
                                        ->with([
                                            'immeuble' => function($t) {
                                                $t->select('id', 'nom') ->without(['projet', 'tranche','bloc']);
                                            },
                                            'bloc' => function($b) {
                                                $b->select('id', 'nom')
                                            ->without(['projet', 'tranche']);
                                            },
                                            'tranche' => function($i) {
                                                $i->select('id', 'nom')
                                            ->without(['projet']);
                                            }
                                        ]);
                                }
                            ])
                            ->orderBy('created_at', 'desc');
                    },*/
    public function show($id)
{
    if (RoleHelper::ACSup()) {
        DatabaseHelper::Config();

        $reservation = Reservation::on('temp')
            ->withSum('avances','montant')
            ->without('avances_valides')
            ->with([
                'bien' => function($query) {
                    $query->with([
                        'immeuble' => function($q) {
                            $q->select('id', 'nom')
                              ->without(['projet', 'tranche','bloc']);
                        },
                        'bloc' => function($q) {
                            $q->select('id', 'nom')
                              ->without(['projet', 'tranche']);
                        },
                        'tranche' => function($q) {
                            $q->select('id', 'nom')
                              ->without(['projet']);
                        }
                    ]);
                   // $query->without('projet','typologie','vue','compositionBien','typeBien');
                },
                'last_statut' => function($query) {
                    $query->without('reservation','user');
                },
                'compromis_vente' => function($query) {
                    $query-> select('*')->without('reservation','user');
                },
                'contrat_vente' => function($query) {
                    $query->without('reservation','user');
                },
                'first_avance' => function($query) {
                    $query->without('reservation','user');
                },
                'projet' => function($query) {
                    $query->select('id', 'nom', 'adresse')
                          ->without('user_projet', 'type_projet');
                },//'aquereurs','aquereurs_ancien'

            ])
            ->findOrFail($id);


        // Hide avances_valides from response
        $reservation->makeHidden('avances_valides');

        $sum_avances_valides = 0;

        // Conditionally replace aquereurs with aquereurs_ancien if etat > 1
        if ($reservation->etat > 1) {
             $reservation->load('remboursement_dd_with_transfert');
           // Load aquereurs_ancien relationship
            $reservation->load('aquereurs_ancien');
            // Replace aquereurs with aquereurs_ancien for the response
            $reservation->aquereurs = $reservation->aquereurs_ancien;
            // Hide the original aquereurs_ancien from response if needed
            $reservation->load('desistements_ancien');

            // Load piece_jointe_desiste when etat > 1
            $reservation->load('piece_jointe_desiste');
            // Hide piece_jointe from response
            $reservation->makeHidden('piece_jointe');
            foreach ($reservation->avances_desist as $av) {
                if ($av->statut == StatutReservationEnum::Validé->value) {
                    $sum_avances_valides += $av->montant;
                }
            }
        } else if($reservation->etat == 1) {
             // Load piece_jointe when etat == 1
            $reservation->load('piece_jointe');
            // Hide piece_jointe_desiste from response
            $reservation->makeHidden('piece_jointe_desiste');
            // Load desistement_att_validation_rejete only when etat == 1
            $reservation->load('desistement_att_validation_rejete');
             foreach ($reservation->avances_valides as $av) {
                $sum_avances_valides += $av->montant;
            }
        }
          // Get all historiques (with ancien if applicable)
        $allHistoriques = $this->getAllHistoriquesWithAncien($id);

        // Set the relation on reservation object
        $reservation->setRelation('historiques', $allHistoriques);
        return response()->json([
            'reservation' => $reservation,
            'sum_avances_valides' => $sum_avances_valides
        ], 200);
    } else {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
}
        public function show_dossier_in_dd($id)
        {
            if (RoleHelper::ACSup()) {
                DatabaseHelper::Config();

                $reservation = Reservation::on('temp')
                    ->withSum('avances','montant')
                    ->without('avances_valides','historiques','aquereurs_ancien','piece_jointe','user','projet')
                    ->with([
                        'aquereurs',
                        'bien' => function($query) {
                            $query->with([
                                'immeuble' => function($q) {
                                    $q->select('id', 'nom')
                                    ->without(['projet', 'tranche','bloc']);
                                },
                                'bloc' => function($q) {
                                    $q->select('id', 'nom')
                                    ->without(['projet', 'tranche']);
                                },
                                'tranche' => function($q) {
                                    $q->select('id', 'nom')
                                    ->without(['projet']);
                                }
                                ,'typeBien',
                            ])->without('projet','typologie','vue');
                        },

                    ])
                    ->findOrFail($id);


                // Hide avances_valides from response
                $reservation->makeHidden('avances_valides');

                $sum_avances_valides = 0;
                    foreach ($reservation->avances_valides as $av) {
                        $sum_avances_valides += $av->montant;
                    }

                return response()->json([
                    'reservation' => $reservation,
                    'sum_avances_valides' => $sum_avances_valides
                ], 200);
            } else {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }


    public function get_pj_res($id, Request $request)
    {
        if (Auth::guard('api')->check()) {

            DatabaseHelper::Config();
            $reservation=Reservation::on('temp')->findOrFail($id);
            if($reservation->etat==1){
                $data=$reservation->piece_jointe;
            }else{
                $data=$reservation->piece_jointe_desiste;
            }
            return response()->json([
                'data' => $data,
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

    private function formatBienInfo($bien) {
    if (!$bien) {
        return '';
    }

    $parts = [];

    // Add bien property name
    $parts[] = $bien->propriete_dite_bien;

    // Add immeuble if exists
    if ($bien->immeuble && $bien->immeuble->nom) {
        $parts[] = $bien->immeuble->nom;
    }

    // Add bloc if exists
    if ($bien->bloc && $bien->bloc->nom) {
        $parts[] = $bien->bloc->nom;
    }

    // Add tranche if exists
    if ($bien->tranche && $bien->tranche->nom) {
        $parts[] = $bien->tranche->nom;
    }

    // Reverse the array to get the hierarchy in the correct order
    $parts = array_reverse($parts);

    return implode('-', $parts);
}
    /**
     * Update the specified resource in storage.
     */
        public function update(UpdateReservationRequest $request, $id)
{
    if (!RoleHelper::ACSup()) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    DatabaseHelper::Config();
    DB::connection('temp')->beginTransaction();

    try {
        $reservation = Reservation::on('temp')->findOrFail($id);
        $originalAttributes = $reservation->getOriginal();

        if ($request->has('code_reservation')) {
            $societe_id = Auth::guard('api')->user()->societe_id;
            $societe = Societe::findOrfail($societe_id);
            $DatabaseName = 'Erp_' . $societe->raison_sociale_concatene . '_' . $societe_id;

            $request->validate([
                'code_reservation' => [
                    Rule::unique('temp.' . $DatabaseName . '.reservations')
                        ->where('etat', 1)
                        ->whereNull('deleted_at')
                        ->ignore($id),
                ],
            ]);
        }

        $user = Auth::user();
        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();
        $old_bien_id = $reservation->bien_id;

        // Test if the connected user is the one who made the proposal / property status
        if ($old_bien_id != $request->input('bien_id')) {
            $bien_prop = Bien::on('temp')->findorfail($request->input('bien_id'));

            if ($bien_prop->etat == 'ENCOURS_DE_PROPOSITION' &&
                $bien_prop->is_proposed->user_id != $userAuth->user_id_origin) {
                return response()->json([
                    'error_33' => 'le bien choisi :' . $bien_prop->propriete_dite_bien .
                    ' est en cours de proposition par : ' .
                    $bien_prop->is_proposed->user->name . ' ' .
                    $bien_prop->is_proposed->user->prenom
                ], 333);
            }
        }

        $changes = [];

        // Define your finance modes enum
        $financeModes = [
            1 => ['code' => 1, 'label' => 'Comptant'],
            2 => ['code' => 2, 'label' => 'Crédit'],
            3 => ['code' => 3, 'label' => 'Indécis'],
        ];

        // Track changes for each field
        $fieldsToTrack = [
            'code_reservation', 'prix', 'mode_financement',
            'date_reservation', 'commentaire', 'prix_remise', 'prix_forfetaire'
        ];

        foreach ($fieldsToTrack as $field) {
            if ($request->has($field)) {
                $newValue = $request->input($field);
                $oldValue = $reservation->$field;

                if ($newValue != $oldValue) {
                    // Fix: Show empty string instead of "N/A" for null values
                    $oldDisplay = $oldValue;
                    $newDisplay = $newValue;

                    // Special handling for mode_financement
                    if ($field == 'mode_financement') {
                        $oldDisplay = isset($financeModes[$oldValue]) ? $financeModes[$oldValue]['label'] : $oldValue;
                        $newDisplay = isset($financeModes[$newValue]) ? $financeModes[$newValue]['label'] : $newValue;
                    }

                    // Only add to changes if not both null/empty
                    if (!(empty($oldDisplay) && empty($newDisplay))) {
                        $changes[$field] = [
                            'old' => $oldDisplay ?? '', // Show empty instead of "N/A"
                            'new' => $newDisplay ?? ''  // Show empty instead of "N/A"
                        ];
                    }
                }
            }
        }

        // Handle bien change separately
       // Then in your update method, replace this section:
        if ($old_bien_id != $request->input('bien_id')) {
            $oldBien = Bien::on('temp')->with(['tranche', 'bloc', 'immeuble'])->find($old_bien_id);
            $newBien = Bien::on('temp')->with(['tranche', 'bloc', 'immeuble'])->find($request->input('bien_id'));

            $changes['bien'] = [
                'old' => $oldBien ? $this->formatBienInfo($oldBien) : $old_bien_id,
                'new' => $newBien ? $this->formatBienInfo($newBien) : $request->input('bien_id')
            ];
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
                // Admin/super admin can change property and advances
                if ($old_bien_id != $request->input('bien_id')) {
                    // Reserve new property
                    $bienController = new BienController();
                    $bienController->reserverBien($request->input('bien_id'), null, $reservation->id);

                    // Free old property
                    Bien_Helper::libererBien($old_bien_id, null, null, false);

                    // Store to reservation history
                    /*$histo = new HistoReservation();
                    $histo->setConnection('temp');
                    $histo->reservation_id = $reservation->id;
                    $histo->user_id = $userAuth->id;
                    $histo->bien_id = $old_bien_id;
                    $histo->action = 1;
                    $histo->description = json_encode(['bien' => $changes['bien']]);
                    $histo->save();*/

                    // Store notification to all commercial users
                    $commerciaux = User::on('temp')->where('role', 3)->get();
                    foreach ($commerciaux as $comm) {
                        Config::set('broadcasting.default', 'pusher_3');
                        $data_notif = [
                            'lien' => '/ventes/reservations/' . $id,
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

            // Track acquereurs changes
            $old_aquereurs = Aquereur::on('temp')->with('client')->where('reservation_id', $id)->get();

            // Map to include client names
            $old_aquereurs_data = $old_aquereurs->map(function($aq) {
                return [
                    'client_id' => $aq->client_id,
                    'client_nom' => $aq->client->nom ?? null,
                    'client_prenom' => $aq->client->prenom ?? null,
                    'pourcentage' => $aq->pourcentage
                ];
            })->toArray();

            // Delete old acquereurs
            foreach ($old_aquereurs as $aq) {
                $aq->forceDelete();
            }

            // Store new acquereurs
            $clientController = new ClientController();
            $clientRequest = new StoreClientRequest();
            $aquereurController = new AquereurController();
            $aquereurRequest = new StoreAquereurRequest();

            $dataArray_clients = json_decode($request->input('clients'), true);
            $dataArrayString = $request->input('oldClients', '[]');
            $dataArray_oldClients = json_decode($dataArrayString, true);

            $new_aquereurs = [];

            if ($dataArray_clients) {
                foreach ($dataArray_clients as $clientInfo) {
                    $clientInfo['projet_id'] = $reservation->projet_id;
                    $clientRequest->merge($clientInfo);
                    $clientData = $clientController->store($clientRequest);

                    $dataAquereur = [
                        'pourcentage' => $clientInfo['pourcentage'],
                        'client_id' => $clientData->id,
                        'reservation_id' => $reservation->id,
                    ];
                    $aquereurRequest->merge($dataAquereur);
                    $aquereurController->store($aquereurRequest);

                    // Add to new acquereurs array
                    $new_aquereurs[] = [
                        'client_id' => $clientData->id,
                        'nom' => $clientData->nom,
                        'prenom' => $clientData->prenom,
                        'pourcentage' => $clientInfo['pourcentage']
                    ];
                }
            }

            if ($dataArray_oldClients) {
                foreach ($dataArray_oldClients as $clientInfo) {
                    // Get existing client
                    $client = Client::on('temp')->find($clientInfo['id']);

                    $dataAquereur = [
                        'pourcentage' => $clientInfo['pourcentage1'] ?? $clientInfo['pourcentage'] ?? 0,
                        'client_id' => $clientInfo['id'],
                        'reservation_id' => $reservation->id,
                    ];
                    $aquereurRequest->merge($dataAquereur);
                    $aquereurController->store($aquereurRequest);

                    // Add to new acquereurs array
                    $new_aquereurs[] = [
                        'client_id' => $clientInfo['id'],
                        'nom' => $client->nom,
                        'prenom' => $client->prenom,
                        'pourcentage' => $clientInfo['pourcentage1'] ?? $clientInfo['pourcentage'] ?? 0
                    ];
                }
            }

            // Extract just the essential comparison data
            $old_client_data = collect($old_aquereurs_data)->map(function($aq) {
                return [
                    'client_id' => $aq['client_id'],
                    'pourcentage' => $aq['pourcentage']
                ];
            })->sortBy('client_id')->values()->toArray();

            $new_client_data = collect($new_aquereurs)->map(function($aq) {
                return [
                    'client_id' => $aq['client_id'],
                    'pourcentage' => $aq['pourcentage']
                ];
            })->sortBy('client_id')->values()->toArray();

            // Only create history if clients or percentages changed
            if ($old_client_data != $new_client_data) {
                $changes['acquereurs'] = [
                    'old' => $old_aquereurs_data,
                    'new' => $new_aquereurs
                ];
            }

            // Edit attachments
            $user_societes = User::where('id', $userAuth->user_id_origin)->first();
            $societe = Societe::findOrfail($user_societes->societe_id);

            if ($request->file('files_reservation')) {
                // Delete old attachments
                $pjController = new PiecesJointeController();
                $old_files = $pjController->getFilesUsingReservationId($id, $societe);
                $pjController->destoryFileUsingReservationId($id, $request->input('code_reservation'), $societe);

                $new_files = [];
                foreach ($request->file('files_reservation') as $file) {
                    $piecesJointeController = new PiecesJointeController();
                    $pieceJointeRequest = new StorePiecesJointeRequest();

                    // Get file name
                    $Myfile = $file->getClientOriginalName();
                    $fileType = $file->getClientOriginalExtension();

                    // Move file to destination directory
                    $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/reservations/' . $reservation->code_reservation);
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

                    // History
                    $new_files[] = $Myfile;
                }

                // Add file changes to changes array
                $changes['files'] = [
                    'old' => $old_files->pluck('fichier')->toArray(),
                    'new' => $new_files
                ];
            }
        }

        // Store history with all changes
        if (!empty($changes)) {
            $histo = new HistoReservation();
            $histo->setConnection('temp');
            $histo->reservation_id = $reservation->id;
            $histo->user_id = $userAuth->id;
            $histo->bien_id = $request->input('bien_id');
            $histo->action = 3;
            $histo->description = json_encode($changes);
            $histo->save();
        }

        DB::connection('temp')->commit();
        return response()->json(['reservation' => $reservation], 200);

    } catch (\Exception $e) {
        DB::connection('temp')->rollBack();
        \Log::error('Reservation update error: ' . $e->getMessage());
        return response()->json(['error' => 'Update failed: ' . $e->getMessage()], 500);
    }
}
    public function relancer_reservation($id, Request $request)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
             $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();
            $reservation = Reservation::on('temp')->findOrFail($id);
            $reservation->statut = StatutReservationEnum::En_Attente->value;
            if( $reservation->save()){
                //store action relance rejete
                $histo = new HistoReservation();
                $histo->setConnection('temp');
                $histo->reservation_id = $id;
                $histo->user_id = $userAuth->id;
                $histo->bien_id = $reservation->bien_id;
                $histo->action = 5;//REJET-RELANCE EN COURS
                $histo->description = null;
                $histo->save();
            Config::set('broadcasting.default', 'pusher_3');
            //notifiction to admin de valider dossier d reservation user_id=>null
            $data_notif = [
                'lien' => '/ventes/reservations/'.$id,
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
            Bien_Helper::libererBien($reservation->bien_id, null, null,false);
            //apres liberation de bien ==> set les visites reservtion perdu
            $visites_vendu=Visite::on('temp')->where('bien_id',$reservation->bien_id)->where('statut','2')->where('etat',1)->get();
            if(count($visites_vendu)>0){
                foreach($visites_vendu as $visite){
                    $visite->statut='4';
                    $visite->save();
                }
            }

            //avance et encaissements
            $avanceController = new AvanceController();
            $avanceController->destoryUsingReservationId($id);
            $tvaColletes = new ComptabiliteController();
            $tvaColletes->destroyTvaCollectesByReservationId($id);
            $aquereurController = new AquereurController();
            $aquereurController->destroyAquerreursByReservationId($id);
            $pjController = new PiecesJointeController();
            $pjController->destoryFileUsingReservationId($id, $reservation->code_reservation,$societe);
            $notif = new NotificationController();
            $notif->destory_force_by_column_id('reservation', $id);
             //desistements
             $desistements=Desistement::on('temp')->where(function ($query)use ($id){
                $query->where('reservation_id',$id)
                    ->orwhere('reservation_id_new',$id);})
                ->get();
                if(count($desistements)>0){

                    //biens desistements_id
                    foreach($desistements as $des){
                         $biens=Bien::on('temp')->where('desistement_id',$des->id)->get();
                         if(count($biens)>0){
                            foreach($biens as $bi){
                                $bi->setConnection('temp');
                                $bi->desistement_id=null;
                                $bi->save();
                            }
                         }

                        if($des->penalite_desistement!=null){
                            $des->penalite_desistement->delete();
                        }
                        if(count($des->remboursement)>0){
                            foreach($des->remboursement as $remb){
                                $remb->delete();
                            }
                        }
                        if(count($des->aquereurs_desisteurs)>0){
                            foreach($des->aquereurs_desisteurs as $aq){
                                $aq->delete();
                            }
                        }
                        if(count($des->aquereurs_non_desisteurs)>0){
                            foreach($des->aquereurs_non_desisteurs as $aqn){
                                $aqn->delete();
                            }
                        }
                        if(count($des->aquereurs_profits)>0){
                            foreach($des->aquereurs_profits as $aqpr){
                                $aqpr->delete();
                            }
                        }
                        if(count($des->aquereurs_partiel)>0){
                            foreach($des->aquereurs_partiel as $aqp){
                                $aqp->delete();
                            }
                        }
                        if(count($des->nouvel_aquereurs_desistements)>0){
                            foreach($des->nouvel_aquereurs_desistements as $n_aq){
                                $n_aq->delete();
                            }
                        }
                        if(count($des->Piece_jointes)>0){
                            foreach($des->Piece_jointes as $pj_d){
                                $pj_d->delete();
                            }
                        }
                        $hdes=HistoriqueDesistement::on('temp')->where('desistement_id',$des->id)->get();
                        if(count($hdes)>0){
                           foreach($hdes as $hbi){
                               $hbi->delete();
                           }
                        }


                        $hbiens=HistoriqueBien::on('temp')->where('desistement_id',$des->id)->get();
                        if(count($hbiens)>0){
                           foreach($hbiens as $hbi){
                               $hbi->delete();
                           }
                        }

                        $avances=Avance::on('temp')->where('desistement_id',$des->id)->get();
                        if(count($avances)>0){
                           foreach($avances as $av){
                               $av->setConnection('temp');
                               $av->desistement_id=null;
                               $av->save();
                           }
                        }
                        $pre=PreReservation::on('temp')->where('desistement_id',$des->id)->get();
                        if(count($pre)>0){
                           foreach($pre as $hbi){
                               $hbi->delete();
                           }
                        }

                        $des->delete();
                    }
                    //
                }

            $traitement_appels=TraitementAppel::on('temp')->where('reservation_id',$id)->get();
            if(count($traitement_appels)>0){
                foreach($traitement_appels as $tr_ap){
                    $tr_ap->delete();
                }
            }
            $histo_b=HistoriqueBien::on('temp')->where('reservation_id',$id)->get();
            if(count($histo_b)>0){
                foreach($histo_b as $h_id){
                    $h_id->delete();
                }
            }
            $histo_r=HistoReservation::on('temp')->where('reservation_id',$id)->get();
            if(count($histo_r)>0){
                foreach($histo_r as $h_r){
                    $h_r->delete();
                }
            }
            $st_r=StatutReservation::on('temp')->where('reservation_id',$id)->get();
            if(count($st_r)>0){
                foreach($st_r as $st){
                    $st->delete();
                }
            }
            $rdv=Rendez_vous::on('temp')->where('reservation_id',$id)->get();
            if(count($rdv)>0){
                foreach($rdv as $rd){
                    $rd->delete();
                }
            }
            $comp=Compromis_vente::on('temp')->where('reservation_id',$id)->get();
            if(count($comp)>0){
                foreach($comp as $c){
                    $c->delete();
                }
            }
            $cont=Contrat_vente::on('temp')->where('reservation_id',$id)->get();
            if(count($cont)>0){
                foreach($cont as $cn){
                    $cn->delete();
                }
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
                //Validation
                //store histo valide
                $histo = new HistoReservation();
                $histo->setConnection('temp');
                $histo->reservation_id = $id;
                $histo->user_id = $userAuth->value('id');
                $histo->bien_id = $reservation->bien_id;
                $histo->action = 2;//Validation
                $histo->description = null;
                $histo->save();
                //store new notification validé
                Config::set('broadcasting.default', 'pusher_3');
                $data_notif = [
                    'lien' => '/ventes/reservations/' . $id,
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
                //store histo rejete
                 $histo = new HistoReservation();
                $histo->setConnection('temp');
                $histo->reservation_id = $id;
                $histo->user_id = $userAuth->value('id');
                $histo->bien_id = $reservation->bien_id;
                $histo->action = 4;//Rejet
                $histo->description = null;
                $histo->save();

                Config::set('broadcasting.default', 'pusher_3');
                $data_notif = [
                    'lien' => '/ventes/reservations/' . $id,
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
