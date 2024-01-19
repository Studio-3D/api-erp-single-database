<?php

namespace App\Http\Controllers;

use App\Enum\RoleEnum;
use App\Enum\StatutReservationEnum;
use App\Http\Helpers\Bien_Helper;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\NotificationHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreAquereurRequest;
use App\Http\Requests\StoreAvanceRequest;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\UpdateReservationRequest;
use App\Models\Aquereur;
use App\Models\Avance;
use App\Models\Bien;
use App\Models\HistoReservation;
use App\Models\PiecesJointe;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use \NumberFormatter;

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

            $reservations = Reservation::on('temp')
                ->joinSub($avances, 'avances_req', function ($join) {
                    $join->on('avances_req.reservation_id', '=', 'reservations.id');
                })
                ->select('reservations.*', 'avances_req.sum_avances')
                ->orderBy('reservations.created_at', 'desc')
                ->where('reservations.projet_id', $projet_id)
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json(['reservations' => $reservations], 200);
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
    public function store(StoreReservationRequest $request)
    {
        $user = Auth::user();
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            //test si le user connecte celui qui a  fait la proposition /etat du bien
            if ($request->bien_id != null) {
                $bien_prop = Bien::on('temp')->findorfail($request->bien_id);
                /* if($bien_prop->etat!=EtatBien::DISPONIBLE->name){
                return response()->json(['error' => 'Ce bien n\'est pas disponible'], 422);
                }*/
                if ($bien_prop->etat == 'ENCOURS_DE_PROPOSITION' && $bien_prop->is_proposed->user_id != $userAuth->value('id')) {
                    return response()->json(['error' => 'le bien choisi :' . $bien_prop->propriete_dite_bien . ' est en cours de proposition  par : ' . $bien_prop->is_proposed->user->name . ' ' . $bien_prop->is_proposed->user->prenom]);
                }

            }
            $reservation = new Reservation();
            $reservation->setConnection('temp');
            $reservation->nb_acquereurs = $request->nb_acquereurs;
            $reservation->code_reservation = $request->code_reservation;
            $reservation->prix = $request->prix;
            $reservation->mode_financement = $request->mode_financement;
            $reservation->date_reservation = $request->date_reservation;
            $reservation->date_limite_reservation = $request->date_limite_reservation;
            $reservation->commentaire = $request->commentaire;
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
                $reservation->statut = StatutReservationEnum::VALIDER->value;
            }
            if (RoleHelper::Com()) {
                $reservation->statut = StatutReservationEnum::EN_ATTENTE->value;
            }
            if ($request->verifierPourcentages === true) {
                if ($reservation->save()) {
                    if (RoleHelper::Com()) {
                        //notifiction to admin de valider dossier d reservation user_id=>null
                        NotificationHelper::storeNotification(
                            '/reservations/show/' . $reservation->id, null, 6, 'DEMANDE VALIDATION RESERVATION', null, RoleEnum::ADMIN->value, null, null, $reservation->projet_id, null, $reservation->id
                        );

                    }
                    $bienController = new BienController();
                    $bienController->reserverBien($reservation->bien_id, null, $reservation->id);

                    $clientController = new ClientController();
                    $clientRequest = new StoreClientRequest();
                    $aquereurController = new AquereurController();
                    $aquereurRequest = new StoreAquereurRequest();
                    if ($request->clients) {
                        foreach ($request->clients as $clientInfo) {
                            $clientRequest->merge($clientInfo);
                            $clientData = $clientController->store($clientRequest);
                            $dataAquereur = [
                                'pourcentage' => $clientInfo['pourcentage'],
                                'client_id' => $clientData->id,
                                'reservation_id' => $reservation->id,
                            ];
                            $aquereurRequest->merge($dataAquereur);
                            $aquereurController->store($aquereurRequest);
                        }}
                    if ($request->oldClients) {
                        foreach ($request->oldClients as $clientInfo) {
                            $dataAquereur = [
                                'pourcentage' => $clientInfo['pourcentage1'],
                                'client_id' => $clientInfo['id'],
                                'reservation_id' => $reservation->id,
                            ];
                            $aquereurRequest->merge($dataAquereur);
                            $aquereurController->store($aquereurRequest);
                        }}
                    $avanceController = new AvanceController();
                    $avanceRequest = new StoreAvanceRequest();
                    /* foreach ($request->avance as $avanceInfo){
                    $avanceRequest->merge($avanceInfo);
                    } */

                    $inWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
                    $mnt_lettre = $inWords->format($request->avance);
                    $dataAvance = [
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

                    ];
                    $avanceRequest->merge($dataAvance);
                    $avanceController->store($avanceRequest);
                    //****store piece jointe***
                    /* $piecesJointeController = new PiecesJointeController();
                    $pieceJointeRequest = new StorePiecesJointeRequest();

                    $datapieceJointe = [
                    'fichier' => $request->fichier,
                    'type' => $request->type,
                    'reservation_id' => $reservation->id,
                    'avance_id' => 3,

                    ];
                    $pieceJointeRequest->merge($datapieceJointe);
                    $piecesJointeController->store($pieceJointeRequest); */

                    //si client deja fait un appel perdu ensuite fait une reservation on lie reservation avec appel
                    //si bien deja desisté et le remboursmeent apres vente on envoi une notif Le Bien desisté est vendu
                }
                return response()->json(['reservation' => $reservation], 200);
            } else {

                return response()->json(['error' => 'la somme des pourcentage doit être 100%'], 422);

            }

        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Display the specified resource.
     */


    public function show($id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $reservation = Reservation::on('temp')->findOrFail($id);

             //get nom propriete _dite_bien concat
             $propriete=null;
             if($reservation->bien_id!=null){
                $bien=new VisiteController();
                 $propriete= $bien->get_propriete_bien_concat($reservation->bien_id);
             }
             $sum_avances_valides=0;
             $sum_avances=0;
             foreach($reservation->avances as $av){
                //avance validé
                if($av->statut==StatutReservationEnum::VALIDER->value){
                    $sum_avances_valides+=$av->montant;
                }
                /*//tous les avances !=refuse
                if($av->statut!=StatutReservationEnum::REFUSER->value){
                    $sum_avances+=$av->montant;
                }*/
             }
             $count_avances=Avance::on('temp')->where('reservation_id',$id)->count('id');

            return response()->json(['reservation' => $reservation,'propriete_dite_bien'=>$propriete,'sum_avances_valides'=>$sum_avances_valides,'count_avances'=>$count_avances], 200);
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
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $reservation = Reservation::on('temp')->findOrFail($id);
            $old_bien_id = $reservation->bien_id;
            //test si le user connecte celui qui a  fait la proposition /etat du bien
            if ($old_bien_id != $request->bien_id) {
                $bien_prop = Bien::on('temp')->findorfail($request->bien_id);
                /*if($bien_prop->etat!=EtatBien::DISPONIBLE->name){
                return response()->json(['error' => 'Ce bien n\'est pas disponible'], 422);
                }*/
                if ($bien_prop->etat == 'ENCOURS_DE_PROPOSITION' && $bien_prop->is_proposed->user_id != $userAuth->value('id')) {
                    return response()->json(['error' => 'le bien choisi :' . $bien_prop->propriete_dite_bien . ' est en cours de proposition  par : ' . $bien_prop->is_proposed->user->name . ' ' . $bien_prop->is_proposed->user->prenom]);
                }

            }

            $reservation->setConnection('temp');
            $reservation->nb_acquereurs = $request->nb_acquereurs;
            $reservation->code_reservation = $request->code_reservation;
            $reservation->prix = $request->prix;
            $reservation->mode_financement = $request->mode_financement;
            $reservation->date_reservation = $request->date_reservation;
            $reservation->date_limite_reservation = $request->date_limite_reservation;
            $reservation->commentaire = $request->commentaire;
            $reservation->prix_remise = $request->prix_remise;
            $numberToWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
            $prix_remise_lettre = $numberToWords->format($request->prix_remise);
            $reservation->prix_remise_lettre = $prix_remise_lettre;
            $reservation->prix_forfetaire = $request->prix_forfetaire;
            $prix_forfetaire_lettre = $numberToWords->format($request->prix_forfetaire);
            $reservation->prix_forfetaire_lettre = $prix_forfetaire_lettre;
            $reservation->bien_id = $request->bien_id;
            if ($request->verifierPourcentages === true) {
                if ($reservation->save()) {
                    if (RoleHelper::AdminSup()) {
                        //admin /sup admin peut changer le bien et les avances
                        if ($old_bien_id != $request->bien_id) {
                            //reserver new bien
                            $bienController = new BienController();
                            $bienController->reserverBien($request->bien_id, null, $reservation->id);
                            //liberer l'ancien bien
                            Bien_Helper::libererBien($old_bien_id, null);
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
                                NotificationHelper::storeNotification(
                                    '/reservations/show/' . $id, Carbon::now(), 8, 'admin a changé le bien du reservation', $comm->user_id_origin, null, null, null, $reservation->projet_id, null, $reservation->id
                                );
                            }

                        }
                    }

                    //delete aquereurs
                    $old_aquereurs = Aquereur::on('temp')->where('reservation_id', $id)->get();
                    foreach ($old_aquereurs as $aq) {
                        $aq->delete();
                    }
                    //store new aqueereur
                    $clientController = new ClientController();
                    $clientRequest = new StoreClientRequest();
                    $aquereurController = new AquereurController();
                    $aquereurRequest = new StoreAquereurRequest();
                    if ($request->clients) {
                        foreach ($request->clients as $clientInfo) {
                            $clientRequest->merge($clientInfo);
                            $clientData = $clientController->store($clientRequest);
                            $dataAquereur = [
                                'pourcentage' => $clientInfo['pourcentage'],
                                'client_id' => $clientData->id,
                                'reservation_id' => $reservation->id,
                            ];
                            $aquereurRequest->merge($dataAquereur);
                            $aquereurController->store($aquereurRequest);
                        }}
                    if ($request->oldClients) {
                        foreach ($request->oldClients as $clientInfo) {
                            $dataAquereur = [
                                'pourcentage' => $clientInfo['pourcentage1'],
                                'client_id' => $clientInfo['id'],
                                'reservation_id' => $reservation->id,
                            ];
                            $aquereurRequest->merge($dataAquereur);
                            $aquereurController->store($aquereurRequest);
                        }}

                    //****delete piece jointe***
                    //store new pieces jointes
                }
                return response()->json(['reservation' => $reservation], 200);
            } else {
                return response()->json(['error' => 'la somme des pourcentage doit être 100%'], 422);

            }
            return response()->json(['reservation' => $reservation], 200);
        }
        return response()->json(['error', 'Unauthorized'], 401);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $reservation = Reservation::on('temp')->findOrFail($id);
            //bien disponible
            Bien_Helper::libererBien($reservation->bien_id, null);
            $avanceController = new AvanceController();
            $avanceController->destoryUsingReservationId($id);
            $aquereurController = new AquereurController();
            $aquereurController->destroyAquerreursByReservationId($id);
            $pjController = new PiecesJointeController();
            $pjController->destoryFileUsingReservationId($id);
            $notif = new NotificationController();
            $notif->destory_force_by_column_id('reservation', $id);
            if ($reservation->delete()) {
                return response()->json(['message' => 'reservation supprimée avec succès.'], 200);
            } else {
                return response()->json(['message' => "reservation n'est supprimée."], 400);
            }
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function getAllInformationsReservation($id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $reservation = Reservation::on('temp')->findOrFail($id);
            $avances = Avance::on('temp')->where('reservation_id', $id)->get();
            $aquereurs = Aquereur::on('temp')->where('reservation_id', $id)->get();
            $pj = PiecesJointe::on('temp')->where('reservation_id', $id)->get();
            $data = [
                'reservation' => $reservation,
                'avances' => $avances,
                'aquereurs' => $aquereurs,
                'piecesjointes' => $pj,
            ];

            return response()->json(['data' => $data], 200);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function getReservationssByProjet($projet_id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $avances = Avance::on('temp')->select('reservation_id', DB::raw('SUM(avances.montant) as sum_avances'))
                ->groupby('reservation_id');

            $reservations = Reservation::on('temp')
                ->joinSub($avances, 'avances_req', function ($join) {
                    $join->on('avances_req.reservation_id', '=', 'reservations.id');
                })
                ->select('reservations.*', 'avances_req.sum_avances')
                ->orderBy('reservations.created_at', 'desc')
                ->where('reservations.projet_id', $projet_id)
                ->get();

            return response()->json(['reservations' => $reservations], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }

    public function get_Historiques_by_reservation($id,Request $request)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);
            $historiques = HistoReservation::on('temp')->where('reservation_id', $id)->orderby('created_at','desc')
                ->paginate($perPage, ['*'], 'page', $page);
            return response()->json(['historiques' => $historiques]);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }


    }

}
