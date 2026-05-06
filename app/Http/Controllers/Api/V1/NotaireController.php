<?php


namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Events\Rendez_vous_Prop;

use App\Enum\RoleEnum;
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
use App\Models\CreneauxOccupes;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Events\RdvEvent;
use App\Events\AttestationVenteEvent;
use App\Events\ContratVenteEvent;
use App\Models\Aquereur;
use App\Models\HistoReservation;



use DB;

class NotaireController extends Controller
{

public function get_notaires(Request $request, $projet_id)
{
    if (Auth::guard('api')->check()) {
        DatabaseHelper::Config();

        if (RoleHelper::AdminSup() || RoleHelper::RespoLivraison()) {
            // Get users with role 5 who are active and associated with the specific project
            $notaires = User::on('temp')
                ->where('role', 5)
                ->where('is_actif', 1)
                ->whereHas('projets', function ($query) use ($projet_id) {
                    $query->where('projet_id', $projet_id);
                })
                ->get();

            return response()->json(['notaires' => $notaires], 200);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }
}
    public function affecter_notaire($id, Request $request){
        if (Auth::guard('api')->check()) {
                $user = Auth::user();
                DatabaseHelper::Config();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();
                $nt = User::on('temp')->findOrFail($request->notaire_id);
                    if (RoleHelper::AdminSup()||RoleHelper::RespoLivraison()) {
                    $res=Reservation::on('temp')->findOrFail($id);
                    $res->notaire_id=$request->notaire_id;
                    $res->user_id_affecte = $userAuth->id;
                    $res->date_affectation_notaire=Carbon::now();

                    if( $res->save()){
                        // send notif to notaire
                            $data_notif = [
                                'lien' => '/ventes/reservations/' . $id,
                                'date' => Carbon::now(),
                                'type' => 33,
                                'description' => 'nouveau dossier affecté',
                                'projet_id' => $res->projet_id,
                                'reservation_id' => $id,
                                'user_id' =>$nt->user_id_origin,
                                'bien_id' => $res->bien_id,
                                'role' => RoleEnum::NOTAIRE->value,
                            ];
                            Config::set('broadcasting.default', 'pusher_3');
                            $notif_helper = new NotificationHelper();
                            $notif_helper->storeNotification($request->merge($data_notif));
                            broadcast(new NotificationEvent($id));
                    }
                return response()->json(['message' => 'is Done'], 200);
                }

            return response()->json(['error' => 'Unauthorized'], 401);
            }
    }


     public function get_new_dossier_notaire(Request $request,$projet_id)
    {
        if (RoleHelper::Notaire() || RoleHelper::RespoLivraison()) {
            $size = $request->input('size', config('app.default_item_number_perpage')); // Default size if not provided
            $page = $request->input('page', 1); // Default page if not provided

            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();

            $query = Reservation::on('temp')->withSum('avances','montant')->orderBy('created_at', 'desc')
                     ->where('projet_id', $projet_id)
                    ->whereDoesntHave('rdv')
                     ->where('etat', 1);

                  // Logique de filtrage par notaire
                if (RoleHelper::Notaire()) {
                    // Pour un notaire connecté : uniquement ses propres RDVs
                    $query->where('notaire_id', $userAuth->id);
                } elseif (RoleHelper::RespoLivraison() && $request->filled('notaire_id')) {
                    // Pour responsable livraison avec notaire spécifié : filtrer par ce notaire
                $query->where('notaire_id', $request->input('notaire_id'));
                }

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
            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

   public function get_rdvs_notaire(Request $request, $projet_id)
    {
        if (RoleHelper::Notaire() || RoleHelper::RespoLivraison()) {
            $size = $request->input('size', config('app.default_item_number_perpage'));
            $page = $request->input('page', 1);

            DatabaseHelper::Config();

            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();

            $query = Rendez_vous::on('temp')->with('reservation')
                ->whereHas('reservation', function ($q) use ($projet_id) {
                    $q->where('projet_id', $projet_id);
                });

            // Logique de filtrage par notaire
            if (RoleHelper::Notaire()) {
                // Pour un notaire connecté : uniquement ses propres RDVs
                $query->whereHas('reservation', function ($q) use ($userAuth) {
                    $q->where('notaire_id', $userAuth->id);
                });
            } elseif (RoleHelper::RespoLivraison() && $request->filled('notaire_id')) {
                // Pour responsable livraison avec notaire spécifié : filtrer par ce notaire
                $query->whereHas('reservation', function ($q) use ($request) {
                    $q->where('notaire_id', $request->input('notaire_id'));
                });
            }
            // Si responsable livraison sans notaire_id => pas de filtre supplémentaire

            // Optional filters
            if ($request->filled('code_reservation')) {
                $query->whereHas('reservation', function ($q) use ($request) {
                    $q->where('code_reservation', 'like', '%' . $request->input('code_reservation') . '%');
                });
            }

            if ($request->filled('bien')) {
                $query->whereHas('reservation.bien', function ($q) use ($request) {
                    $q->where('propriete_dite_bien', 'like', '%' . $request->input('bien') . '%');
                });
            }

            if ($request->filled('cc')) {
                $query->whereHas('reservation.user', function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->input('cc') . '%')
                            ->orWhere('prenom', 'like', '%' . $request->input('cc') . '%');
                    });
                });
            }

            if ($request->filled('date_start')) {
                $start = Carbon::parse($request->input('date_start'));
                $query->whereHas('reservation', function ($q) use ($start) {
                    $q->whereDate('date_reservation', '>=', $start);
                });
            }

            if ($request->filled('date_end')) {
                $end = Carbon::parse($request->input('date_end'));
                $query->whereHas('reservation', function ($q) use ($end) {
                    $q->whereDate('date_reservation', '<=', $end);
                });
            }

            // RDV date filters
            if ($request->filled('rdv_date_start')) {
                $start = Carbon::parse($request->input('rdv_date_start'));
                $query->whereDate('rdv', '>=', $start);
            }

            if ($request->filled('rdv_date_end')) {
                $end = Carbon::parse($request->input('rdv_date_end'));
                $query->whereDate('rdv', '<=', $end);
            }

            // RDV datetime filter (exact or near)
            if ($request->filled('rdv_datetime')) {
                $datetime = Carbon::parse($request->input('rdv_datetime'));
                // You can adjust this to search within a range if needed
                $query->whereDate('rdv', $datetime->toDateString())
                    ->whereTime('rdv', '>=', $datetime->copy()->subHour()->toTimeString())
                    ->whereTime('rdv', '<=', $datetime->copy()->addHour()->toTimeString());
            }

            // Status filter
            if ($request->filled('statut') && in_array($request->input('statut'), [1, 2, 3])) {
                $query->where('statut', $request->input('statut'));
            }

            // Order by: first status (1, 2, 3), then by rdv date (today's first, then future)
            $query->orderByRaw('CASE
                WHEN statut = 1 AND DATE(rdv) = CURDATE() THEN 1
                WHEN statut = 1 AND DATE(rdv) > CURDATE() THEN 2
                WHEN statut = 1 AND DATE(rdv) < CURDATE() THEN 3
                WHEN statut = 2 THEN 4
                WHEN statut = 3 THEN 5
                ELSE 6
            END')
            ->orderBy('rdv', 'asc');

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                $rdv = $query->paginate($size, ['*'], 'page', $page);

                $pagination = [
                    'currentPage' => $rdv->currentPage(),
                    'totalItems' => $rdv->total(),
                    'totalPages' => $rdv->lastPage(),
                ];

                $rdv = $rdv->items();

                return response()->json([
                    'data' => $rdv,
                    'pagination' => $pagination,
                ], 200);
            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }



  public function get_relances_notaire(Request $request, $projet_id)
    {
        if (RoleHelper::Notaire() || RoleHelper::RespoLivraison()) {
            $size = $request->input('size', config('app.default_item_number_perpage'));
            $page = $request->input('page', 1);

            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();

            $query = Rendez_vous::on('temp')->with('reservation')
                ->where('prochaine_relance', '!=', null)
                ->where('statut', 1) // Only pending/active relances
                ->whereHas('reservation', function ($q) use ($projet_id) {
                    $q->where('projet_id', $projet_id);
                });

            // Logique de filtrage par notaire
            if (RoleHelper::Notaire()) {
                // Pour un notaire connecté : uniquement ses propres relances
                $query->whereHas('reservation', function ($q) use ($userAuth) {
                    $q->where('notaire_id', $userAuth->id);
                });
            } elseif (RoleHelper::RespoLivraison() && $request->filled('notaire_id')) {
                // Pour responsable livraison avec notaire spécifié : filtrer par ce notaire
                $query->whereHas('reservation', function ($q) use ($request) {
                    $q->where('notaire_id', $request->input('notaire_id'));
                });
            }
            // Si responsable livraison sans notaire_id => pas de filtre supplémentaire

            // Optional filters
            if ($request->filled('code_reservation')) {
                $query->whereHas('reservation', function ($q) use ($request) {
                    $q->where('code_reservation', 'like', '%' . $request->input('code_reservation') . '%');
                });
            }

            if ($request->filled('bien')) {
                $query->whereHas('reservation.bien', function ($q) use ($request) {
                    $q->where('propriete_dite_bien', 'like', '%' . $request->input('bien') . '%');
                });
            }

            if ($request->filled('cc')) {
                $query->whereHas('reservation.user', function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->input('cc') . '%')
                            ->orWhere('prenom', 'like', '%' . $request->input('cc') . '%');
                    });
                });
            }

            if ($request->filled('client')) {
                $query->whereHas('reservation.aquereurs.client', function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('nom', 'like', '%' . $request->input('client') . '%')
                            ->orWhere('prenom', 'like', '%' . $request->input('client') . '%');
                    });
                });
            }

            if ($request->filled('date_start')) {
                $start = Carbon::parse($request->input('date_start'));
                $query->whereHas('reservation', function ($q) use ($start) {
                    $q->whereDate('date_reservation', '>=', $start);
                });
            }

            if ($request->filled('date_end')) {
                $end = Carbon::parse($request->input('date_end'));
                $query->whereHas('reservation', function ($q) use ($end) {
                    $q->whereDate('date_reservation', '<=', $end);
                });
            }

            // Relance date filters
            if ($request->filled('relance_date_start')) {
                $start = Carbon::parse($request->input('relance_date_start'));
                $query->whereDate('prochaine_relance', '>=', $start);
            }

            if ($request->filled('relance_date_end')) {
                $end = Carbon::parse($request->input('relance_date_end'));
                $query->whereDate('prochaine_relance', '<=', $end);
            }

            // Order by: today's relances first, then overdue, then future
            $query->orderByRaw('
                CASE
                    WHEN DATE(prochaine_relance) = CURDATE() THEN 1
                    WHEN DATE(prochaine_relance) < CURDATE() THEN 2
                    WHEN DATE(prochaine_relance) > CURDATE() THEN 3
                    ELSE 4
                END
            ')
            ->orderBy('prochaine_relance', 'asc'); // Then by time ascending

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                $relances = $query->paginate($size, ['*'], 'page', $page);

                $pagination = [
                    'currentPage' => $relances->currentPage(),
                    'totalItems' => $relances->total(),
                    'totalPages' => $relances->lastPage(),
                ];

                $relances = $relances->items();

                return response()->json([
                    'data' => $relances,
                    'pagination' => $pagination,
                ], 200);
            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }
    public function add_prochaine_relance($id, Request $request)
    {
        if (RoleHelper::Notaire()) {
            $user = Auth::user();
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();

            $rdv = Rendez_vous::on('temp')->findOrFail($id);
            $rdv->setConnection('temp');

            // Récupérer l'historique actuel ou initialiser un tableau vide
            $relancesHistory = $rdv->relances_history ?? [];

            // Ajouter l'ancienne prochaine_relance à l'historique si elle existe
            if ($rdv->prochaine_relance) {
                $relancesHistory[] = [
                    'date_programmee' => $rdv->prochaine_relance->format('Y-m-d H:i:s'),
                    'date_creation' => Carbon::now()->format('Y-m-d H:i:s'),
                    'user_id' => $userAuth->id,
                    'user_name' => $userAuth->name . ' ' . $userAuth->prenom,
                    'statut' => 'reprogrammee',
                    'raison' => $request->input('raison', null) // Optionnel: raison de la reprogrammation
                ];
            }

            // Mettre à jour la nouvelle date de relance
            $rdv->prochaine_relance = Carbon::parse($request->prochaine_relance)->format('Y-m-d H:i:s');
            $rdv->relances_history = $relancesHistory;

            if ($rdv->save()) {
                // Store new notification
                $data_notif = [
                    'lien' => '/ventes/reservations/' .$rdv->reservation_id,
                    'date' => Carbon::now(),
                    'type' => 34,
                    'description' => 'Relance Notaire',
                    'projet_id' => $rdv->reservation->projet_id,
                    'reservation_id' => $rdv->reservation_id,
                    'user_id' => $userAuth->user_id_origin,
                    'bien_id' => $rdv->reservation->bien_id,
                    'role' => RoleEnum::NOTAIRE->value,
                ];

                Config::set('broadcasting.default', 'pusher_3');
                $notif_helper = new NotificationHelper();
                $notif_helper->storeNotification($request->merge($data_notif));
                broadcast(new NotificationEvent($id));

                // Retourner la réponse
                return response()->json([
                    'message' => 'Prochaine relance programmée avec succès',
                    'rdv' => $rdv->fresh()
                ], 200);
            }

            return response()->json(['error' => 'Erreur lors de la sauvegarde'], 500);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function get_relances_history($id)
    {
        if (RoleHelper::Notaire()||RoleHelper::RespoLivraison()||RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $rdv = Rendez_vous::on('temp')->findOrFail($id);

            $relancesHistory = $rdv->relances_history ?? [];

            // Formater l'historique pour l'affichage
            $formattedHistory = array_map(function($relance) {
                return [
                    'date_programmee' => $relance['date_programmee'],
                    'date_creation' => $relance['date_creation'],
                    'user_name' => $relance['user_name'],
                    'statut' => $relance['statut'],
                    'raison' => $relance['raison'] ?? null,
                    'formatted_date_programmee' => Carbon::parse($relance['date_programmee'])->format('d/m/Y H:i'),
                    'formatted_date_creation' => Carbon::parse($relance['date_creation'])->format('d/m/Y H:i'),
                ];
            }, $relancesHistory);

            return response()->json([
                'relances_history' => $formattedHistory,
                'current_relance' => $rdv->prochaine_relance ? [
                    'date' => $rdv->prochaine_relance->format('Y-m-d H:i:s'),
                    'formatted_date' => $rdv->prochaine_relance->format('d/m/Y H:i'),
                ] : null
            ], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    /*******************************Attestation de Vente *********************** */
    /**********
     * "Non signé" = Réservations qui n'ont PAS d'attestation de vente (pas d'enregistrement dans la table compromis_vente)
     "Signé" = Attestations de vente qui ont compromis_signe != null
    */
 public function get_attestations_ventes(Request $request, $projet_id)
{
    if (RoleHelper::Notaire()||RoleHelper::RespoLivraison()) {
        $size = $request->input('size', config('app.default_item_number_perpage'));
        $page = $request->input('page', 1);

        DatabaseHelper::Config();

        $user = Auth::user();
        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();

        // Récupérer toutes les réservations du notaire
        $query = Reservation::on('temp')
            ->with([
                'bien.tranche',
                'bien.bloc',
                'bien.immeuble',
                'aquereurs.client',
                'compromis_vente'
            ])
            ->where('projet_id', $projet_id)
            //->where('notaire_id', $userAuth->id)
            ->where('statut', 1)
            ->where('etat', 1);

        if (RoleHelper::Notaire()) {
                    // Pour un notaire connecté : uniquement ses propres RDVs
                    $query->where('notaire_id', $userAuth->id);
        } elseif (RoleHelper::RespoLivraison() && $request->filled('notaire_id')) {
                    // Pour responsable livraison avec notaire spécifié : filtrer par ce notaire
                $query->where('notaire_id', $request->input('notaire_id'));
        }
        // Appliquer les filtres communs
        if ($request->filled('code_reservation')) {
            $query->where('code_reservation', 'like', '%' . $request->input('code_reservation') . '%');
        }

        if ($request->filled('bien')) {
            $query->whereHas('bien', function ($q) use ($request) {
                $q->where('propriete_dite_bien', 'like', '%' . $request->input('bien') . '%');
            });
        }

        // Filtre par client
        if ($request->filled('client')) {
            $clientSearch = $request->input('client');
            $query->whereHas('aquereurs.client', function ($q) use ($clientSearch) {
                $q->where(function ($q) use ($clientSearch) {
                    $q->where('nom', 'like', '%' . $clientSearch . '%')
                      ->orWhere('prenom', 'like', '%' . $clientSearch . '%');
                });
            });
        }

        // Filtre par téléphone
        if ($request->filled('telephone')) {
            $telephoneSearch = $request->input('telephone');
            $query->whereHas('aquereurs.client', function ($q) use ($telephoneSearch) {
                $q->where(function ($q) use ($telephoneSearch) {
                    $q->where('telephone_num1', 'like', '%' . $telephoneSearch . '%')
                      ->orWhere('telephone_num2', 'like', '%' . $telephoneSearch . '%');
                });
            });
        }

        // Date de réservation
        if ($request->filled('date_start')) {
            $startDate = Carbon::parse($request->input('date_start'));
            $query->whereDate('date_reservation', '>=', $startDate);
        }

        if ($request->filled('date_end')) {
            $endDate = Carbon::parse($request->input('date_end'));
            $query->whereDate('date_reservation', '<=', $endDate);
        }

        // Filtres spécifiques aux attestations (AVANT pagination pour efficacité)
        $statut = $request->input('statut', '');

        // Filtre par statut "signe" : seulement les réservations avec attestation signée
        if ($statut === 'signe') {
            $query->whereHas('compromis_vente', function ($q) {
                // compromis_signee est un varchar, donc vérifier qu'il n'est pas null et pas vide
                $q->whereNotNull('compromis_signee')
                  ->where('compromis_signee', '!=', null);
            });
        }
        // Filtre par statut "non_signe" : réservations sans attestation OU avec attestation non signée
        elseif ($statut === 'non_signe') {
            $query->where(function($q) {
                $q->whereDoesntHave('compromis_vente') // Pas d'attestation
                  ->orWhereHas('compromis_vente', function ($q2) {
                      // Attestation mais non signée (null ou vide)
                      $q2->whereNull('compromis_signee');
                  });
            });
        }

        // Filtres de dates pour les attestations (si demandés et si pas déjà filtré par statut)
        // Ces filtres s'appliquent seulement aux réservations avec attestation
        if (!$statut || $statut !== 'non_signe') {
            if ($request->filled('date_sign_client_start') || $request->filled('date_sign_client_end')) {
                $query->whereHas('compromis_vente', function ($q) use ($request) {
                    if ($request->filled('date_sign_client_start')) {
                        $startDate = Carbon::parse($request->input('date_sign_client_start'));
                        $q->whereDate('date_sign_client', '>=', $startDate);
                    }
                    if ($request->filled('date_sign_client_end')) {
                        $endDate = Carbon::parse($request->input('date_sign_client_end'));
                        $q->whereDate('date_sign_client', '<=', $endDate);
                    }
                });
            }

            if ($request->filled('date_sign_mo_start') || $request->filled('date_sign_mo_end')) {
                $query->whereHas('compromis_vente', function ($q) use ($request) {
                    if ($request->filled('date_sign_mo_start')) {
                        $startDate = Carbon::parse($request->input('date_sign_mo_start'));
                        $q->whereDate('date_sign_mo', '>=', $startDate);
                    }
                    if ($request->filled('date_sign_mo_end')) {
                        $endDate = Carbon::parse($request->input('date_sign_mo_end'));
                        $q->whereDate('date_sign_mo', '<=', $endDate);
                    }
                });
            }

            if ($request->filled('date_enreg_start') || $request->filled('date_enreg_end')) {
                $query->whereHas('compromis_vente', function ($q) use ($request) {
                    if ($request->filled('date_enreg_start')) {
                        $startDate = Carbon::parse($request->input('date_enreg_start'));
                        $q->whereDate('date_enreg', '>=', $startDate);
                    }
                    if ($request->filled('date_enreg_end')) {
                        $endDate = Carbon::parse($request->input('date_enreg_end'));
                        $q->whereDate('date_enreg', '<=', $endDate);
                    }
                });
            }

            if ($request->filled('date_echeance_start') || $request->filled('date_echeance_end')) {
                $query->whereHas('compromis_vente', function ($q) use ($request) {
                    if ($request->filled('date_echeance_start')) {
                        $startDate = Carbon::parse($request->input('date_echeance_start'));
                        $q->whereDate('date_echeance', '>=', $startDate);
                    }
                    if ($request->filled('date_echeance_end')) {
                        $endDate = Carbon::parse($request->input('date_echeance_end'));
                        $q->whereDate('date_echeance', '<=', $endDate);
                    }
                });
            }
        }

        // Pagination
        $reservations = $query->orderBy('created_at', 'desc')
            ->paginate($size, ['*'], 'page', $page);

        // Transformer les données pour le frontend
        $transformedData = $reservations->map(function ($reservation) {
            $attestation = $reservation->compromis_vente;

            if ($attestation) {
                // Déterminer si l'attestation est signée
                $compromisSignee = $attestation->compromis_signee;
                $isSigned = !empty($compromisSignee) && $compromisSignee !=null ;

                // Réservation avec attestation
                return [
                    'id' => $attestation->id,
                    'reservation_id' => $reservation->id,
                    'reservation' => $reservation,
                    'compromis_signe' => $compromisSignee, // Garder la valeur originale
                    'date_sign_client' => $attestation->date_sign_client,
                    'date_sign_mo' => $attestation->date_sign_mo,
                    'date_enreg' => $attestation->date_enreg,
                    'date_echeance' => $attestation->date_echeance,
                    'num_titre' => $attestation->num_titre,
                    'num_recu' => $attestation->num_recu,
                    'duree_echeance' => $attestation->duree_echeance,
                    'commentaire' => $attestation->commentaire,
                    'statut_type' => $isSigned ? 'signe' : 'non_signe_attestation',
                    'is_non_signed_reservation' => false,
                    'created_at' => $attestation->created_at,
                ];
            } else {
                // Réservation sans attestation
                return [
                    'id' => null,
                    'reservation_id' => $reservation->id,
                    'reservation' => $reservation,
                    'compromis_signe' => null,
                    'date_sign_client' => null,
                    'date_sign_mo' => null,
                    'date_enreg' => null,
                    'date_echeance' => null,
                    'num_titre' => null,
                    'num_recu' => null,
                    'duree_echeance' => null,
                    'commentaire' => null,
                    'statut_type' => 'non_signe',
                    'is_non_signed_reservation' => true,
                    'created_at' => $reservation->created_at,
                ];
            }
        });

        // Maintenant les filtres par statut sont déjà appliqués au niveau SQL,
        // mais on garde cette partie au cas où (filtrage PHP supplémentaire)
        $statut = $request->input('statut', '');
        if ($statut === 'signe') {
            $transformedData = $transformedData->filter(function ($item) {
                return $item['statut_type'] === 'signe';
            });
        } elseif ($statut === 'non_signe') {
            $transformedData = $transformedData->filter(function ($item) {
                return $item['statut_type'] === 'non_signe' || $item['statut_type'] === 'non_signe_attestation';
            });
        }

        // Pagination manuelle (si filtres PHP supplémentaires)
        $filteredData = $transformedData->values();
        $total = $filteredData->count();
        $paginatedData = $filteredData->slice(($page - 1) * $size, $size);

        $pagination = [
            'currentPage' => (int)$page,
            'totalItems' => $total,
            'totalPages' => ceil($total / $size),
        ];

        return response()->json([
            'success' => true,
            'data' => $paginatedData->values()->all(),
            'pagination' => $pagination,
            'total' => $total,
        ], 200);
    }

    return response()->json(['error' => 'Unauthorized'], 401);
}

    /*****************************Contrat Ventes******************* */

  /**********
     * "Non signé" = Réservations qui n'ont PAS contrat de vente (pas d'enregistrement dans la table contrat_vente)
     "Signé" = Contrat_vente qui ont compromis_signe != null
    */
        public function get_contrats_ventes(Request $request, $projet_id)
            {
                if (RoleHelper::Notaire() || RoleHelper::RespoLivraison()) {
                    $size = $request->input('size', config('app.default_item_number_perpage'));
                    $page = $request->input('page', 1);

                    DatabaseHelper::Config();

                    $user = Auth::user();
                    $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();

                    // Récupérer toutes les réservations du notaire
                    $query = Reservation::on('temp')
                        ->with([
                            'bien.tranche',
                            'bien.bloc',
                            'bien.immeuble',
                            'aquereurs.client',
                            'contrat_vente',
                            'avances_valides'
                        ])
                        ->withCount('avances_valides')
                        ->withSum('avances_valides', 'montant')
                        ->where('projet_id', $projet_id)
                        //->where('notaire_id', $userAuth->id)
                        ->where('statut', 1)
                        ->where('etat', 1)
                        // Condition : somme des avances validées >= prix de la réservation
                        ->whereHas('avances_valides', function($q) {
                            $q->select(DB::raw('SUM(montant) as total'))
                            ->havingRaw('total >= reservations.prix');
                        }, '>=', 1);
                    if (RoleHelper::Notaire()) {
                                // Pour un notaire connecté : uniquement ses propres RDVs
                                $query->where('notaire_id', $userAuth->id);
                    } elseif (RoleHelper::RespoLivraison() && $request->filled('notaire_id')) {
                                // Pour responsable livraison avec notaire spécifié : filtrer par ce notaire
                            $query->where('notaire_id', $request->input('notaire_id'));
                    }
                    // Appliquer les filtres communs
                    if ($request->filled('code_reservation')) {
                        $query->where('code_reservation', 'like', '%' . $request->input('code_reservation') . '%');
                    }

                    if ($request->filled('bien')) {
                        $query->whereHas('bien', function ($q) use ($request) {
                            $q->where('propriete_dite_bien', 'like', '%' . $request->input('bien') . '%');
                        });
                    }

                    // Filtre par client
                    if ($request->filled('client')) {
                        $clientSearch = $request->input('client');
                        $query->whereHas('aquereurs.client', function ($q) use ($clientSearch) {
                            $q->where(function ($q) use ($clientSearch) {
                                $q->where('nom', 'like', '%' . $clientSearch . '%')
                                ->orWhere('prenom', 'like', '%' . $clientSearch . '%');
                            });
                        });
                    }

                    // Filtre par téléphone
                    if ($request->filled('telephone')) {
                        $telephoneSearch = $request->input('telephone');
                        $query->whereHas('aquereurs.client', function ($q) use ($telephoneSearch) {
                            $q->where(function ($q) use ($telephoneSearch) {
                                $q->where('telephone_num1', 'like', '%' . $telephoneSearch . '%')
                                ->orWhere('telephone_num2', 'like', '%' . $telephoneSearch . '%') ;
                            });
                        });
                    }

                    // Date de réservation
                    if ($request->filled('date_start')) {
                        $startDate = Carbon::parse($request->input('date_start'));
                        $query->whereDate('date_reservation', '>=', $startDate);
                    }

                    if ($request->filled('date_end')) {
                        $endDate = Carbon::parse($request->input('date_end'));
                        $query->whereDate('date_reservation', '<=', $endDate);
                    }

                    // FILTRES SPÉCIFIQUES PAR STATUT
                    $statut = $request->input('statut', '');

                    if ($statut === 'signe') {
                        // Seulement les réservations avec contrat signé (piece_jointe non null)
                        $query->whereHas('contrat_vente', function ($q) {
                            $q->whereNotNull('piece_jointe');
                        });
                    } elseif ($statut === 'non_signe') {
                        // Réservations sans contrat OU avec contrat non signé (piece_jointe null)
                        $query->where(function($q) {
                            $q->whereDoesntHave('contrat_vente') // Pas de contrat du tout
                            ->orWhereHas('contrat_vente', function ($q2) {
                                // Contrat existant mais non signé (piece_jointe null)
                                $q2->whereNull('piece_jointe');
                            });
                        });
                    }

                    // FILTRES DE DATES POUR LES CONTRATS
                    // Ces filtres s'appliquent seulement aux réservations avec contrat
                    if (!$statut || $statut !== 'non_signe') {
                        if ($request->filled('date_sign_client_start') || $request->filled('date_sign_client_end')) {
                            $query->whereHas('contrat_vente', function ($q) use ($request) {
                                if ($request->filled('date_sign_client_start')) {
                                    $startDate = Carbon::parse($request->input('date_sign_client_start'));
                                    $q->whereDate('date_sign_client', '>=', $startDate);
                                }
                                if ($request->filled('date_sign_client_end')) {
                                    $endDate = Carbon::parse($request->input('date_sign_client_end'));
                                    $q->whereDate('date_sign_client', '<=', $endDate);
                                }
                            });
                        }

                        if ($request->filled('date_sign_mo_start') || $request->filled('date_sign_mo_end')) {
                            $query->whereHas('contrat_vente', function ($q) use ($request) {
                                if ($request->filled('date_sign_mo_start')) {
                                    $startDate = Carbon::parse($request->input('date_sign_mo_start'));
                                    $q->whereDate('date_sign_mo', '>=', $startDate);
                                }
                                if ($request->filled('date_sign_mo_end')) {
                                    $endDate = Carbon::parse($request->input('date_sign_mo_end'));
                                    $q->whereDate('date_sign_mo', '<=', $endDate);
                                }
                            });
                        }

                        if ($request->filled('date_enreg_start') || $request->filled('date_enreg_end')) {
                            $query->whereHas('contrat_vente', function ($q) use ($request) {
                                if ($request->filled('date_enreg_start')) {
                                    $startDate = Carbon::parse($request->input('date_enreg_start'));
                                    $q->whereDate('date_enreg', '>=', $startDate);
                                }
                                if ($request->filled('date_enreg_end')) {
                                    $endDate = Carbon::parse($request->input('date_enreg_end'));
                                    $q->whereDate('date_enreg', '<=', $endDate);
                                }
                            });
                        }

                    }

                    // Pagination
                    $reservations = $query->orderBy('created_at', 'desc')
                        ->paginate($size, ['*'], 'page', $page);

                    // Transformer les données pour le frontend
                    $transformedData = $reservations->map(function ($reservation) {
                        $contrat = $reservation->contrat_vente;

                        if ($contrat) {
                            // Déterminer si le contrat est signé (piece_jointe non null)
                            $pieceJointe = $contrat->piece_jointe;
                            $isSigned = !empty($pieceJointe);

                            // Réservation avec contrat
                            return [
                                'id' => $contrat->id,
                                'reservation_id' => $reservation->id,
                                'reservation' => $reservation,
                                'contrat_signe' => $pieceJointe,
                                'date_sign_client' => $contrat->date_sign_client,
                                'date_sign_mo' => $contrat->date_sign_mo,
                                'date_enreg' => $contrat->date_enreg,
                                'date_echeance' => $contrat->date_echeance,
                                'num_titre' => $contrat->num_titre,
                                'num_recu' => $contrat->num_recu,
                                'commentaire' => $contrat->commentaire,
                                'statut_type' => $isSigned ? 'signe' : 'non_signe_contrat',
                                'is_non_signed_reservation' => false,
                                'created_at' => $contrat->created_at,
                                // Informations sur les avances
                                'total_avances_valides' => $reservation->avances_valides_sum_montant ?? 0,
                                'prix_reservation' => $reservation->prix,
                                'condition_avances_remplie' => ($reservation->avances_valides_sum_montant ?? 0) >= $reservation->prix,
                            ];
                        } else {
                            // Réservation sans contrat
                            return [
                                'id' => null,
                                'reservation_id' => $reservation->id,
                                'reservation' => $reservation,
                                'contrat_signe' => null,
                                'date_sign_client' => null,
                                'date_sign_mo' => null,
                                'date_enreg' => null,
                                'date_echeance' => null,
                                'num_titre' => null,
                                'num_recu' => null,
                                'commentaire' => null,
                                'statut_type' => 'non_signe',
                                'is_non_signed_reservation' => true,
                                'created_at' => $reservation->created_at,
                                // Informations sur les avances
                                'total_avances_valides' => $reservation->avances_valides_sum_montant ?? 0,
                                'prix_reservation' => $reservation->prix,
                                'condition_avances_remplie' => ($reservation->avances_valides_sum_montant ?? 0) >= $reservation->prix,
                            ];
                        }
                    });

                    // Filtrage PHP supplémentaire par statut
                    $statutFilter = $request->input('statut', '');
                    if ($statutFilter === 'signe') {
                        $transformedData = $transformedData->filter(function ($item) {
                            return $item['statut_type'] === 'signe';
                        });
                    } elseif ($statutFilter === 'non_signe') {
                        $transformedData = $transformedData->filter(function ($item) {
                            return $item['statut_type'] === 'non_signe' || $item['statut_type'] === 'non_signe_contrat';
                        });
                    }

                    // Pagination manuelle
                    $filteredData = $transformedData->values();
                    $total = $filteredData->count();
                    $paginatedData = $filteredData->slice(($page - 1) * $size, $size);

                    $pagination = [
                        'currentPage' => (int)$page,
                        'totalItems' => $total,
                        'totalPages' => ceil($total / $size),
                    ];

                    return response()->json([
                        'success' => true,
                        'data' => $paginatedData->values()->all(),
                        'pagination' => $pagination,
                        'total' => $total,
                    ], 200);
                }

                return response()->json(['error' => 'Unauthorized'], 401);
            }

        /*Creneau Occupes by notaire*/
public function getCreneauxOccupes_by_User(Request $request)
{
    if (RoleHelper::Notaire() || RoleHelper::RespoLivraison()) {
        $user = Auth::user();
        DatabaseHelper::Config();
        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();

        // Démarrer la requête
        $query = CreneauxOccupes::on('temp')->where('type', '!=', 0);

        // Récupérer le notaire_id depuis la requête
        $notaireId = $request->input('notaire_id');

        if (RoleHelper::Notaire()) {
            // Pour un notaire, toujours voir ses propres créneaux
            $query->where('user_id', $userAuth->id);
        } elseif (RoleHelper::RespoLivraison()) {
            // Pour le respo livraison

            if ($notaireId === 'tous' || $notaireId === null) {
                // Voir tous les créneaux de tous les notaires (rôle 5)
                // Récupérer tous les IDs des notaires
                $notairesIds = User::on('temp')
                    ->where('role', 5)
                    ->pluck('id')
                    ->toArray();

                // Filtrer les créneaux par ces IDs
                if (!empty($notairesIds)) {
                    $query->whereIn('user_id', $notairesIds);
                } else {
                    // Si pas de notaires, retourner vide
                    return response()->json(['creneaux' => []], 200);
                }
            } elseif ($notaireId && $notaireId !== 'null') {
                // Voir les créneaux d'un notaire spécifique
                $query->where('user_id', $notaireId);
            }
        }

        $start = Carbon::createFromTimestamp($request->input('start') / 1000);
        $end = Carbon::createFromTimestamp($request->input('end') / 1000);

        // Récupérer les créneaux
        $creneaux = $query->whereBetween('debut', [$start, $end])
            ->orderBy('debut', 'asc')
            ->get();

        // Enrichir avec les informations utilisateur
        $enrichedCreneaux = $creneaux->map(function ($creneau) {
            // Récupérer l'utilisateur associé
            $user = User::on('temp')->find($creneau->user_id);

            return [
                'id' => $creneau->id,
                'debut' => $creneau->debut->format('Y-m-d H:i:s'),
                'fin' => $creneau->fin->format('Y-m-d H:i:s'),
                'disponible' => $creneau->disponible,
                'type' => $creneau->type,
                'user_id' => $creneau->user_id,
                // Utiliser les noms de colonnes corrects de votre base de données
                'user_name' => $user ? ($user->nom ?? $user->name ?? null) : null,
                'user_prenom' => $user ? $user->prenom : null,
                'user_type' => $user ? $user->role : null
            ];
        });

        return response()->json(['creneaux' => $enrichedCreneaux], 200);
    }

    return response()->json(['error' => 'Unauthorized'], 401);
}
      public function storeCreneau(Request $request)
        {
            if (!RoleHelper::NotaireRespoL()) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $user = Auth::user();
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();

            // Valider les données
            $validated = $request->validate([
                'debut' => 'required|date',
                'fin' => 'required|date|after:debut',
                'disponible' => 'boolean',
                'type' => 'nullable'
            ]);

            try {

                // Vérifier si le créneau existe déjà
                    $existingCreneau = CreneauxOccupes::on('temp')
                        ->where(function($query) use ($validated) {
                            // Vérifie les chevauchements (sans inclure les créneaux qui se touchent)
                            $query->where('debut', '<', $validated['fin'])   // début existant < fin nouveau
                                ->where('fin', '>', $validated['debut']);  // fin existant > début nouveau
                        })
                        ->where('type','!=',0)
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

                // Créer le nouveau créneau
                $cren = new CreneauxOccupes();
                $cren->setConnection('temp');
                $cren->debut = Carbon::parse($validated['debut']);
                $cren->fin = Carbon::parse($validated['fin']);
                $cren->user_id = $userAuth->id;
                $cren->type = $validated['type'] ?? null;
                $cren->reservation_id = null;
                $cren->disponible = false; // Utiliser la valeur du formulaire
                $cren->save();

                // Check the creneau propose ==> supprimer it
                $cren_prop = CreneauxOccupes::on('temp')
                    ->where('type', 0)
                    ->where('user_id', $userAuth->id)
                    ->get();
                if (count($cren_prop) > 0) {
                            foreach($cren_prop as $cr){
                            $cr->forceDelete();
                            }
                }


                return response()->json([
                    'message' => 'Créneau ajouté avec succès',
                    'creneau' => [
                        'id' => $cren->id,
                        'debut' => $cren->debut->format('Y-m-d H:i:s'),
                        'fin' => $cren->fin->format('Y-m-d H:i:s'),
                        'disponible' => $cren->disponible,
                        'type' => $cren->type
                    ]
                ], 201);

            } catch (\Illuminate\Validation\ValidationException $e) {
                return response()->json([
                    'error' => 'Validation error',
                    'errors' => $e->errors()
                ], 422);
            } catch (\Exception $e) {
                // CORRECTION: Ne pas utiliser $creneau qui n'existe pas
                return response()->json([
                    'error' => 'Erreur lors de l\'ajout du créneau',
                    'details' => $e->getMessage(),
                    'trace' => $e->getTraceAsString() // Pour debug
                ], 500);
            }
        }


    public function updateCreneau(Request $request, $id)
    {
        if (!RoleHelper::NotaireRespoL()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = Auth::user();
        DatabaseHelper::Config();
        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();

        // Valider les données
        $validated = $request->validate([
            'debut' => 'required|date',
            'fin' => 'required|date|after:debut',
            'disponible' => 'boolean',
            'type' => 'nullable'
        ]);

        try {
            // Récupérer le créneau à modifier
            $creneau = CreneauxOccupes::on('temp')
                ->where('id', $id)
                ->where('user_id', $userAuth->id)
                ->firstOrFail();

            // Vérifier si le créneau existe déjà (pour d'autres créneaux)
            $existingCreneau = CreneauxOccupes::on('temp')
                ->where('id', '!=', $id)
                ->where(function($query) use ($validated) {
                // Vérifier si un créneau existant chevauche le nouveau créneau
                $query->where(function($q) use ($validated) {
                    // Cas 1 : Le créneau existant commence avant et finit après le début du nouveau
                    $q->where('debut', '<', $validated['fin'])
                      ->where('fin', '>',$validated['debut']);
                })
                ->orWhere(function($q) use ($validated) {
                    // Cas 2 : Même début et fin exacts
                    $q->where('debut',$validated['debut'])
                      ->where('fin', $validated['fin']);
                });
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

            // Mettre à jour le créneau
            $creneau->debut = Carbon::parse($validated['debut']);
            $creneau->fin = Carbon::parse($validated['fin']);
            $creneau->type = $validated['type'] ?? null;
            $creneau->disponible = $validated['disponible'] ?? false;
            $creneau->save();

            return response()->json([
                'message' => 'Créneau modifié avec succès',
                'creneau' => [
                    'id' => $creneau->id,
                    'debut' => $creneau->debut->format('Y-m-d H:i:s'),
                    'fin' => $creneau->fin->format('Y-m-d H:i:s'),
                    'disponible' => $creneau->disponible,
                    'type' => $creneau->type
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Créneau non trouvé'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la modification du créneau',
                'details' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Delete a créneau
     */
    public function deleteCreneau(Request $request, $id)
    {
        if (!RoleHelper::Notaire() && !RoleHelper::RespoLivraison()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = Auth::user();
        DatabaseHelper::Config();
        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();

        try {
            // Trouver le créneau
            $creneau = CreneauxOccupes::on('temp')
                ->where('id', $id)
                ->where('user_id', $userAuth->id)
                ->first();

            if (!$creneau) {
                return response()->json([
                    'error' => 'Créneau non trouvé ou non autorisé'
                ], 404);
            }

            // Vérifier si le créneau peut être supprimé (pas dans le passé si occupé)
            $now = Carbon::now();
            if (!$creneau->disponible && $creneau->debut < $now) {
                return response()->json([
                    'error' => 'Impossible de supprimer un créneau occupé dans le passé'
                ], 400);
            }

            // Supprimer le créneau
            $creneau->delete();

            return response()->json([
                'message' => 'Créneau supprimé avec succès',
                'deleted_id' => $id
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la suppression',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store multiple créneaux at once
     */



    // Dans votre contrôleur Laravel
public function updateAgendaByUser(Request $request)
{
    $validated = $request->validate([
        'date_debut' => 'required|date',
        'date_fin' => 'required|date',
    ]);

    // Vérifier les permissions
    if (!RoleHelper::NotaireRespoL()) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $user = Auth::user();
    DatabaseHelper::Config();
    $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();

    $debutTime = Carbon::parse($validated['date_debut']);
    $finTime = Carbon::parse($validated['date_fin']);

    // Vérifier les heures d'ouverture
    $debutHour = $debutTime->format('H:i');
    $finHour = $finTime->format('H:i');

    if ($debutHour < '09:00' || $finHour > '17:00') {
        return response()->json([
            'message' => 'Hors des heures d\'ouverture',
            'errors' => ['date_debut' => ['Veuillez choisir un horaire entre 9h et 17h']]
        ], 422);
    }

    return DB::connection('temp')->transaction(function () use ($debutTime, $finTime, $userAuth) {
        // CORRECTION : Vérifier les conflits de manière correcte
        $existingConflict = CreneauxOccupes::on('temp')
            ->where(function($query) use ($debutTime, $finTime) {
                // Vérifier si un créneau existant chevauche le nouveau créneau
                $query->where(function($q) use ($debutTime, $finTime) {
                    // Cas 1 : Le créneau existant commence avant et finit après le début du nouveau
                    $q->where('debut', '<', $finTime)
                      ->where('fin', '>', $debutTime);
                })
                ->orWhere(function($q) use ($debutTime, $finTime) {
                    // Cas 2 : Même début et fin exacts
                    $q->where('debut', $debutTime)
                      ->where('fin', $finTime);
                });
            })
            ->where('type', '!=',0)
            ->where('disponible', false) // Seulement les créneaux occupés
            ->exists();

        if ($existingConflict) {
            return response()->json([
                'message' => 'Ce créneau n\'est plus disponible',
                'errors' => ['date_debut' => ['Ce créneau est déjà occupé']]
            ], 422);
        }

        // Supprimer les anciennes propositions du même jour
        $todayPropositions = CreneauxOccupes::on('temp')
            ->where('user_id', $userAuth->id)
            ->where('type', 0) // Propositions seulement
            ->whereDate('debut', $debutTime->toDateString())
            ->get();

        foreach ($todayPropositions as $proposition) {
            $proposition->forceDelete();
        }

        // Créer le nouveau créneau
        $creneau = new CreneauxOccupes();
        $creneau->setConnection('temp');
        $creneau->debut = $debutTime->format('Y-m-d H:i:s');
        $creneau->fin = $finTime->format('Y-m-d H:i:s');
        $creneau->user_id = $userAuth->id;
        $creneau->type = 0; // Type 0 = proposition
        $creneau->disponible = false;
        $creneau->save();

        // Déclencher l'événement Pusher
        event(new Rendez_vous_Prop(
            $debutTime->format('Y-m-d H:i:s'),
            $userAuth->id,
            null, // Pas de reservation_id
            null  // Pas d'ancien créneau
        ));

        return response()->json([
            'message' => 'Proposition de créneau enregistrée avec succès',
            'creneau' => $creneau
        ]);
    });
}
}
