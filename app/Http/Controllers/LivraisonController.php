<?php

namespace App\Http\Controllers;

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

class LivraisonController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    /**************************************RDV**********************************************/
    public function get_rdvs_reservation($reservation_id, Request $request)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);
            $data = Rendez_vous::on('temp')
                ->where('reservation_id', $reservation_id)
                ->select('rendez_vous.*')->orderBy('created_at', 'desc')->get();
            $last_rdv = $data->take(1);
            $data_p = PaginationHelper::paginate_array(array_slice($data->toArray(), 1), $perPage, $page, $request->url());
            $reservation = Reservation::on('temp')->findorfail($reservation_id);
            return response()->json(['last_rdv' => $last_rdv, 'historiques' => $data_p, 'etat_res' => $reservation->etat, 'contrat_vente' => $reservation->contrat_vente], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }

    }

    public function store_rdv_reservation($id, Request $request)
    {

        if (RoleHelper::ACSup()) {

            $user = Auth::user();
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $rdv = new Rendez_vous();
            $rdv->setConnection('temp');
            $rdv->reservation_id = $id;
            $rdv->rdv = $request->rdv;
            $rdv->type = $request->type;
            $rdv->user_id = $userAuth->value('id');
            if (RoleHelper::AdminSup()) {
                $rdv->date_validation = Carbon::now();
                $rdv->user_id_valider = $userAuth->value('id');
                $rdv->statut = '1';
            } else {
                $rdv->statut = '0';

            }
            if ($rdv->save()) {
                if (RoleHelper::Com()) {
                    Config::set('broadcasting.default', 'pusher_5');
                    //6 demande validation rdv avec notaire
                    broadcast(new NotifMenuEvent(6));
                    //store new notification a validé

                    $data_notif = [
                        'lien' => '/reservations/show/' . $id,
                        'date' => Carbon::now(),
                        'type' => 22,
                        'description' => 'Demande validation rdv',
                        'reservation_id' => $id,
                        'projet_id' => $rdv->reservation->projet_id,
                        'role' => RoleEnum::ADMIN->value,
                    ];
                    Config::set('broadcasting.default', 'pusher_3');
                    $notif_helper = new NotificationHelper();
                    $notif_helper->storeNotification($request->merge($data_notif));
                    broadcast(new NotificationEvent($id));
                }
            }
            return response()->json(['message' => 'le rdv est ajouter.'], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }
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
                            'lien' => '/reservations/show/' . $id,
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
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $rdv = Rendez_vous::on('temp')->findorfail($rdv_id);

            if ($rdv->delete()) {
                $notification = Notification::on('temp')->where('reservation_id', $rdv->reservation_id)->where('type', 22)->orderBy('created_at', 'DESC')->get();
                if (count($notification) > 0) {
                    foreach ($notification as $nt) {
                        $nt->delete();
                    }
                }
            }
            return response()->json(['message' => 'rdv deleted'], 200);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function traiter_rdv_reservation($id, Request $request)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $rdv = Rendez_vous::on('temp')->findOrFail($id);
            $rdv->setConnection('temp');
            $rdv->statut = $request->statut;
            $rdv->user_id_valider = $userAuth->value('id');
            $rdv->date_validation = Carbon::now();
            if ($request->statut == 2) {
                $rdv->commentaire = $request->commentaire;
            }
            if ($rdv->save()) {
                if ($request->statut == 1) {
                    //store new notification validé
                    Config::set('broadcasting.default', 'pusher_3');
                    $data_notif = [
                        'lien' => '/reservations/show/' . $rdv->reservation_id,
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
                        'lien' => '/reservations/show/' . $rdv->reservation_id,
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

            }

            return response()->json(['message' => 'données enregistrés avec succès.'], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }

    /**************************************************Compromis_vente***********************************/

    public function store_compromis_vente($id, Request $request)
    {

        if (RoleHelper::ACSup()) {

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
                        'lien' => '/reservations/show/' . $id,
                        'date' => date('Y-m-d', strtotime($request->date_echeance . ' - 3 days')),
                        'type' => 26,
                        'description' => 'compromis bientot expirer',
                        'reservation_id' => $comp->reservation_id,
                        'projet_id' => $comp->reservation->projet_id,
                        'user_id' => $comp->reservation->user->user_id_origin,

                    ];
                    $notif_helper = new NotificationHelper();
                    $notif_helper->storeNotification($request->merge($data_notif));
                    broadcast(new NotificationEvent($comp->id));
                }
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

        if (RoleHelper::ACSup()) {
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

            $compromis = Compromis_vente::on('temp')->where('reservation_id', $id)->orderby('created_at', 'desc')->first();
            if ($compromis != null) {
                $compromis_annule_count = Compromis_vente::on('temp')->where('reservation_id', $compromis->reservation_id)->onlyTrashed()->orderby('created_at', 'desc')->count();

            } else {
                $compromis_annule_count = 0;
            }
            $res = new ReservationController();
            $reservation = $res->show($id);
            return response()->json(['compromis' => $compromis, 'compromis_annule_count' => $compromis_annule_count, 'reservation' => $reservation], 200);
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
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            if ($request->hasFile('fichier_scanner')) {

                $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
                $societe = Societe::findOrfail($user_societes->societe_id);
                $comp = Compromis_vente::on('temp')->findOrfail($request->input("comp_id"));
                $comp->setConnection('temp');

                // Récupérer le nom du fichier
                $comp->compromis_signee = $request->file('fichier_scanner')->getClientOriginalName();
                $directory = public_path('Docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/compromis_vente');
                File::makeDirectory($directory, 0755, true, true);
                $request->file('fichier_scanner')->move($directory, $request->file('fichier_scanner')->getClientOriginalName());

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
            $contrat = Contrat_vente::on('temp')->where('reservation_id', $id)->orderby('created_at', 'desc')->first();
            $res = new ReservationController();
            $reservation = $res->show($id);
            return response()->json(['contrat' => $contrat, 'reservation' => $reservation], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function store_contrat_vente($id, Request $request)
    {

        if (RoleHelper::ACSup()) {

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
            if ($request->commentaire == "null") {
                $cont->commentaire = null;
            } else {
                $cont->commentaire = $request->commentaire;
            }
            if ($cont->save()) {

                return response()->json(['cont' => $cont->id], 200);

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

        if (RoleHelper::ACSup()) {
            $user = Auth::user();
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $comp = Contrat_vente::on('temp')->withTrashed()->findOrFail($id);

            $comp->date_sign_client = $request->date_sign_client;
            $comp->date_sign_mo = $request->date_sign_mo;
            $comp->date_enreg = $request->date_enreg;
            if ($request->commentaire == "null") {
                $comp->commentaire = null;
            } else {
                $comp->commentaire = $request->comment;
            }
            $comp->user_id = $userAuth->value('id');
            if ($comp->save()) {

                return response()->json(['contrar_id' => $comp->id], 200);

            }

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }

    public function scanner_contrat(Request $request)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            if ($request->hasFile('fichier_scanner')) {

                $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
                $societe = Societe::findOrfail($user_societes->societe_id);
                $comp = Contrat_vente::on('temp')->findOrfail($request->input("contrat_id"));
                $comp->setConnection('temp');
                $codeReservation = $comp->reservation->code_reservation;

                // Récupérer le nom du fichier
                $comp->piece_jointe = $request->file('fichier_scanner')->getClientOriginalName();
                $directory = public_path('Docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/contrat_vente' . '/' . $codeReservation);
                File::makeDirectory($directory, 0755, true, true);
                $request->file('fichier_scanner')->move($directory, $request->file('fichier_scanner')->getClientOriginalName());

                if (!$comp->save()) {
                    return response()->json(['error' => 'Échec de scanner les fichiers'], 500);
                }
            }

            return response()->json(['success' => 'Fichiers scannés avec succès'], 200);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
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
