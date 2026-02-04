<?php


namespace App\Http\Controllers;

use App\Events\Rendez_vous_Prop;
use App\Enum\RoleEnum;
use App\Enum\StatutRdvEnum;
use App\Enum\StatutReservationEnum;
use App\Events\NotificationEvent;
use App\Events\NotifMenuEvent;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\NotificationHelper;
use App\Http\Helpers\PaginationHelper;
use App\Http\Helpers\RoleHelper;
use App\Models\Compromis_vente;
use App\Models\Contrat_vente;
use App\Models\Notification;
use App\Models\Rendez_vous;
use App\Models\Reservation;
use App\Models\Societe;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Api\V1\ReservationController;
use App\Models\CreneauxOccupes;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Events\RdvEvent;
use App\Events\AttestationVenteEvent;
use App\Events\ContratVenteEvent;
use App\Models\Aquereur;
use App\Models\HistoReservation;


use App\Models\StatutClient;

use DB;

class LivraisonController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    /**************************************RDV**********************************************/
    public function get_rdvs_reservation($reservation_id, Request $request)
    {
        if (RoleHelper::ACSup()||RoleHelper::Notaire()||RoleHelper::RespoLivraison()||RoleHelper::RespoCommercial()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);
            $data = Rendez_vous::on('temp')
                ->where('reservation_id', $reservation_id)
                ->select('rendez_vous.*')->orderBy('created_at', 'desc')->get();
           // $last_rdv = $data->take(1);
            //$data_p = PaginationHelper::paginate_array(array_slice($data->toArray(), 1), $perPage, $page, $request->url());
            //'historiques' => $data_p
            //'last_rdv' => $last_rdv,
            return response()->json(['rdv'=>$data], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }

    }
    // creneau occupes rdv
        public function getCreneauxOccupes(Request $request)
        {

            DatabaseHelper::Config();

            // Ensuite vérifiez le rôle
            if (!RoleHelper::ACSup()&&!RoleHelper::Notaire()&&!RoleHelper::RespoLivraison()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            $start = Carbon::createFromTimestamp($request->input('start')/1000);
            $end = Carbon::createFromTimestamp($request->input('end')/1000);

            return CreneauxOccupes::on('temp')->whereBetween('debut', [$start, $end])
                ->get()
                ->map(function ($creneau) {
                    return [
                        'id' => $creneau->id,
                        'debut' => $creneau->debut->format('Y-m-d H:i:s'),
                        'fin' => $creneau->fin->format('Y-m-d H:i:s'),
                        'disponible' => $creneau->disponible
                    ];
                });


        }


public function store_rdv_reservation($id, Request $request)
{
    $validated = $request->validate([
        'rdv' => 'required|date',
        'type' => 'required|string',
    ]);

    if (RoleHelper::ACSup()||RoleHelper::Notaire()) {
        $user = Auth::user();
        DatabaseHelper::Config();
        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();

        // Parse the appointment time
        $rdvTime = Carbon::parse($validated['rdv']);
        $heureRdv = $rdvTime->format('H:i');

        // Check business hours
        if ($heureRdv < '08:00' || $heureRdv > '18:00') {
            return response()->json([
                'message' => 'Hors des heures ouvrables',
                'errors' => ['rdv' => ['Veuillez choisir un créneau entre 8h et 18h']]
            ], 422);
        }

        // Format dates
        $dateDebut = $rdvTime->format('Y-m-d H:i:s');
        $dateFin = $rdvTime->copy()->addMinutes(30)->format('Y-m-d H:i:s'); // Add 30 minutes for end time

        // Check slot availability
        $creneau = CreneauxOccupes::on('temp')
            ->where('debut', $dateDebut)
            ->where('disponible', false)
            ->where('user_id','!=',$userAuth->id)
            ->first();

        if ($creneau) {
            return response()->json([
                'message' => 'Ce créneau n\'est pas disponible',
                'errors' => ['rdv' => ['Le créneau sélectionné n\'est pas disponible']]
            ], 422);
        }

        DB::transaction(function () use ($id, $request, $dateDebut, $dateFin, $userAuth) {
            // Create appointment
            $rdv = new Rendez_vous();
            $rdv->setConnection('temp');
            $rdv->reservation_id = $id;
            $rdv->rdv = $dateDebut;
            $rdv->type = $request->type;
            $rdv->user_id = $userAuth->id;
            $rdv->statut = '1';
            $rdv->save();
            $rdvId = $rdv->id;

            $cren = new CreneauxOccupes();
            $cren->setConnection('temp');
            $cren->debut = $dateDebut;
            $cren->fin = $dateFin; // Set the end time
            $cren->user_id= $userAuth->id;
            $cren->type=$request->type;
            $cren->reservation_id=$id;
            $cren->disponible = false;
            $cren->save();
            // Mark time slot as occupied
            //check the creneau propose ==> supprimer it ->where('debut',$dateDebut)->where('fin',$dateFin)
            $cren_prop=CreneauxOccupes::on('temp')->where('type',0)->where('user_id',$userAuth->id)->get();
             if (count($cren_prop) > 0) {
                    foreach($cren_prop as $cr){
                    $cr->forceDelete();
                    }
            }

        });
                //actualiser avances
                Config::set('broadcasting.default', 'pusher_8');
                // Broadcast event to all users subscribed to this reservation
                broadcast(new RdvEvent($id));
                  // send notif to notaire
                  if(RoleHelper::ACSup()){
                        $res=Reservation::on('temp')->findOrFail($id);
                        if($res->notaire_id!=null){
                             $data_notif = [
                            'lien' => '/ventes/reservations/' . $id,
                            'date' => $dateDebut,
                            'type' => 32,
                            'description' => 'nouveau rendez vous affecté',
                            'projet_id' => $res->projet_id,
                            'reservation_id' => $id,
                            'user_id' =>$res->notaire->user_id_origin,
                            'bien_id' => $res->bien_id,
                            'role' => RoleEnum::NOTAIRE->value,
                            ];
                            Config::set('broadcasting.default', 'pusher_3');
                            $notif_helper = new NotificationHelper();
                            $notif_helper->storeNotification($request->merge($data_notif));
                            broadcast(new NotificationEvent($id));
                        }
                  }


        $this->createStatutClientFor($id, $userAuth, $dateDebut);

        return response()->json(['message' => 'Rendez-vous enregistré avec succès'], 201);
    }
}

private function createStatutClientFor($id, $userAuth, $dateRdv)
    {
        try {
            // Get all aquereurs for this reservation
            $aquereurs = Aquereur::on('temp')
                ->where('reservation_id', $id)
                ->with('client')
                ->get();

            if ($aquereurs->isEmpty()) {
                \Log::warning('No aquereurs found : '  . ', Reservation ID: ' . $id);
                return;
            }

                // Get reservation details
                $reservation = Reservation::on('temp')->find($id);
                if (!$reservation) {
                    \Log::warning('Reservation not found for ID: ' . $id);
                    return;
                }

            // Get the last created rendez-vous for this reservation
            $rendezVous = Rendez_vous::on('temp')
                ->where('reservation_id', $id)
                ->latest()
                ->first();

            if (!$rendezVous) {
                \Log::warning('No rendez-vous found for reservation ID: ' . $id);
                return;
            }

              foreach ($aquereurs as $aquereur) {
                    $statutClient = new StatutClient();
                    $statutClient->setConnection('temp');
                    $statutClient->visite_id = null;
                    $statutClient->client_id = $aquereur->client_id;
                    $statutClient->statut = '6'; // Ajouter rdv
                    $statutClient->rdv_id = $rendezVous->id;
                    $statutClient->reservation_id = $id;
                    $statutClient->date_traitement = now();
                    $statutClient->user_id_traite = $userAuth->value('id');

                   // Format date and time for the comment
            $dateFormatted = Carbon::parse($dateRdv)->locale('fr')->isoFormat('dddd D MMMM YYYY');
            $heureFormatted = Carbon::parse($dateRdv)->format('H:i');

            // Build comment for rendez-vous
                    $rdvType = $rendezVous->type;
                    $typeText = '';

                    switch($rdvType) {
                        case 1:
                            $typeText = 'Attestation de Vente';
                            break;
                        case 2:
                            $typeText = 'Contrat de Vente';
                            break;
                        default:
                            $typeText = '';
                            break;
                    }

                    $comment = 'Rendez-vous planifié le ' . $dateFormatted . ' à ' . $heureFormatted .
                            ' - Type: ' . $typeText .
                            ' - Réservation: ' . $reservation->code_reservation .
                            ' - Bien: ' . $reservation->bien->propriete_dite_bien;


                // Add agent info if available
                if ($userAuth) {
                    $comment .= ' - Commercial: ' . $userAuth->name;
                }

            $statutClient->commentaire = $comment;
            $statutClient->save();
              }

        } catch (\Exception $e) {
            \Log::error('Failed to create StatutClient for avance payment: ' . $e->getMessage());
            // Don't throw error to avoid breaking the avance creation
        }
    }
// In your controller
public function updateReservationCreneau($reservation_id, Request $request)
{
    $validated = $request->validate([
        'rdv' => 'required|date',
    ]);

    if (!RoleHelper::ACSup()&&!RoleHelper::Notaire()&&!RoleHelper::RespoLivraison()) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $user = Auth::user();
    DatabaseHelper::Config();
    $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();

    $rdvTime = Carbon::parse($validated['rdv']);
    $dateDebut = $rdvTime->format('Y-m-d H:i:s');
    $dateFin = $rdvTime->copy()->addMinutes(30)->format('Y-m-d H:i:s');

    // Business hours check
    if ($rdvTime->format('H:i') < '09:00' || $rdvTime->format('H:i') > '17:00') {
        return response()->json([
            'message' => 'Hors des heures d\'ouverture',
            'errors' => ['rdv' => ['Veuillez choisir un horaire entre 9h et 17h']]

        ], 422);
    }

    return DB::connection('temp')->transaction(function () use ($reservation_id, $dateDebut, $dateFin, $userAuth, $validated) {
        // 1. Find and delete only the most recent creneau for this reservation
        $lastCreneau = CreneauxOccupes::on('temp')
         ->where('reservation_id', $reservation_id)
            ->where('type',0)//proposition
            ->where('user_id',$userAuth->id)//proposition
            ->latest('debut')
            ->first();

        if ($lastCreneau) {
            $lastCreneau->forceDelete();
        }

         // Vérifier si le créneau existe déjà

             $existingCreneau = CreneauxOccupes::on('temp')
                        //->where('user_id','!=', $userAuth->id)
                        ->where(function($query) use ($dateDebut,$dateFin) {
                            // Vérifie les chevauchements (sans inclure les créneaux qui se touchent)
                            $query->where('debut', '<',$dateDebut)   // début existant < fin nouveau
                                ->where('fin', '>', $dateFin);  // fin existant > début nouveau
                        })
                        ->first();

        if ($existingCreneau) {
            return response()->json([
                'error' => 'Ce créneau chevauche un créneau existant',
                'conflict_with' => [
                    'id' => $existingCreneau->id,
                    'debut' => $existingCreneau->debut->format('Y-m-d H:i:s'),
                    'fin' => $existingCreneau->fin->format('Y-m-d H:i:s')
                ]
            ], 409);
        }
        // 3. Create new creneau
          $cren = new CreneauxOccupes();
            $cren->setConnection('temp');
            $cren->debut = $dateDebut;
            $cren->fin = $dateFin; // Set the end time
            $cren->user_id= $userAuth->id;
            //type par defaut 0
            $cren->reservation_id=$reservation_id;
            $cren->disponible = false;
            $cren->save();


        // 4. Trigger Pusher event with the affected time range
        event(new Rendez_vous_Prop(
            $dateDebut,
            $userAuth->id,
            $reservation_id,
            $lastCreneau ? $lastCreneau->debut : null
        ));

        return response()->json([
            'message' => 'Time slot updated successfully',
            'new_slot' => $dateDebut,
            'old_slot' => $lastCreneau ? $lastCreneau->debut : null
        ]);
    });
}

/*private function genererCreneauxJournee(Carbon $date)
{
    $heureDebut = 8; // 8h
    $heureFin = 18; // 18h
    $dureeCreneau = 30; // minutes

    $debut = $date->copy()->addHours($heureDebut);
    $fin = $date->copy()->addHours($heureFin);

    $creneaux = [];

    while ($debut->lt($fin)) {
        $creneaux[] = [
            'debut' => $debut->format('Y-m-d H:i:s'),
            'fin' => $debut->copy()->addMinutes($dureeCreneau)->format('Y-m-d H:i:s'),
            'disponible' => true,
            'created_at' => now(),
            'updated_at' => now()
        ];

        $debut->addMinutes($dureeCreneau);
    }

    // Insertion en masse pour meilleure performance
    CreneauxOccupes::on('temp')->insert($creneaux);
}*/
    public function update_rdv_reservation($id, Request $request)
    {

        if (RoleHelper::ACSup()) {

            $user = Auth::user();
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $rdv = Rendez_vous::on('temp')->findOrFail($id);
            $set = 0;
            if ($rdv->statut == StatutRdvEnum::En_Attente->value) {
                $rdv->setConnection('temp');
                $rdv->rdv = $request->rdv;
                $rdv->user_id = $userAuth->value('id');
                $rdv->type = $request->type;
                $set = 1;
            } else {
                $this->store_rdv_reservation($rdv->reservation_id, $request);
                //delete ancien rdv
                $set = 2;
            }

            if ($rdv->save()) {
                if (RoleHelper::Com() && $set == 1) {
                    //suprimer last _notif
                    $notification = Notification::on('temp')->where('reservation_id', $rdv->reservation_id)->where('type', 22)->orderBy('created_at', 'DESC')->first();
                    if ($notification != null) {
                        $notification->delete();
                        Config::set('broadcasting.default', 'pusher_5');
                        //6 MODification rdv
                        broadcast(new NotifMenuEvent(6));
                        //store new notification a validé
                        $data_notif = [
                            'lien' => '/ventes/reservations/' . $id,
                            'date' => Carbon::now(),
                            'type' => 25,
                            'description' => 'modification du rdv',
                            'projet_id' => $rdv->reservation->projet_id,
                            'reservation_id' => $rdv->reservation_id,
                            'role' => RoleEnum::ADMIN->value,
                        ];
                        Config::set('broadcasting.default', 'pusher_3');
                        $notif_helper = new NotificationHelper();
                        $notif_helper->storeNotification($request->merge($data_notif));
                        broadcast(new NotificationEvent($id));
                    }
                }
                if ($set == 2) {
                    //store new nev et delete last rdv
                    $rdv->delete();
                }
            }
            return response()->json(['message' => 'le rdv est .' . $request->statut], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }
    public function get_rdv_notaire_menu($projet_id, Request $request)
    {

        if (Auth::guard('api')->check() && RoleHelper::ACSup()) {
            DatabaseHelper::Config();

            if (RoleHelper::AdminSup()) {
                //ADMIN
                $nb_rdv_notaire = Rendez_vous::on('temp')
                    ->join('reservations', 'rendez_vous.reservation_id', '=', 'reservations.id')
                    ->whereNull('reservations.deleted_at')
                    ->where('reservations.projet_id', $projet_id)
                    ->where('reservations.etat', 1)->where('rendez_vous.statut', '0')
                    ->count();

            } else
            if (RoleHelper::Com()) {
                $user = Auth::user();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                $nb_rdv_notaire = Rendez_vous::on('temp')
                    ->join('reservations', 'rendez_vous.reservation_id', '=', 'reservations.id')
                    ->whereNull('reservations.deleted_at')
                    ->where('reservations.projet_id', $projet_id)
                    ->where('rendez_vous.user_id', $userAuth->value('id'))
                    ->where('rendez_vous.statut', '0')
                    ->where('reservations.etat', 1)
                    ->count();

            }
            return response()->json(['nb' => $nb_rdv_notaire]);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }

    public function destroy_rdv_reservation($rdv_id)
    {
            if (RoleHelper::ACSup()||RoleHelper::Notaire()||RoleHelper::RespoLivraison()) {
            DatabaseHelper::Config();
            $rdv = Rendez_vous::on('temp')->findorfail($rdv_id);
            $dateDebut=$rdv->rdv;
            $res_id=$rdv->reservation_id;
            if ($rdv->delete()) {
                //destroy creneau occupes
            $cren_prop=CreneauxOccupes::on('temp')->where('debut',$dateDebut)->where('reservation_id',$res_id)->first();
                        if($cren_prop!=null){
                            $cren_prop->forceDelete();
                        }
                $notification = Notification::on('temp')->where('reservation_id', $rdv->reservation_id)->where('type', 22)->orderBy('created_at', 'DESC')->get();
                if (count($notification) > 0) {
                    foreach ($notification as $nt) {
                        $nt->delete();
                    }
                }
                  //actualiser avances
                Config::set('broadcasting.default', 'pusher_8');
                // Broadcast event to all users subscribed to this reservation
                broadcast(new RdvEvent($res_id));
            }

            return response()->json(['message' => 'rdv deleted'], 200);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function traiter_rdv_reservation($id, Request $request)
    {
        if (RoleHelper::ACSup()||RoleHelper::Notaire()||RoleHelper::RespoLivraison()) {
            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $rdv = Rendez_vous::on('temp')->findOrFail($id);
            $rdv->setConnection('temp');
            $rdv->statut = $request->statut;
            $rdv->user_id_valider = $userAuth->value('id');
            $rdv->date_validation = Carbon::now();
           // if ($request->statut == 3) {
                $rdv->commentaire = $request->commentaire;
            //}
            if ($rdv->save()) {
               /*** if ($request->statut == 2) {
                    //store new notification validé
                    Config::set('broadcasting.default', 'pusher_3');
                    $data_notif = [
                        'lien' => '/ventes/reservations/' . $rdv->reservation_id,
                        'date' => Carbon::now(),
                        'type' => 23,
                        'user_id' => $rdv->reservation->user->user_id_origin,
                        'description' => 'rdv validé',
                        'projet_id' => $rdv->reservation->projet_id,
                        'reservation_id' => $rdv->reservation_id,

                    ];
                    $notif_helper = new NotificationHelper();
                    $notif_helper->storeNotification($request->merge($data_notif));

                    broadcast(new NotificationEvent($id));
                    Config::set('broadcasting.default', 'pusher_5');
                    //6 rdv notaire
                    broadcast(new NotifMenuEvent(6));

                } else {
                    //store new notification rejeté
                    Config::set('broadcasting.default', 'pusher_3');
                    $data_notif = [
                        'lien' => '/ventes/reservations/' . $rdv->reservation_id,
                        'date' => Carbon::now(),
                        'type' => 24,
                        'user_id' => $rdv->reservation->user->user_id_origin,
                        'description' => 'rdv rejeté',
                        'projet_id' => $rdv->reservation->projet_id,
                        'reservation_id' => $rdv->reservation_id,

                    ];
                    $notif_helper = new NotificationHelper();
                    $notif_helper->storeNotification($request->merge($data_notif));
                    broadcast(new NotificationEvent($id));
                    Config::set('broadcasting.default', 'pusher_5');
                    //6 rdv notaire
                    broadcast(new NotifMenuEvent(6));

                }
                    **/

            }

            return response()->json(['message' => 'données enregistrés avec succès.'], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }

    /**************************************************Compromis_vente***********************************/

    public function store_compromis_vente($id, Request $request)
    {

        if (RoleHelper::ACSup()||RoleHelper::Notaire()||RoleHelper::RespoLivraison()) {
            $user = Auth::user();
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $comp = new Compromis_vente();
            $comp->setConnection('temp');
            $last_num_recu = Compromis_vente::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")
                ->get('num_recu')->first();
            if ($last_num_recu != null) {
                $n_recu = $last_num_recu->num_recu + 1;
                $comp->num_recu = '00' . $n_recu . '';
            } else {
                $comp->num_recu = '001';
            }
            $comp->reservation_id = $id;
            $comp->date_sign_client = $request->date_sign_client;
            $comp->date_sign_mo = $request->date_sign_mo;
            $comp->date_enreg = $request->date_enreg;
            $comp->duree_echeance = $request->duree_echeance;
            if ($request->date_echeance == "null") {
                $comp->date_echeance = null;
            } else {
                $comp->date_echeance = $request->date_echeance;
            }
            $comp->user_id = $userAuth->value('id');
            if ($request->commentaire == "null") {
                $comp->commentaire = null;
            } else {
                $comp->commentaire = $request->commentaire;
            }
            if ($comp->save()) {

                if ($comp->date_echeance != null) {
                    //msg compromis bientot s'exprimer
                    Config::set('broadcasting.default', 'pusher_3');

                    $data_notif = [
                        'lien' => '/ventes/reservations/' . $id,
                        'date' => date('Y-m-d', strtotime($request->date_echeance . ' - 3 days')),
                        'type' => 26,
                        'description' => 'compromis bientot expirer',
                        'reservation_id' => $comp->reservation_id,
                        'projet_id' => $comp->reservation->projet_id,
                        'user_id' => $comp->reservation->user->user_id_origin,
                        'bien_id' => $comp->reservation->bien_id,

                    ];
                    $notif_helper = new NotificationHelper();
                    $notif_helper->storeNotification($request->merge($data_notif));
                    broadcast(new NotificationEvent($comp->id));
                       if($comp->reservation->notaire_id!=null){
                             $data_notif = [
                            'lien' => '/ventes/reservations/' . $id,
                            'date' => date('Y-m-d', strtotime($request->date_echeance . ' - 3 days')),
                            'type' => 26,
                            'description' => 'compromis bientot expirer',
                            'reservation_id' => $comp->reservation_id,
                            'projet_id' => $comp->reservation->projet_id,
                            'user_id' =>$comp->reservation->notaire->user_id_origin,
                            'bien_id' => $comp->reservation->bien_id,
                            'role' => RoleEnum::NOTAIRE->value,
                            ];
                            $notif_helper = new NotificationHelper();
                            $notif_helper->storeNotification($request->merge($data_notif));
                            broadcast(new NotificationEvent($id));
                        }


                }
                 //pusher attestation de vente

                       /* //actualiser compromise de reservation
                    Config::set('broadcasting.default', 'pusher_9');
                    // Broadcast event to all users subscribed to this reservation
                    broadcast(new AttestationVenteEvent($id));*/
                return response()->json(['comp_id' => $comp->id], 200);

            } else {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

    }

    public function print_compromis($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $compromis = Compromis_vente::on('temp')->withTrashed()->with('reservation')->findOrfail($id);
            $bien = new VisiteController();
            $propriete = $bien->get_propriete_bien_concat($compromis->reservation->bien_id);
            $sum_avances_valides = 0;
            //si dossier desiste
            if ($compromis->reservation->etat > 1) {
                foreach ($reservation->avances_desist as $av) {
                    //avance validé
                    if ($av->statut == StatutReservationEnum::Validé->value) {
                        $sum_avances_valides += $av->montant;
                    }
                }
            } else {
                foreach ($compromis->reservation->avances as $av) {
                    //avance validé
                    if ($av->statut == StatutReservationEnum::Validé->value) {
                        $sum_avances_valides += $av->montant;
                    }
                }
            }
            return response()->json(['compromis' => $compromis, 'bien_propriete' => $propriete, 'sum_avances_valides' => $sum_avances_valides], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function show_compromis($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $compromis = Compromis_vente::on('temp')->with('reservation')->findOrfail($id);
            $compromis_annule_count = Compromis_vente::on('temp')->where('reservation_id', $compromis->reservation_id)->onlyTrashed()->count();
            return response()->json(['compromis' => $compromis, 'compromis_annule_count' => $compromis_annule_count], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function update_compromis($id, Request $request)
    {

        if (RoleHelper::ACSup()||RoleHelper::Notaire()||RoleHelper::RespoLivraison()) {
            $user = Auth::user();
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $comp = Compromis_vente::on('temp')->withTrashed()->findOrFail($id);
            $old_duree = $comp->duree;
            $old_date_echeance = $comp->date_echeance;
            $d_ech = null;
            if ($request->date_ech == "null") {
                $d_ech = null;
            } else {
                $d_ech = $request->date_ech;
            }
            //si user modifier duree ou date ==> comme prolongation va store new compromis et l'autre supprime
            if ($d_ech != $old_date_echeance) {

                //suprimer last _notif de bientot expirer
                $notification = Notification::on('temp')->where('reservation_id', $comp->reservation_id)->where('type', 26)->orderBy('created_at', 'DESC')->first();
                if ($notification != null) {
                    $notification->delete();
                }
                $data = [
                    'date_sign_client' => $request->date_c,
                    'date_sign_mo' => $request->date_mo,
                    'date_enreg' => $request->date_en,
                    'duree_echeance' => $request->duree == "null" ? null : $request->duree,
                    'date_echeance' => $d_ech,
                    'commentaire' => $request->comment,
                ];
                $xx = $this->store_compromis_vente($comp->reservation_id, $request->merge($data));
                $comp->delete();
                /* // Set the correct broadcasting connection for comporomis de vente
                config(['broadcasting.default' => 'pusher_9']);
                // Broadcast event
                broadcast(new AttestationVenteEvent($comp->reservation_id));*/
                return response()->json($xx);

            } else {

                $comp->date_sign_client = $request->date_c;
                $comp->date_sign_mo = $request->date_mo;
                $comp->date_enreg = $request->date_en;
                $comp->duree_echeance = $request->duree;
                $comp->date_echeance = $d_ech;
                if ($request->comment == "null") {
                    $comp->commentaire = null;
                } else {
                    $comp->commentaire = $request->comment;
                }
                $comp->user_id = $userAuth->value('id');
                if ($comp->save()) {
                    /*if($request->date_echeance!=$old_date_echeance){
                    $notification=Notification::on('temp')->where('reservation_id',$comp->reservation_id)->where('type',26)->orderBy('created_at', 'DESC')->first();
                    if($notification!=null){
                    $notification->delete();
                    }
                    //msg compromis bientot s'exprimer
                    Config::set('broadcasting.default', 'pusher_3');

                    $data_notif = [
                    'lien' => '/reservations/show/'.$id,
                    'date' => date('Y-m-d', strtotime($request->date_ech . ' - 3 days')),
                    'type' =>26,
                    'description' => 'compromis bientot expirer',
                    'reservation_id'=>$comp->reservation_id,
                    'projet_id'=>$comp->reservation->projet_id,
                    'user_id'=>$comp->reservation->user->user_id_origin,

                    ];
                    $notif_helper = new NotificationHelper();
                    $notif_helper->storeNotification($request->merge($data_notif));
                    broadcast(new NotificationEvent($comp->id));
                    }*/
                   /* config(['broadcasting.default' => 'pusher_9']);
                    // Broadcast event
                    broadcast(new AttestationVenteEvent($comp->reservation_id));*/
                    return response()->json(['comp_id' => $comp->id], 200);

                }
            }

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }

    public function get_compromis_by_reservation($id, Request $request)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);

            $compromis = Compromis_vente::on('temp')->without('reservation')->where('reservation_id', $id)->orderby('created_at', 'desc')->first();
            if ($compromis != null) {
                $compromis_annule_count = Compromis_vente::on('temp')->where('reservation_id', $compromis->reservation_id)->onlyTrashed()->orderby('created_at', 'desc')->count();

            } else {
                $compromis_annule_count = 0;
            }
            $res = new ReservationController();
          //  $reservation = $res->show($id);
            return response()->json(['compromis' => $compromis, 'compromis_annule_count' => $compromis_annule_count], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function get_compromis_annules_by_reservation($reservation_id, Request $request)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);
            $compromis_annule = Compromis_vente::on('temp')->where('reservation_id', $reservation_id)->onlyTrashed()->orderby('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            return response()->json(['compromis_annule' => $compromis_annule], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function scanner_compromis(Request $request)
    {
        if (RoleHelper::ACSup()||RoleHelper::Notaire()||RoleHelper::RespoLivraison()) {
            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get()->first();
            if ($request->hasFile('fichier_scanner')) {

                $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
                $societe = Societe::findOrfail($user_societes->societe_id);
                $comp = Compromis_vente::on('temp')->with('reservation')->findOrfail($request->input("comp_id"));
                $comp->setConnection('temp');

                // Récupérer le nom du fichier
                $comp->compromis_signee = $request->file('fichier_scanner')->getClientOriginalName();
                $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/compromis_vente');
                File::makeDirectory($directory, 0755, true, true);
                $request->file('fichier_scanner')->move($directory, $request->file('fichier_scanner')->getClientOriginalName());
                // Créer StatutClient après le scan
                    $this->createStatutClientForScanner($comp->reservation_id, $userAuth, 'compromis');
                     //store historique
                    $histo = new HistoReservation();
                    $histo->setConnection('temp');
                    $histo->reservation_id = $comp->reservation_id;
                    $histo->user_id = $userAuth->id;
                    $histo->action = 11;//Attesation de vente
                    $histo->bien_id=$comp->reservation->bien_id;
                    $histo->description = null;
                    $histo->save();
                      //actualiser compromise de reservation
                    Config::set('broadcasting.default', 'pusher_9');
                    // Broadcast event to all users subscribed to this reservation
                    broadcast(new AttestationVenteEvent($comp->reservation_id));
                if (!$comp->save()) {
                    return response()->json(['error' => 'Échec de scanner les fichiers'], 500);
                }
            }

            return response()->json(['success' => 'Fichiers scannés avec succès'], 200);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    /*************************************************Contrat de vente********************* */

    public function get_contrat_by_reservation($id, Request $request)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $contrat = Contrat_vente::on('temp')->where('reservation_id', $id)->without('reservation')->orderby('created_at', 'desc')->first();
            $res = new ReservationController();
            //$reservation = $res->show($id);
            //'reservation' => $reservation
            return response()->json(['contrat' => $contrat ], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function store_contrat_vente($id, Request $request)
    {
        if (RoleHelper::ACSup()||RoleHelper::Notaire()||RoleHelper::RespoLivraison()) {

            $user = Auth::user();
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $cont = new Contrat_vente();
            $cont->setConnection('temp');
            $last_num_recu = Contrat_vente::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")
                ->get('num_recu')->first();
            if ($last_num_recu != null) {
                $n_recu = $last_num_recu->num_recu + 1;
                $cont->num_recu = '00' . $n_recu . '';
            } else {
                $cont->num_recu = '001';
            }
            $cont->reservation_id = $id;
            $cont->date_sign_client = $request->date_sign_client;
            $cont->date_sign_mo = $request->date_sign_mo;
            $cont->date_enreg = $request->date_enreg;
            $cont->user_id = $userAuth->value('id');
                if ($request->commentaire == "" || $request->commentaire == "null") {
                $cont->commentaire = null;
            } else {
                $cont->commentaire = $request->commentaire;
            }
            if ($cont->save()) {
                 /*/actualiser contrat de vente
                    Config::set('broadcasting.default', 'pusher_10');
                    // Broadcast event to all users subscribed to this reservation
                    broadcast(new ContratVenteEvent($id));*/
                return response()->json(['contrat' => $cont], 200);
            } else {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

    }
    public function show_contrat($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $contrat = Contrat_vente::on('temp')->with('reservation')->findOrfail($id);
            $bien = new VisiteController();
            $propriete = $bien->get_propriete_bien_concat($contrat->reservation->bien_id);
            $sum_avances_valides = 0;
            //si dossier desiste
            if ($contrat->reservation->etat > 1) {
                foreach ($reservation->avances_desist as $av) {
                    //avance validé
                    if ($av->statut == StatutReservationEnum::Validé->value) {
                        $sum_avances_valides += $av->montant;
                    }
                }
            } else {
                foreach ($contrat->reservation->avances as $av) {
                    //avance validé
                    if ($av->statut == StatutReservationEnum::Validé->value) {
                        $sum_avances_valides += $av->montant;
                    }
                }
            }
            return response()->json(['contrat' => $contrat, 'bien_propriete' => $propriete, 'sum_avances_valides' => $sum_avances_valides], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function update_contrat($id, Request $request)
    {

        if (RoleHelper::ACSup()||RoleHelper::Notaire()||RoleHelper::RespoLivraison()) {
            $user = Auth::user();
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $comp = Contrat_vente::on('temp')->withTrashed()->findOrFail($id);

            $comp->date_sign_client = $request->date_sign_client;
            $comp->date_sign_mo = $request->date_sign_mo;
            $comp->date_enreg = $request->date_enreg;
            // Fix: Use consistent field name
        if ($request->comment == "" || $request->comment == "null") {
            $comp->commentaire = null;
        } else {
            $comp->commentaire = $request->comment;
        }
            $comp->user_id = $userAuth->value('id');
            if ($comp->save()) {
       /*//actualiser contrat de vente
                    Config::set('broadcasting.default', 'pusher_10');
                    // Broadcast event to all users subscribed to this reservation
                    broadcast(new ContratVenteEvent($comp->reservation_id));*/
                return response()->json(['contrar_id' => $comp->id], 200);

            }

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }

    public function scanner_contrat(Request $request)
    {
        if (RoleHelper::ACSup()||RoleHelper::Notaire()||RoleHelper::RespoLivraison()) {
            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get()->first();
            if ($request->hasFile('fichier_scanner')) {

                $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
                $societe = Societe::findOrfail($user_societes->societe_id);
                $comp = Contrat_vente::on('temp')->with('reservation')->findOrfail($request->input("contrat_id"));
                $comp->setConnection('temp');
                $codeReservation = $comp->reservation->code_reservation;

                // Récupérer le nom du fichier
                $comp->piece_jointe = $request->file('fichier_scanner')->getClientOriginalName();
                $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/contrat_vente' . '/' . $codeReservation);
                File::makeDirectory($directory, 0755, true, true);
                $request->file('fichier_scanner')->move($directory, $request->file('fichier_scanner')->getClientOriginalName());
                 // Créer StatutClient après le scan
                $this->createStatutClientForScanner($comp->reservation_id, $userAuth, 'contrat');
                 //store historique
                    $histo = new HistoReservation();
                    $histo->setConnection('temp');
                    $histo->reservation_id = $comp->reservation_id;
                    $histo->user_id = $userAuth->id;
                    $histo->action = 12;//Contrat de vente
                    $histo->bien_id=$comp->reservation->bien_id;
                    $histo->description = null;
                    $histo->save();
                //actualiser contrat de vente
                    Config::set('broadcasting.default', 'pusher_10');
                    // Broadcast event to all users subscribed to this reservation
                    broadcast(new ContratVenteEvent($comp->reservation_id));
                if (!$comp->save()) {
                    return response()->json(['error' => 'Échec de scanner les fichiers'], 500);
                }
            }

            return response()->json(['success' => 'Fichiers scannés avec succès'], 200);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    private function createStatutClientForScanner($reservationId, $userAuth, $documentType)
{
    try {
        // Get all aquereurs for this reservation
        $aquereurs = Aquereur::on('temp')
            ->where('reservation_id', $reservationId)
            ->with('client')
            ->get();

        if ($aquereurs->isEmpty()) {
            \Log::warning('No aquereurs found for Reservation ID: ' . $reservationId);
            return;
        }

        // Get reservation details
        $reservation = Reservation::on('temp')->find($reservationId);
        if (!$reservation) {
            \Log::warning('Reservation not found for ID: ' . $reservationId);
            return;
        }

        // Get document details based on type
        $document = null;
        $statutCode = '';
        $documentText = '';

        if ($documentType === 'compromis') {
            $document = Compromis_vente::on('temp')
                ->where('reservation_id', $reservationId)
                ->latest()
                ->first();
            $statutCode = '7'; // Statut pour Signer_Attestation_Vente
            $documentText = 'Attestation de Vente scanné';
        } elseif ($documentType === 'contrat') {
            $document = Contrat_vente::on('temp')
                ->where('reservation_id', $reservationId)
                ->latest()
                ->first();
            $statutCode = '8'; // Statut pour contrat scanné
            $documentText = 'Contrat de Vente scanné';
        }

        if (!$document) {
            \Log::warning('No ' . $documentType . ' found for reservation ID: ' . $reservationId);
            return;
        }

        foreach ($aquereurs as $aquereur) {
            $statutClient = new StatutClient();
            $statutClient->setConnection('temp');
            $statutClient->client_id = $aquereur->client_id;
            $statutClient->statut = $statutCode;

            // Set the appropriate document ID based on type
            if ($documentType == 'compromis') {
                $statutClient->compromis_vente_id = $document->id;
            } elseif ($documentType == 'contrat') {
                $statutClient->contrat_vente_id = $document->id;
            }

            $statutClient->reservation_id = $reservationId;
            $statutClient->date_traitement = now();
            $statutClient->user_id_traite = $userAuth->id;

            // Build comment
            $comment = $documentText . ' - ';

            if ($document->date_sign_client) {
                $dateSignClient = Carbon::parse($document->date_sign_client)
                    ->locale('fr')
                    ->isoFormat('dddd D MMMM YYYY');
                $comment .= 'Signé par client le ' . $dateSignClient;
            }

            if ($document->date_sign_mo) {
                $dateSignMo = Carbon::parse($document->date_sign_mo)
                    ->locale('fr')
                    ->isoFormat('dddd D MMMM YYYY');
                $comment .= ', Signé par maître d\'ouvrage le ' . $dateSignMo;
            }

            if ($document->date_enreg) {
                $dateEnreg = Carbon::parse($document->date_enreg)
                    ->locale('fr')
                    ->isoFormat('dddd D MMMM YYYY');
                $comment .= ', Enregistré le ' . $dateEnreg;
            }

            $comment .= ' - Réservation: ' . $reservation->code_reservation;
            $comment .= ' - Bien: ' . $reservation->bien->propriete_dite_bien;

            // Add agent info if available
            if ($userAuth) {
                $comment .= ' - Commercial: ' . $userAuth->name;
            }

            // Add document number if exists
            if ($document->num_recu) {
                $comment .= ' - N°: ' . $document->num_recu;
            }

            $statutClient->commentaire = $comment;
            $statutClient->save();
        }

    } catch (\Exception $e) {
        \Log::error('Failed to create StatutClient for ' . $documentType . ' scan: ' . $e->getMessage());
        // Don't throw error to avoid breaking the scan process
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
