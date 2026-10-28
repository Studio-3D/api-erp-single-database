<?php
namespace App\Http\Controllers\Api\V1;
use App\Events\NotifMenuEvent;
use App\Http\Helpers\CommercialAssignmentHelper;

use App\Enum\InteretEnum;
use App\Enum\StatutVisiteEnum;
use App\Events\NotificationEvent;
use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\NotificationHelper;
use App\Http\Helpers\PaginationHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreProspectRequest;
use App\Http\Requests\UpdateProspectRequest;
use App\Models\Client;
use App\Models\Notification;
use App\Models\Prospect;
use App\Models\ProspectFusion;

use App\Models\Source;
use App\Models\StatutProspect;
use App\Models\TraitementFrein;
use App\Models\User;
use App\Models\Visite;
use Carbon\Carbon;
use App\Models\Societe;
use Illuminate\Support\Facades\File;
use App\Models\StatutClient;
use App\Models\Import;
use App\Http\Helpers\FichierHelper;  // AJOUTER CETTE LIGNE
use Illuminate\Support\Str;

use App\Models\TraitementAppel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Enum\StatutProspectEnum;

use App\Models\HistoriqueRelanceWhatsapp;
use App\Models\Projet;
class ProspectController extends Controller
{
    /**
     * Display a listing of the resource.
     */


    public function uploadMedia_bulk_whatsap(Request $request)
    {
        if (!RoleHelper::ACSup() && !RoleHelper::AgentAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        DatabaseHelper::Config();

        // Get the society information (like in update societe)
        $user = Auth::user();
        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
        $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
        $societe = Societe::findOrfail($user_societes->societe_id);

        $request->validate([
                'media' => 'required|file|max:10240'
        ]);

        if ($request->hasFile('media')) {
            $file = $request->file('media');

            // Generate filename like in societe update

            $fileName = time() . '_' . $file->getClientOriginalName();
            $doss = 'bulk_whatsap_file';

            // Get file info BEFORE using FichierHelper
            $fileType = Str::startsWith($file->getMimeType(), 'image/') ? 'image' : 'video';
            $mimeType = $file->getMimeType();
            $originalName = $file->getClientOriginalName();

            // Use FichierHelper exactly like in societe update (this stores in docs folder)
            FichierHelper::ajouter_fichier(
                $file,
                $societe->raison_sociale_concatene,
                $societe->id,
                $doss,
                $fileName
            );
            // Generate URL using FichierHelper
            $mediaUrl = FichierHelper::get_file_url(
                $societe->raison_sociale_concatene,
                $societe->id,
                $doss,
                $fileName
            );

            return response()->json([
                'message' => 'Media uploaded successfully',
                'url'     => $mediaUrl,
            ], 201);
        }

        return response()->json(['error' => 'No file uploaded'], 400);
    }


public function getNbProspectRdv(Request $request, $projet_id)
{
    if (Auth::guard('api')->check()) {
        DatabaseHelper::Config();
        $user = Auth::user();
        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();

        // Récupérer la date de demain
        $tomorrow = Carbon::tomorrow();

        $query = StatutProspect::on('temp')
            ->where('rdv', '!=', null)
            ->whereNull('visite_id')
            ->whereNull('appel_id')
            ->where('type_traitement_rdv_relance', 0)
            ->whereDate('rdv', $tomorrow); // AJOUT: Filtrer uniquement les RDV de demain
            /*->whereHas('prospect', function ($q) use ($projet_id) {
                $q->where('projet_id', $projet_id);
            });*/

        if (!RoleHelper::AdminSup() && !RoleHelper::AgentAdmin()) {
            $query->where('user_id_traite', $userAuth->value('id'));
        }

        $count = $query->count();

        \Log::info('getNbProspectRdv count for tomorrow:', [
            'count' => $count,
            'projet_id' => $projet_id,
            'tomorrow' => $tomorrow->toDateString()
        ]);

        return response()->json([
            'count' => $count
        ], 200);
    }

    return response()->json(['error' => 'Unauthorized'], 401);
}

    /**
     * Récupérer le nombre de relances prospects
     */
    public function getNbProspectRelance(Request $request, $projet_id)
{
    if (Auth::guard('api')->check()) {
        DatabaseHelper::Config();
        $user = Auth::user();
        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();

        $query = StatutProspect::on('temp')
            ->where('date_rappel', '!=', null)
            ->whereNull('visite_id')
            ->whereNull('appel_id')
            ->where('type_traitement_rdv_relance', 0)
             ->whereDate('date_rappel', '<=', Carbon::now());
           /* ->whereHas('prospect', function ($q) use ($projet_id) {
                $q->where('projet_id', $projet_id);
            });*/

        if (!RoleHelper::AdminSup() && !RoleHelper::AgentAdmin()) {
            $query->where('user_id_traite', $userAuth->value('id'));
        }

        $count = $query->count();

        return response()->json([
            'count' => $count
        ], 200);
    }

    return response()->json(['error' => 'Unauthorized'], 401);
}
public function traiterRelanceRdvProspect(Request $request, $id)
{
    if (Auth::guard('api')->check()) {
        DatabaseHelper::Config();
        $user = Auth::user();

        $statutProspect = StatutProspect::on('temp')->find($id);

        if (!$statutProspect) {
            return response()->json(['error' => 'Statut Prospect non trouvé'], 404);
        }

        // Valider les données
        $request->validate([
            'date' => 'nullable|date',
            'commentaire' => 'nullable|string',
        ]);

        // Mettre à jour le statut prospect (le marquer comme traité)
        $statutProspect->type_traitement_rdv_relance = 1; // 1 = manuelle
        $statutProspect->date_traitement_rdv_relance = now();
        $statutProspect->user_id_traite_rdv_relance = $user->id;
        if ($request->filled('commentaire')) {
            $statutProspect->commentaire = $request->commentaire;
        }
        $statutProspect->save();

        // Si une nouvelle date de RDV est fournie
        if ($request->filled('date')) {
            $new_relance = new StatutProspect();
            $new_relance->setConnection('temp');
            $new_relance->prospect_id = $statutProspect->prospect_id;
            $new_relance->user_id_traite = $user->id;
            $new_relance->date_traitement = now();

            if ($statutProspect->rdv != null) {
                // C'était un RDV, on crée un nouveau RDV
                $new_relance->rdv = Carbon::parse($request->date);
                $new_relance->statut = '1';
            } else {
                // C'était une relance, on crée une nouvelle relance
                $new_relance->date_rappel = Carbon::parse($request->date);
                $new_relance->statut = '3';
            }

            $new_relance->type_traitement_rdv_relance = 0; // Nouveau RDV/Relance en attente

            if ($new_relance->save()) {
                if ($new_relance->date_rappel != null) {
                    // Notification pour Relance Prospect (H)
                    Config::set('broadcasting.default', 'pusher_notify');
                    $data_notif = [
                        //'lien' => '/crm?tab=relance/',
                        'lien'        => '/crm/prospects/' .  $statutProspect->prospect_id,
                        'date' => $request->date,
                        'type' => 1,
                        'description' => 'Relance Prospect',
                        'user_id' => Auth::guard('api')->user()->id,
                        'role' => null,
                        'prospect_id' => $statutProspect->prospect_id,
                        'projet_id' => $statutProspect->prospect->projet_id,
                    ];
                    $notif_helper = new NotificationHelper();
                    $notif_helper->storeNotification($request->merge($data_notif));
                    broadcast(new NotificationEvent($statutProspect->id));
                    broadcast(new NotifMenuEvent('H'));

                    // CRITICAL: Forcer le rafraîchissement complet après H
                    broadcast(new NotifMenuEvent('D'));

                } else {
                    // Notification pour RDV Prospect (G)
                    Config::set('broadcasting.default', 'pusher_notify');
                    $data_notif = [
                        'lien' => '/crm?tab=rendez-vous',
                        'date' => $request->date,
                        'type' => 2,
                        'description' => 'RDV Prospect',
                        'user_id' => Auth::guard('api')->user()->id,
                        'role' => null,
                        'prospect_id' => $statutProspect->prospect_id,
                        'projet_id' => $statutProspect->prospect->projet_id,
                    ];
                    $notif_helper = new NotificationHelper();
                    $notif_helper->storeNotification($request->merge($data_notif));
                    broadcast(new NotificationEvent($statutProspect->id));
                    broadcast(new NotifMenuEvent('G'));

                    // CRITICAL: Forcer le rafraîchissement complet après G
                    broadcast(new NotifMenuEvent('D'));
                }
            }
        } else {
            // Si pas de nouvelle date, juste marquer comme traité
            // Rafraîchir quand même les compteurs
            broadcast(new NotifMenuEvent('D'));
        }

        return response()->json([
            'message' => 'Prospect traité avec succès',
            'data' => $statutProspect
        ], 200);
    }

    return response()->json(['error' => 'Unauthorized'], 401);
}
public function exportByProjet(Request $request, $projet_id)
{
    // Check if user has permission
    if (!RoleHelper::ACSup_RC()) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    DatabaseHelper::Config();

    $query = Prospect::on('temp')->with([
        'traite_par_user' => function($query) {
            $query->select('id','name','prenom')->without('societe');
        },
        'last_statut' => function($query) {
            $query->select('*')->without('prospect','user');
        },
        'commercial_affecte' => function($query) {
            $query->select('id','user_id_origin', 'name', 'prenom')->without('societe');
        },
        'source' => function($query) {
            $query->select('id', 'source');
        }
    ])->where('projet_id', $projet_id);
    // ✅ NOUVEAU : exclure les prospects dont le DERNIER statut est '0' (Nouveau) ou '18' (En cours)
    $query->whereNotIn('prospects.id', function ($subQuery) {
        $subQuery->select('sp.prospect_id')
            ->from('statut_prospects as sp')
            ->whereNull('sp.deleted_at')
            ->whereIn('sp.id', function ($inner) {
                $inner->selectRaw('MAX(sp2.id)')
                    ->from('statut_prospects as sp2')
                    ->whereColumn('sp2.prospect_id', 'sp.prospect_id')
                    ->whereNull('sp2.deleted_at');
            })
            ->whereIn('sp.statut', ['18']);   // ← Nouveau + En cours
    });
    // ⭐  Exclure les prospects fusionnés
    $query->whereNotIn('prospects.id', function ($subQuery) {
        $subQuery->select('prospect_fusion_id')
            ->from('prospect_fusions')
            ->whereNull('deleted_at');
    });
    // Filtrer par commercial affecté
    if ($request->filled('commercial_id')) {
        $commercialId = $request->input('commercial_id');
        $query->whereHas('commercial_affecte', function($q) use ($commercialId) {
            $q->where('user_id_origin', $commercialId);
        });
    }

    // ✅ DATE FILTERS - Creation date (using prospect.created_at)
    if ($request->filled('created_at_from') && $request->filled('created_at_to')) {
        $from = $request->input('created_at_from') . ' 00:00:00';
        $to = $request->input('created_at_to') . ' 23:59:59';
        $query->whereBetween('prospects.created_at', [$from, $to]);
    }

    // ✅ DATE FILTERS - Affectation date (using prospect.date_affectation)
    if ($request->filled('date_affectation_from') && $request->filled('date_affectation_to')) {
        $from = $request->input('date_affectation_from') . ' 00:00:00';
        $to = $request->input('date_affectation_to') . ' 23:59:59';
        $query->whereBetween('prospects.date_affectation', [$from, $to]);
    }

    // ✅ DATE FILTERS - Treatment date (using last_statut.date_traitement)
    if ($request->filled('date_traitement_from') && $request->filled('date_traitement_to')) {
        $from = $request->input('date_traitement_from');
        $to = $request->input('date_traitement_to');

        $query->whereHas('last_statut', function($q) use ($from, $to) {
            $q->whereBetween('date_traitement', [$from, $to]);
        });
    }


    // Get all results without pagination for export
    $prospects = $query->orderBy('prospects.created_at', 'desc')
        ->select('prospects.*')
        ->get();

    return response()->json([
        'prospects' => $prospects,
        'total' => $prospects->count()
    ], 200);
}
public function indexByProjet(Request $request, $projet_id)
{
    if (RoleHelper::ACSup_RC()|| RoleHelper::AgentAdmin()  ) {
        $size = $request->input('size', null);
        $page = $request->input('page', null);

        DatabaseHelper::Config();

        $query = Prospect::on('temp')->with([
            'traite_par_user' => function($query) {
                $query->select('id','name','prenom')->without('societe');
            },
            'partenaire' => function($query) {
                $query->select('id','description');
            },
            'last_statut' => function($query) {
                $query->select('*')->without('prospect','user');
            },
            'commercial_affecte' => function($query) {
                $query->select('id','user_id_origin', 'name', 'prenom')->without('societe');
            },
            'client' => function($query) {
                $query->select('id', 'nom', 'prenom')->without('partenaire');
            },
            'affecte_par_admin' => function($query) {
                $query->select('id', 'name', 'prenom')->without('societe');
            },
            'source' => function($query) {
                $query->select('id', 'source');
            }
        ])->withCount([
            'visites',
            'all_appels as appels_count'
        ])->where('projet_id', $projet_id);
        // ✅ NOUVEAU : exclure les prospects dont le DERNIER statut est '0' (Nouveau) ou '18' (En cours)
        $query->whereNotIn('prospects.id', function ($subQuery) {
            $subQuery->select('sp.prospect_id')
                ->from('statut_prospects as sp')
                ->whereNull('sp.deleted_at')
                ->whereIn('sp.id', function ($inner) {
                    $inner->selectRaw('MAX(sp2.id)')
                        ->from('statut_prospects as sp2')
                        ->whereColumn('sp2.prospect_id', 'sp.prospect_id')
                        ->whereNull('sp2.deleted_at');
                })
                ->whereIn('sp.statut', ['18']);   // ← Nouveau + En cours
        });
        // ⭐ Exclure les prospects fusionnés
        $query->whereNotIn('prospects.id', function ($subQuery) {
            $subQuery->select('prospect_fusion_id')
                ->from('prospect_fusions')
                ->whereNull('deleted_at');
        });
        // Filtrer par commercial affecté
        if ($request->filled('commercial_id')) {
            $commercialId = $request->input('commercial_id');
            $query->whereHas('commercial_affecte', function($q) use ($commercialId) {
                $q->where('user_id_origin', $commercialId);
            });
        }
         // ✅ AJOUT: Filtrer par lead qualifié
        if ($request->filled('lead_qualifie')) {
            $leadQualifie = $request->input('lead_qualifie');
            // Convertir en boolean (true/false)
            if ($leadQualifie === 'true' || $leadQualifie === '1' || $leadQualifie === 1) {
                $query->where('etat', 1);
            } elseif ($leadQualifie === 'false' || $leadQualifie === '0' || $leadQualifie === 0) {
                $query->where('etat', 0);
            }
        }

        // ✅ DATE FILTERS - Creation date (using prospect.created_at)
        if ($request->filled('created_at_from') && $request->filled('created_at_to')) {
            $from = $request->input('created_at_from') . ' 00:00:00';
            $to = $request->input('created_at_to') . ' 23:59:59';
            $query->whereBetween('prospects.created_at', [$from, $to]);
        }

        // ✅ DATE FILTERS - Affectation date (using prospect.date_affectation)
        if ($request->filled('date_affectation_from') && $request->filled('date_affectation_to')) {
            $from = $request->input('date_affectation_from') . ' 00:00:00';
            $to = $request->input('date_affectation_to') . ' 23:59:59';
            $query->whereBetween('prospects.date_affectation', [$from, $to]);
        }

        // ✅ DATE FILTERS - Treatment date (using last_statut.date_traitement)
        if ($request->filled('date_traitement_from') && $request->filled('date_traitement_to')) {
            $from = $request->input('date_traitement_from');
            $to = $request->input('date_traitement_to');

            $query->whereHas('last_statut', function($q) use ($from, $to) {
                $q->whereBetween('date_traitement', [$from, $to]);
            });
        }

        // Filtres existants
        if ($request->filled('telephone')) {
            $query->where(function ($q) use ($request) {
                $q->where('telephone', 'like', '%' . $request->input('telephone') . '%')
                  ->orWhere('telephone_num2', 'like', '%' . $request->input('telephone') . '%');
            });
        }

        if ($request->filled('cin')) {
            $query->where('cin', 'like', '%' . $request->input('cin') . '%');
        }
        if ($request->filled('nom')) {
            $query->where('nom', 'like', '%' . $request->input('nom') . '%');
        }
        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->input('email') . '%');
        }
        if ($request->filled('prenom')) {
            $query->where('prenom', 'like', '%' . $request->input('prenom') . '%');
        }

        if ($request->filled('origin')) {
            $originValue = $request->input('origin');
            $query->where('origin', $originValue);
        }

        if ($request->filled('source')) {
            $sourceValue = $request->input('source');
            if (is_numeric($sourceValue)) {
                $query->where('source', $sourceValue);
            }
        }

        // ✅ STATUS FILTER - Using whereRaw with the exact working SQL
        if ($request->filled('statut')) {
            $statutValue = $request->input('statut');

            $query->whereRaw('prospects.id IN (
                SELECT p.id
                FROM prospects p
                JOIN statut_prospects sp ON p.id = sp.prospect_id
                WHERE sp.id = (
                    SELECT MAX(sp2.id)
                    FROM statut_prospects sp2
                    WHERE sp2.prospect_id = p.id
                )
                AND sp.statut = ?
            )', [$statutValue]);
        }

        if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
            $prospects = $query->orderBy('prospects.created_at', 'desc')
                ->select('prospects.*')
                ->paginate($size, ['prospects.*'], 'page', $page);

            $pagination = [
                'currentPage' => $prospects->currentPage(),
                'totalItems'  => $prospects->total(),
                'totalPages'  => $prospects->lastPage(),
            ];

            $prospects = $prospects->items();

            return response()->json([
                'prospects'  => $prospects,
                'pagination' => $pagination,
            ], 200);
        } else {
            $prospects = $query->orderBy('prospects.created_at', 'desc')
                ->select('prospects.*')
                ->get();

            return response()->json(['prospects' => $prospects], 200);
        }
    }

    return response()->json(['error' => 'Unauthorized'], 401);
}
    public function index(Request $request)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', null);
            $page = $request->input('page', null);
            DatabaseHelper::Config();

            // Démarrer la requête directement sur le modèle
            $query = prospect::on('temp')->with('client', 'visites', 'appels');

            // Exclure les prospects fusionnés
            $query->whereNotIn('prospects.id', function ($subQuery) {
                $subQuery->select('prospect_fusion_id')
                    ->from('prospect_fusions')
                    ->whereNull('deleted_at');
            });
            $query->where(function ($q) use ($request) {
                if ($request->filled('telephone')) {
                    $q->where(function ($subQuery) use ($request) {
                        $subQuery->where('telephone', 'like', '%' . $request->input('telephone') . '%')
                            ->orWhere('telephone_num2', 'like', '%' . $request->input('telephone') . '%');
                    });
                }
            });
            if ($request->filled('cin')) {
                $query->where('cin', 'like', '%' . $request->input('cin') . '%');
            }
            if ($request->filled('nom')) {
                $query->where('nom', 'like', '%' . $request->input('nom') . '%');
            }
            if ($request->filled('prenom')) {
                $query->where('prenom', 'like', '%' . $request->input('prenom') . '%');
            }


            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {

                $prospects = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Extraire les propriétés du paginateur
                $pagination = [
                    'currentPage' => $prospects->currentPage(),
                    'totalItems'  => $prospects->total(),
                    'totalPages'  => $prospects->lastPage(),
                ];

                // Extraire les éléments d'utilisateur du paginateur
                $prospects = $prospects->items();

                // Retourner la réponse simplifiée
                return response()->json([
                    'prospects'  => $prospects,
                    'pagination' => $pagination,
                ], 200);
            } else {
                // Return all results if pagination parameters are not provided or invalid
                $prospects = $query->orderBy('created_at', 'desc')
                    ->get();

                return response()->json(['prospects' => $prospects], 200);
            }

        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

   public function traiter_prospect($id, Request $request)
{
    // ✅ Validate that 'statut' is required and must not be null
    $request->validate([
        'statut' => 'required',
    ], [
        'statut.required' => 'Le Statut est Obligatoire.',
    ]);

    if (RoleHelper::ACSup() || RoleHelper::AgentAdmin() || RoleHelper::RespoCommercial()) {
        DatabaseHelper::Config();
        $user     = Auth::user();
        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
        $prospect = Prospect::on('temp')->findOrFail($id);

        $nouveauCommercialId = $userAuth->value('id');

        // ============================================================
        // 🔑 DÉTECTION DU CHANGEMENT DE COMMERCIAL (RÉAFFECTATION)
        // ============================================================
        $ancienCommercialId = $prospect->commercial_affecte;

        $estReaffectation = $ancienCommercialId
            && $nouveauCommercialId
            && (int) $ancienCommercialId !== (int) $nouveauCommercialId;

        // ============================================================
        // MISE À JOUR DU PROSPECT
        // ============================================================
        $prospect->traite_par_user_id = $nouveauCommercialId;
        $prospect->date_traitement    = Carbon::now();

        // 🔑 Gestion du compteur d'injoignables
        $statut = (int) $request->statut;

        if ($statut === StatutProspectEnum::Injoignable->value) {
            // Marqué injoignable → +1
            $prospect->nb_injoignable = (int) $prospect->nb_injoignable + 1;
        } elseif (in_array($statut, [
            StatutProspectEnum::Receptif->value,
            StatutProspectEnum::Interesse->value,
            StatutProspectEnum::Planification_RDV->value,
            StatutProspectEnum::Converti_en_visite->value,
            StatutProspectEnum::Converti_en_client->value,
            StatutProspectEnum::pre_reservation->value,
        ])) {
            // Le prospect est redevenu joignable → reset
            $prospect->nb_injoignable = 0;
        }
        // Sinon (Rappel, Perdu, WhatsApp, etc.) → on ne touche pas

        // ============================================================
        // 🔑 RÉAFFECTATION : le commercial qui traite devient le nouveau commercial affecté
        // ============================================================
        if ($estReaffectation) {
            // Décrémenter l'ancien commercial
            \App\Models\User::on('temp')
                ->where('id', $ancienCommercialId)
                ->decrement('nb_prospects');

            // Incrémenter le nouveau commercial
            \App\Models\User::on('temp')
                ->where('id', $nouveauCommercialId)
                ->increment('nb_prospects');

            // Mettre à jour l'affectation
            $prospect->commercial_affecte   = $nouveauCommercialId;
            $prospect->affecte_par_admin_id = $nouveauCommercialId;
            $prospect->date_affectation     = Carbon::now();
        }

        // ============================================================
        // UNASSIGN pour certains statuts finaux
        // ============================================================
        $finalStatuses = [
            StatutProspectEnum::Perdu->value,              // 8
            StatutProspectEnum::Converti_en_visite->value, // 4
        ];

        if (in_array($request->statut, $finalStatuses)) {
            $oldCommercialId = $prospect->commercial_affecte;
            $prospect->commercial_affecte   = null;
            $prospect->affecte_par_admin_id = null;
            $prospect->date_affectation     = null;

            // Update commercial's prospect counter
            if ($oldCommercialId) {
                \App\Models\User::on('temp')
                    ->where('id', $oldCommercialId)
                    ->decrement('nb_prospects');
            }
        }

        $prospect->save();

        // ============================================================
        // PRÉPARATION DES DONNÉES COMMUNES
        // ============================================================
        $statutDemande = is_numeric($request->statut)
            ? (int) $request->statut
            : (\App\Enum\StatutProspectEnum::tryFrom($request->statut)?->value ?? 0);

        // Récupérer les noms des commerciaux pour les commentaires
        $ancienCommercial  = $ancienCommercialId
            ? \App\Models\User::on('temp')->find($ancienCommercialId)
            : null;
        $nouveauCommercial = \App\Models\User::on('temp')->find($nouveauCommercialId);

        $ancienNom  = $ancienCommercial ? $ancienCommercial->name : "ID#$ancienCommercialId";
        $nouveauNom = $nouveauCommercial ? $nouveauCommercial->name : "ID#$nouveauCommercialId";

        // ============================================================
        // 1️⃣ STATUT RÉAFFECTATION (uniquement si réaffectation)
        // ============================================================
        if ($estReaffectation) {
            $ps_reaffect = new statutProspect();
            $ps_reaffect->setConnection('temp');
            $ps_reaffect->prospect_id     = $id;
            $ps_reaffect->statut          = (string) StatutProspectEnum::Reaffecte->value;
            $ps_reaffect->commentaire     = sprintf(
                'Prospect réaffecté de %s vers %s',
                $ancienNom,
                $nouveauNom
            );
            $ps_reaffect->user_id_traite  = $nouveauCommercialId;
            $ps_reaffect->date_traitement = Carbon::now();
            $ps_reaffect->save();
        }

        // ============================================================
        // 2️⃣ STATUT MÉTIER (le statut demandé par le commercial)
        // ============================================================
        $ps_statut = new statutProspect();
        $ps_statut->setConnection('temp');
        $ps_statut->prospect_id = $id;
        $ps_statut->statut      = (string) $statutDemande;

        // Construction du commentaire métier
        $commentaire = $request->commentaire;
        if (in_array($request->statut, $finalStatuses)) {
            $commentaire = $commentaire
                ? $commentaire . ' (Prospect désaffecté du commercial)'
                : 'Prospect désaffecté du commercial';
        }

        $ps_statut->commentaire     = $commentaire;
        $ps_statut->user_id_traite  = $nouveauCommercialId;
        $ps_statut->date_traitement = Carbon::now();

        // Gestion RDV / Rappel selon le statut métier
        if ($statutDemande === StatutProspectEnum::Planification_RDV->value) { // 1
            $ps_statut->rdv = $request->rdv;
            $ps_statut->type_traitement_rdv_relance = 0;
        } elseif ($statutDemande === StatutProspectEnum::Rappel->value) { // 3
            $ps_statut->date_rappel = $request->date_rappel;
            $ps_statut->type_traitement_rdv_relance = 0;
        }

        // ============================================================
        // NOTIFICATIONS (uniquement sur le statut métier)
        // ============================================================
        if ($ps_statut->save()) {

            // --- RAPPEL (statut = 2 côté legacy, mais dans l'enum Rappel = 3) ---
            if ($request->statut == 2) {
                Config::set('broadcasting.default', 'pusher_notify');
                $data_notif = [
                    'lien'        => '/crm/prospects/' . $id,
                    'date'        => $request->date_rappel,
                    'type'        => 30,
                    'user_id'     => Auth::guard('api')->user()->id,
                    'description' => 'le prospect doit etre rappelé',
                    'projet_id'   => $prospect->projet_id,
                    'prospect_id' => $id,
                ];
                $notif_helper = new NotificationHelper();
                $notif_helper->storeNotification($request->merge($data_notif));
                broadcast(new NotificationEvent($id));
                broadcast(new NotifMenuEvent('H'));
            }

            // --- RDV (statut = 1) ---
            if ($request->statut == 1) {
                Config::set('broadcasting.default', 'pusher_notify');
                $data_notif = [
                    'lien'        => '/crm?tab=rendez-vous',
                    'date'        => $request->rdv,
                    'type'        => 31,
                    'user_id'     => Auth::guard('api')->user()->id,
                    'description' => 'le prospect a un rdv',
                    'projet_id'   => $prospect->projet_id,
                    'prospect_id' => $id,
                ];
                $notif_helper = new NotificationHelper();
                $notif_helper->storeNotification($request->merge($data_notif));
                broadcast(new NotificationEvent($id));
                broadcast(new NotifMenuEvent('G'));
            }
        }

        return response()->json(['message' => 'done'], 200);

    } else {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
}

    public function get_Historiques_by_prospect($id, Request $request)
{
    if (Auth::guard('api')->check()) {
        $size = $request->input('size', config('app.default_item_number_perpage'));
        $page = $request->input('page', 1);

        DatabaseHelper::Config();

        // Récupérer le prospect pour vérifier s'il est devenu client
        $prospect = Prospect::on('temp')->find($id);

        if (!$prospect) {
            return response()->json(['error' => 'Prospect non trouvé'], 404);
        }

        // 1. Récupérer les statuts de prospect
        $statutsProspect = StatutProspect::on('temp')
            ->select(
                'id',
                'prospect_id',
                'statut',
                'user_id_traite',
                'date_traitement',
                'rdv',
                'date_rappel',
                'commentaire',
                'visite_id',
                'appel_id',
                'created_at',
                'updated_at'
            )
            ->with([
                'user' => function($query) {
                    $query->select('id', 'name', 'prenom')
                        ->without('societe');
                }
            ])
            ->where('prospect_id', $id)
            ->without('prospect')
            ->get();

        // 2. Récupérer les statuts de client si le prospect est devenu client
        $statutsClient = collect();
        if ($prospect->client_id) {
            $statutsClient = StatutClient::on('temp')
                ->select(
                    'id',
                    'statut',
                    'user_id_traite',
                    'date_traitement',
                    'commentaire',
                    'visite_id',
                    'created_at',
                    'updated_at',
                    'reservation_id',
                    'avance_id',
                    'desistement_id',
                    'penalite_id',
                    'remboursement_id',
                    'client_id',
                    'rdv_id'
                )
                ->where('client_id', $prospect->client_id)
                ->with([
                    'user' => function($query) {
                        $query->select('id', 'name', 'prenom')
                            ->without('societe');
                    },
                    'reservation' => function($query) {
                        $query->select('id', 'code_reservation');
                    },
                    'avance' => function($query) {
                        $query->select('id', 'montant');
                    },
                    'rdv' => function($query) {
                        $query->select('id', 'rdv');
                    }
                ])
                ->without('client')
                ->get();
        }

        // 3. Combiner et formater les résultats avec UNIQUE IDs
        $allHistoriques = collect();
        $counter = 0; // Add counter to ensure uniqueness

        // Ajouter les statuts de prospect
        foreach ($statutsProspect as $statut) {
            $allHistoriques->push([
                'id' => 'prospect_'.$statut->id, // Keep original ID
                'prospect_id' => $statut->prospect_id,
                'statut' => $statut->statut,
                'user_id_traite' => $statut->user_id_traite,
                'date_traitement' => $statut->date_traitement,
                'rdv' => $statut->rdv,
                'date_rappel' => $statut->date_rappel,
                'commentaire' => $statut->commentaire,
                'visite_id' => $statut->visite_id,
                'appel_id' => $statut->appel_id,
                'created_at' => $statut->created_at,
                'updated_at' => $statut->updated_at,
                'type_source' => 'prospect',
                'reservation_id' => null,
                'avance_id' => null,
                'desistement_id' => null,
                'penalite_id' => null,
                'remboursement_id' => null,
                'client_id' => null,
                'user' => $statut->user ? [
                    'id' => $statut->user->id,
                    'name' => $statut->user->name,
                    'prenom' => $statut->user->prenom
                ] : null
            ]);
        }

        // Ajouter les statuts de client
        foreach ($statutsClient as $statut) {
            $rdvDate = null;
            if ($statut->rdv_id && $statut->rdv) {
                $rdvDate = $statut->rdv->rdv;
            }
            $allHistoriques->push([
               'id' => 'client'.$statut->id, // Keep original ID
                'prospect_id' => null,
                'statut' => $statut->statut,
                'user_id_traite' => $statut->user_id_traite,
                'date_traitement' => $statut->date_traitement,
                'rdv' => $rdvDate,
                'date_rappel' => null,
                'commentaire' => $statut->commentaire,
                'visite_id' => $statut->visite_id,
                'appel_id' => null,
                'created_at' => $statut->created_at,
                'updated_at' => $statut->updated_at,
                'type_source' => 'client',
                'reservation_id' => $statut->reservation_id,
                'avance_id' => $statut->avance_id,
                'desistement_id' => $statut->desistement_id,
                'penalite_id' => $statut->penalite_id,
                'remboursement_id' => $statut->remboursement_id,
                'client_id' => $statut->client_id,
                'rdv_id' => $statut->rdv_id,
                'user' => $statut->user ? [
                    'id' => $statut->user->id,
                    'name' => $statut->user->name,
                    'prenom' => $statut->user->prenom
                ] : null,
                'reservation' => $statut->reservation ? [
                    'id' => $statut->reservation->id,
                    'code_reservation' => $statut->reservation->code_reservation
                ] : null,
                'avance' => $statut->avance ? [
                    'id' => $statut->avance->id,
                    'montant' => $statut->avance->montant
                ] : null,
                'rendez_vous' => $statut->rdv ? [
                    'id' => $statut->rdv->id,
                    'rdv' => $statut->rdv->rdv,
                ] : null
            ]);
        }

        // ... rest of your filtering code remains the same ...

        // 4. Appliquer les filtres
        $filteredHistoriques = $allHistoriques;

        if ($request->filled('date_traitement')) {
            $date = Carbon::parse($request->input('date_traitement'))->format('Y-m-d');
            $filteredHistoriques = $filteredHistoriques->filter(function ($item) use ($date) {
                return $item['date_traitement'] &&
                    Carbon::parse($item['date_traitement'])->format('Y-m-d') == $date;
            });
        }

        if ($request->filled('rdv')) {
            $date = Carbon::parse($request->input('rdv'))->format('Y-m-d');
            $filteredHistoriques = $filteredHistoriques->filter(function ($item) use ($date) {
                if ($item['rdv']) {
                    return Carbon::parse($item['rdv'])->format('Y-m-d') == $date;
                }
                return false;
            });
        }

        if ($request->filled('date_rappel')) {
            $date = Carbon::parse($request->input('date_rappel'))->format('Y-m-d');
            $filteredHistoriques = $filteredHistoriques->filter(function ($item) use ($date) {
                return $item['date_rappel'] &&
                    Carbon::parse($item['date_rappel'])->format('Y-m-d') == $date;
            });
        }

        if ($request->filled('statut')) {
            $statutValue = $request->input('statut');
            if (str_starts_with($statutValue, 'P_')) {
                $statutId = str_replace('P_', '', $statutValue);
                $filteredHistoriques = $filteredHistoriques->filter(function ($item) use ($statutId) {
                    return $item['type_source'] === 'prospect' &&
                        $item['statut'] == $statutId;
                });
            } elseif (str_starts_with($statutValue, 'C_')) {
                $statutId = str_replace('C_', '', $statutValue);
                $filteredHistoriques = $filteredHistoriques->filter(function ($item) use ($statutId) {
                    return $item['type_source'] === 'client' &&
                        $item['statut'] == $statutId;
                });
            } else {
                $filteredHistoriques = $filteredHistoriques->filter(function ($item) use ($request) {
                    return $item['statut'] == $request->statut;
                });
            }
        }

        // 5. Trier et paginer
        $sortedHistoriques = $filteredHistoriques->sortByDesc('created_at')->values();
        $totalItems = $sortedHistoriques->count();

        $historiques = $sortedHistoriques->slice(($page - 1) * $size, $size)->values();

        // 7. Informations de pagination
        $pagination = [
            'currentPage' => (int)$page,
            'totalItems'  => $totalItems,
            'totalPages'  => ceil($totalItems / $size),
            'perPage' => $size
        ];

        return response()->json([
            'historiques' => $historiques,
            'pagination'  => $pagination,
        ], 200);
    }

    return response()->json(['error' => 'Unauthorized'], 401);
}
public function export_historiques(Request $request, $id)
{
    if (Auth::guard('api')->check()) {
        $dateStart = $request->input('date_start');
        $dateEnd   = $request->input('date_end');

        DatabaseHelper::Config();

        // Récupérer le prospect pour vérifier s'il est devenu client
        $prospect = Prospect::on('temp')->find($id);

        if (!$prospect) {
            return response()->json(['error' => 'Prospect non trouvé'], 404);
        }

        // 1. Récupérer les statuts de prospect
        $statutsProspect = StatutProspect::on('temp')
            ->select(
                'id',
                'prospect_id',
                'statut',
                'user_id_traite',
                'date_traitement',
                'rdv',
                'date_rappel',
                'commentaire',
                'visite_id',
                'appel_id',
                'created_at',
                'updated_at'
            )
            ->with([
                'user' => function($query) {
                    $query->select('id', 'name', 'prenom')
                        ->without('societe');
                }
            ])
            ->where('prospect_id', $id)
            ->without('prospect')
            ->get();

        // 2. Récupérer les statuts de client si le prospect est devenu client
        $statutsClient = collect();
        if ($prospect->client_id) {
            $statutsClient = StatutClient::on('temp')
                ->select(
                    'id',
                    'statut',
                    'user_id_traite',
                    'date_traitement',
                    'commentaire',
                    'visite_id',
                    'created_at',
                    'updated_at',
                    'reservation_id',
                    'avance_id',
                    'desistement_id',
                    'penalite_id',
                    'remboursement_id',
                    'client_id',
                    'rdv_id'
                )
                ->where('client_id', $prospect->client_id)
                ->with([
                    'user' => function($query) {
                        $query->select('id', 'name', 'prenom')
                            ->without('societe');
                    },
                    'reservation' => function($query) {
                        $query->select('id', 'code_reservation');
                    },
                    'avance' => function($query) {
                        $query->select('id', 'montant');
                    },
                    'rdv' => function($query) {
                        $query->select('id', 'rdv');
                    }
                ])
                ->without('client')
                ->get();
        }

        // 3. Combiner et formater les résultats
        $allHistoriques = collect();

        // Ajouter les statuts de prospect
        foreach ($statutsProspect as $statut) {
            $allHistoriques->push([
                'id' => 'prospect_'.$statut->id,
                'prospect_id' => $statut->prospect_id,
                'statut' => $statut->statut,
                'user_id_traite' => $statut->user_id_traite,
                'date_traitement' => $statut->date_traitement,
                'rdv' => $statut->rdv,
                'date_rappel' => $statut->date_rappel,
                'commentaire' => $statut->commentaire,
                'visite_id' => $statut->visite_id,
                'appel_id' => $statut->appel_id,
                'created_at' => $statut->created_at,
                'updated_at' => $statut->updated_at,
                'type_source' => 'prospect',
                'reservation_id' => null,
                'avance_id' => null,
                'desistement_id' => null,
                'penalite_id' => null,
                'remboursement_id' => null,
                'client_id' => null,
                'user' => $statut->user ? [
                    'id' => $statut->user->id,
                    'name' => $statut->user->name,
                    'prenom' => $statut->user->prenom
                ] : null
            ]);
        }

        // Ajouter les statuts de client
        foreach ($statutsClient as $statut) {
            $rdvDate = null;
            if ($statut->rdv_id && $statut->rdv) {
                $rdvDate = $statut->rdv->rdv;
            }
            $allHistoriques->push([
                'id' => 'client'.$statut->id,
                'prospect_id' => null,
                'statut' => $statut->statut,
                'user_id_traite' => $statut->user_id_traite,
                'date_traitement' => $statut->date_traitement,
                'rdv' => $rdvDate,
                'date_rappel' => null,
                'commentaire' => $statut->commentaire,
                'visite_id' => $statut->visite_id,
                'appel_id' => null,
                'created_at' => $statut->created_at,
                'updated_at' => $statut->updated_at,
                'type_source' => 'client',
                'reservation_id' => $statut->reservation_id,
                'avance_id' => $statut->avance_id,
                'desistement_id' => $statut->desistement_id,
                'penalite_id' => $statut->penalite_id,
                'remboursement_id' => $statut->remboursement_id,
                'client_id' => $statut->client_id,
                'rdv_id' => $statut->rdv_id,
                'user' => $statut->user ? [
                    'id' => $statut->user->id,
                    'name' => $statut->user->name,
                    'prenom' => $statut->user->prenom
                ] : null,
                'reservation' => $statut->reservation ? [
                    'id' => $statut->reservation->id,
                    'code_reservation' => $statut->reservation->code_reservation
                ] : null,
                'avance' => $statut->avance ? [
                    'id' => $statut->avance->id,
                    'montant' => $statut->avance->montant
                ] : null,
                'rendez_vous' => $statut->rdv ? [
                    'id' => $statut->rdv->id,
                    'rdv' => $statut->rdv->rdv,
                ] : null
            ]);
        }

        // 4. Appliquer les filtres (incluant les nouvelles dates)
        $filteredHistoriques = $allHistoriques;

        // ✅ Filtre date_start / date_end (pour l'export)
        if ($dateStart) {
            $start = Carbon::parse($dateStart)->startOfDay();
            $filteredHistoriques = $filteredHistoriques->filter(function ($item) use ($start) {
                return $item['date_traitement'] &&
                    Carbon::parse($item['date_traitement'])->gte($start);
            });
        }

        if ($dateEnd) {
            $end = Carbon::parse($dateEnd)->endOfDay();
            $filteredHistoriques = $filteredHistoriques->filter(function ($item) use ($end) {
                return $item['date_traitement'] &&
                    Carbon::parse($item['date_traitement'])->lte($end);
            });
        }

        // 5. Trier (SANS pagination)
        $sortedHistoriques = $filteredHistoriques->sortByDesc('created_at')->values();

        // ✅ Retourner TOUTES les données
        return response()->json([
            'data'  => $sortedHistoriques,
            'total' => $sortedHistoriques->count(),
        ], 200);
    }

    return response()->json(['error' => 'Unauthorized'], 401);
}
    /*public function get_Historiques_by_prospect($id, Request $request)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', config('app.default_item_number_perpage')); // Default size if not provided
            $page = $request->input('page', 1);                                         // Default page if not provided

            DatabaseHelper::Config();

            $query = StatutProspect::on('temp')
            ->with(['visite' => function($query) {
                $query->select('id','origin_id')->without('historique_bien_visite','bien','partenaire','prospect','source','user'); // Fixed syntax
            },
                 'user' => function($query) {
                $query->select('id','name','prenom')->without('societe'); // Fixed syntax
            },
            ])->where('prospect_id', $id)->without('prospect')->orderBy('date_traitement', 'desc');
            // Optional filters (Add more if needed)

            if ($request->filled('date_traitement')) {
                $start = Carbon::parse($request->input('date_traitement'));
                $query->whereDate('date_traitement', $start);
            }
            if ($request->filled('rdv')) {
                $start = Carbon::parse($request->input('rdv'));
                $query->whereDate('rdv', $start);
            }
            if ($request->filled('date_rappel')) {
                $start = Carbon::parse($request->input('date_rappel'));
                $query->whereDate('date_rappel', $start);
            }
            if ($request->filled('statut')) {
                $query->where('statut', $request->statut);
            }

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                // Paginate if size and page are valid
                $historiques = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Add pagination info
                $pagination = [
                    'currentPage' => $historiques->currentPage(),
                    'totalItems'  => $historiques->total(),
                    'totalPages'  => $historiques->lastPage(),
                ];

                $historiques = $historiques->items();

                return response()->json([
                    'historiques' => $historiques,
                    'pagination'  => $pagination,
                ], 200);
            }
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
    public function store(StoreProspectRequest $request)
    {
        if (RoleHelper::ACSup() || RoleHelper::AgentAdmin() || RoleHelper::RespoCommercial()) {

            // Démarrer une transaction
            DB::connection('temp')->beginTransaction();

            try {
                $user = Auth::user();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();
                DatabaseHelper::Config();

                $prospect = new Prospect();
                $prospect->setConnection("temp");
                $prospect->user_id_add = $userAuth ? $userAuth->id : null;

                if(RoleHelper::Com()){
                    $prospect->commercial_affecte = $userAuth ? $userAuth->id : null;
                    $prospect->affecte_par_admin_id = $userAuth ? $userAuth->id : null;
                    $prospect->date_affectation = Carbon::now();
                }

                $prospect->cin            = $request->cin;
                $prospect->nom            = $request->nom;
                $prospect->nom            = $request->nom;
                $prospect->prenom         = $request->prenom;
                $prospect->telephone      = $request->telephone;
                $prospect->telephone_num2 = $request->telephone_num2 == "null" ? '' : $request->telephone_num2;
                $prospect->email          = $request->email;
                $prospect->origin         = $request->origin != null ? $request->origin : 'manuel';
                $prospect->notifie        = $request->notifie;
                $prospect->source         = $request->source;
                $prospect->partenaire_id  = $request->partenaire_id;
                $prospect->message        = $request->message;
                $prospect->ville          = $request->ville;
                $prospect->projet_id      = $request->projet_id;
                $prospect->etat  = $request->lead_qualifie ?? true; // true par défaut si non fourni


                if ($prospect->save()) {

                    if ($request->has('prospect_fusion_ids')) {
                        $fusionIds = is_array($request->prospect_fusion_ids)
                            ? $request->prospect_fusion_ids
                            : json_decode($request->prospect_fusion_ids, true);

                        if (is_array($fusionIds)) {
                            $userId = $userAuth ? $userAuth->id : null;

                            foreach ($fusionIds as $fusionId) {
                                $fusion = new ProspectFusion();
                                $fusion->setConnection('temp');
                                $fusion->prospect_principal_id = $prospect->id;
                                $fusion->prospect_fusion_id = $fusionId;
                                $fusion->user_id = $userId;
                                $fusion->save();
                            }
                        }
                    }
                    // Create default "en_attente" status unless created from a visite
                    if (($prospect->origin ?? ($request->origin ?? null)) !== 'visite') {

                        // Si commercial, ajouter le statut "Affecté"
                        if (RoleHelper::Com()) {
                            $statutProspect = new StatutProspect();
                            $statutProspect->setConnection('temp');
                            $statutProspect->prospect_id = $prospect->id;
                            $statutProspect->statut = (string) StatutProspectEnum::Affecte->value;
                            $statutProspect->date_traitement = Carbon::now();
                            $statutProspect->user_id_traite = $userAuth->id;
                            $statutProspect->commentaire = 'Prospect affecté au commercial';
                            $statutProspect->save();
                        }else{

                        //  AFFECTATION AUTOMATIQUE — CYCLE 50% PRIORITAIRE
                        // ============================================================
                        $systemUser = User::on('temp')
                            ->where('role', 1)
                            ->whereNull('deleted_at')
                            ->first();

                        $assignment = CommercialAssignmentHelper::assignProspect(
                            $prospect->id,
                            $request->projet_id,
                            'temp',
                            $systemUser?->id,
                            'manuel'
                        );

                        $assignedCommercialId = $assignment['success']
                            ? $assignment['commercial_id']
                            : null;

                        if (!$assignment['success']) {
                            Log::warning('⚠️ assignment failed', [
                                'prospect_id' => $prospect->id,
                                'message'     => $assignment['message'] ?? 'unknown',
                            ]);
                        }
                        // ============================================================
                        //  STATUT INITIAL
                        // ============================================================
                        $statutProspect = new StatutProspect();
                        $statutProspect->setConnection('temp');
                        $statutProspect->prospect_id = $prospect->id;
                        $statutProspect->date_traitement = Carbon::now();

                        if ($assignedCommercialId) {
                            $statutProspect->statut = (string) StatutProspectEnum::Affecte->value;
                            $statutProspect->user_id_traite = $systemUser ? $systemUser->id : null;
                            $statutProspect->commentaire = 'Prospect créé manuellement';
                        } else {
                            $statutProspect->statut = '0';
                            $statutProspect->user_id_traite = null;
                            $statutProspect->commentaire = 'Prospect créé manuellement';
                        }
                        $statutProspect->type_traitement_rdv_relance = 0;
                        $statutProspect->save();
                        if($assignedCommercialId) {
                          $this->sendAffectationNotification($assignedCommercialId, $prospect->id, $prospect->projet_id);
                        }

                        }



                    }

                    // Valider la transaction si tout s'est bien passé
                    DB::connection('temp')->commit();

                    return $prospect;
                } else {
                    // La sauvegarde a échoué, annuler la transaction
                    DB::connection('temp')->rollBack();
                    return response()->json(['error' => 'Erreur lors de la création du prospect'], 500);
                }

            } catch (\Exception $e) {
                // Une erreur est survenue, annuler la transaction
                DB::connection('temp')->rollBack();

                // Log de l'erreur pour le débogage
                \Log::error('Erreur création prospect: ' . $e->getMessage());

                return response()->json([
                    'error' => 'Erreur lors de la création du prospect',
                    'message' => $e->getMessage()
                ], 500);
            }

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /*public static function Store_WhatsApp($phone_number_id, $from, $msg_body, $name, $societe_id, $projet_id = null)
    {
        DatabaseHelper::Config($societe_id);
        $prospect = new Prospect();
        $prospect->setConnection("temp");
        $prospect->cin       = null;
        $prospect->message   = $msg_body;
        $prospect->nom       = $name;
        $prospect->telephone = $from;
        $prospect->email     = null;
        $prospect->origin    = 'whatsapp';
        $prospect->source    = 1;
        $prospect->etat    = 1;
        $prospect->projet_id = $projet_id; // link prospect to project from WhatsApp config
        $prospect->save();

        // Create default "en_attente" status
        $statutProspect = new StatutProspect();
        $statutProspect->setConnection('temp');
        $statutProspect->prospect_id = $prospect->id;
        // Enforce numeric-only status storage for WhatsApp
        $statutProspect->statut = '0';
        $statutProspect->date_traitement = Carbon::now()->toDateString();
        $statutProspect->user_id_traite = null; // No specific user for WhatsApp
        $statutProspect->commentaire = 'Prospect créé via WhatsApp';
        $statutProspect->save();

        // Create a notification for the new WhatsApp prospect
        try {
            $notif_helper = new NotificationHelper();
            $req = new \Illuminate\Http\Request();

            // Ensure notification has a projet_id even if prospect was left null (ambiguous auto-link)
            $notifProjetId = $projet_id;
            if (!$notifProjetId && $phone_number_id) {
                try {
                    $cfg = DB::connection('temp')
                        ->table('whatsapp_configurations')
                        ->where('phone_number_id', $phone_number_id)
                        ->whereNull('deleted_at')
                        ->first();
                    if ($cfg && isset($cfg->projet_id)) {
                        $notifProjetId = $cfg->projet_id;
                    }
                } catch (\Throwable $e) {
                    // leave as null if lookup fails
                }
            }

            $notif_helper->storeNotification($req->merge([
                'lien'        => '/crm/prospects/' . $prospect->id,
                'date'        => Carbon::now(),
                'type'        => 51, // custom type for WhatsApp prospect
                'description' => 'Nouveau prospect via WhatsApp',
                'user_id'     => null,
                'role'        => null,
                'visite_id'   => null,
                'prospect_id' => $prospect->id,
                'projet_id'   => $notifProjetId,
            ]));
        } catch (\Exception $e) {
            Log::warning('Failed to create WhatsApp notification: ' . $e->getMessage());
        }
    }*/

      public static function Store_fcb_instagram($msg_body, $name,$type, $societe_id, $projet_id = null, $phoneNumber = null, $facebookId = null)
    {
        DatabaseHelper::Config($societe_id);
        $prospect = new Prospect();
        $prospect->setConnection("temp");
        $prospect->cin       = null;
        $prospect->message   = $msg_body;
        $prospect->nom       = $name;
        $prospect->telephone = $phoneNumber;
        $prospect->email     = null;
          $prospect->facebook_id     = $facebookId;
        $prospect->origin    = $type;
        $prospect->source    = null;
        $prospect->etat    = 1;
        $prospect->projet_id = $projet_id; // link prospect to project from WhatsApp config
        $prospect->save();

        // Create default "en_attente" status
        $statutProspect = new StatutProspect();
        $statutProspect->setConnection('temp');
        $statutProspect->prospect_id = $prospect->id;
        // Enforce numeric-only status storage for WhatsApp
        $statutProspect->statut = '0';
        $statutProspect->date_traitement = Carbon::now()->toDateString();
        $statutProspect->user_id_traite = null; // No specific user for WhatsApp
        $statutProspect->commentaire = 'Prospect créé via '.$type;
        $statutProspect->save();

        // Create a notification for the new WhatsApp prospect
        try {
            $notif_helper = new NotificationHelper();
            $req = new \Illuminate\Http\Request();

            // Ensure notification has a projet_id even if prospect was left null (ambiguous auto-link)

            $notif_helper->storeNotification($req->merge([
                'lien'        => '/crm/prospects/' . $prospect->id,
                'date'        => Carbon::now(),
                'type'        => 101,
                'description' => 'Nouveau prospect via '.$type,
                'user_id'     => null,
                'role'        =>  \App\Enum\RoleEnum::ADMIN_COMMERCIAL->value,
                'visite_id'   => null,
                'prospect_id' => $prospect->id,
                'projet_id'   => $projet_id,
            ]));
        } catch (\Exception $e) {
            Log::warning('Failed to create '.$type.'notification: ' . $e->getMessage());
        }
    }

/*public static function Store_LandingPage($name, $prenom, $phone, $email, $societe_id, $comment = null, $projet_id)
{
    DatabaseHelper::Config($societe_id);

    Log::info('🔄 Store_LandingPage: Starting', [
        'name' => $name,
        'phone' => $phone,
        'projet_id' => $projet_id,
    ]);

    DB::connection('temp')->beginTransaction();

    try {
        // ============================================================
        // 1️⃣ CRÉATION DU PROSPECT
        // ============================================================
        // ✅ Recherche dynamique de la source "Landing Page"
            $source = \App\Models\Source::on('temp')
                ->where('source', 'LIKE', '%landing%page%')
                ->orWhere('source', 'LIKE', '%landingpage%')
                ->orWhere('source', 'LIKE', '%landing page%')
                ->whereNull('deleted_at')  // si tu as du soft delete
                ->first();

        $prospect = new Prospect();
        $prospect->setConnection("temp");
        $prospect->cin       = null;
        $prospect->message   = $comment;
        $prospect->nom       = $name;
        $prospect->prenom    = $prenom;
        $prospect->telephone = $phone;
        $prospect->email     = $email;
        $prospect->projet_id = $projet_id;
        $prospect->origin    = 'landingPage';
        $prospect->source = $source ? $source->id : 3; // ou 17 en fallback si tu veux
        $prospect->etat      = 1;
        $prospect->save();

        Log::info('✅ Prospect created via LandingPage', ['prospect_id' => $prospect->id]);

        // ============================================================
        // 2️⃣ AFFECTATION AUTOMATIQUE (même logique que autoAssignSingleProspect)
        // ============================================================
        $assignedCommercialId = null;
        $systemUser = User::on('temp')
            ->where('role', 1)
            ->whereNull('deleted_at')
            ->first();

        $commercials = User::on('temp')
            ->whereHas('projets', function ($q) use ($projet_id) {
                $q->where('projet_id', $projet_id);
            })
            ->where('role', 3)
            ->where('is_actif', 1)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        if ($commercials->isNotEmpty()) {
            $targetCommercial = null;

            if ($commercials->count() === 1) {
                $targetCommercial = $commercials->first();
                Log::info('✅ Only one commercial found', ['commercial_id' => $targetCommercial->id]);
            } else {
                // Reset si tous ont last_affected = 1
                $allHaveLastAffected = $commercials->every(fn($c) => (int) $c->last_affected === 1);

                if ($allHaveLastAffected) {
                    Log::info('🔄 All commercials have last_affected = 1, resetting all to 0');
                    User::on('temp')
                        ->where('role', 3)
                        ->where('is_actif', 1)
                        ->whereNull('deleted_at')
                        ->update(['last_affected' => 0]);

                    $commercials = User::on('temp')
                        ->whereHas('projets', function ($q) use ($projet_id) {
                            $q->where('projet_id', $projet_id);
                        })
                        ->where('role', 3)
                        ->where('is_actif', 1)
                        ->whereNull('deleted_at')
                        ->orderBy('id')
                        ->get();
                }

                // Prendre le premier avec last_affected = 0
                $targetCommercial = $commercials->firstWhere('last_affected', 0)
                    ?? $commercials->first();

                Log::info('✅ Selected commercial', [
                    'commercial_id' => $targetCommercial->id,
                    'name' => $targetCommercial->name . ' ' . $targetCommercial->prenom,
                    'last_affected_before' => $targetCommercial->last_affected,
                ]);
            }

            if ($targetCommercial) {
                $assignedCommercialId = $targetCommercial->id;

                // Mettre à jour le prospect
                $prospect->commercial_affecte   = $assignedCommercialId;
                $prospect->affecte_par_admin_id = $systemUser ? $systemUser->id : $assignedCommercialId;
                $prospect->date_affectation     = Carbon::now();
                $prospect->save();

                // Incrémenter nb_prospects + last_affected = 1
                $targetCommercial->nb_prospects = ((int) $targetCommercial->nb_prospects) + 1;
                $targetCommercial->last_affected = 1;
                $targetCommercial->save();

                // Reset last_affected = 0 pour les autres
                User::on('temp')
                    ->where('id', '!=', $assignedCommercialId)
                    ->where('role', 3)
                    ->where('is_actif', 1)
                    ->whereNull('deleted_at')
                    ->update(['last_affected' => 0]);

                Log::info('✅ Prospect assigned automatically', [
                    'prospect_id' => $prospect->id,
                    'commercial_id' => $assignedCommercialId,
                ]);
            }
        } else {
            Log::warning('⚠️ No active commercials found for project', ['projet_id' => $projet_id]);
        }

        // ============================================================
        // 3️⃣ STATUT INITIAL
        // ============================================================
        $statutProspect = new StatutProspect();
        $statutProspect->setConnection('temp');
        $statutProspect->prospect_id = $prospect->id;
        $statutProspect->date_traitement = Carbon::now();

        if ($assignedCommercialId) {
            $statutProspect->statut = (string) StatutProspectEnum::Affecte->value; // '6'
            $statutProspect->user_id_traite = $systemUser ? $systemUser->id : null;
            $statutProspect->commentaire = 'Prospect créé via Landing Page et affecté automatiquement';
        } else {
            $statutProspect->statut = '0';
            $statutProspect->user_id_traite = null;
            $statutProspect->commentaire = 'Prospect créé via Landing Page ';
        }
        $statutProspect->type_traitement_rdv_relance = 0;
        $statutProspect->save();

        // ============================================================
        // 4️⃣ COMMIT
        // ============================================================
        DB::connection('temp')->commit();
        Log::info('✅ Transaction committed for LandingPage prospect', ['prospect_id' => $prospect->id]);

        // ============================================================
        // 5️⃣ NOTIFICATIONS (hors transaction)
        // ============================================================
        try {
            $notif_helper = new NotificationHelper();
            $req = new \Illuminate\Http\Request();
            // Notification au commercial affecté
            if ($assignedCommercialId) {
                Config::set('broadcasting.default', 'pusher_notify');
                $commercial = User::on('temp')->find($assignedCommercialId);

                if ($commercial && $commercial->user_id_origin) {
                    $notif_helper->storeNotification($req->merge([
                        'lien'        => '/crm/prospects/' . $prospect->id,
                        'date'        => Carbon::now(),
                        'type'        => 53,
                        'description' => 'Un nouveau prospect (Landing Page) vous a été affecté',
                        'user_id'     => $commercial->user_id_origin,
                        'role'        => null,
                        'visite_id'   => null,
                        'prospect_id' => $prospect->id,
                        'projet_id'   => $projet_id,
                    ]));
                    broadcast(new \App\Events\NotificationEvent($assignedCommercialId));
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to create landing page notification: ' . $e->getMessage());
        }

        return $prospect;

    } catch (\Exception $e) {
        DB::connection('temp')->rollBack();
        Log::error('❌ Store_LandingPage failed: ' . $e->getMessage());
        Log::error('Trace: ' . $e->getTraceAsString());
        return null;
    }
}*/

public static function Store_LandingPage($name, $prenom, $phone, $email, $societe_id, $comment = null, $projet_id)
{
    DatabaseHelper::Config($societe_id);

    Log::info('🔄 Store_LandingPage: Starting', [
        'name' => $name,
        'phone' => $phone,
        'projet_id' => $projet_id,
    ]);

    DB::connection('temp')->beginTransaction();

    try {
        // ============================================================
        // 1️⃣ CRÉATION DU PROSPECT
        // ============================================================
        $source = \App\Models\Source::on('temp')
            ->where(function ($q) {
                $q->where('source', 'LIKE', '%landing%page%')
                  ->orWhere('source', 'LIKE', '%landingpage%')
                  ->orWhere('source', 'LIKE', '%landing page%');
            })
            ->whereNull('deleted_at')
            ->first();

        $prospect = new Prospect();
        $prospect->setConnection("temp");
        $prospect->cin       = null;
        $prospect->message   = $comment;
        $prospect->nom       = $name;
        $prospect->prenom    = $prenom;
        $prospect->telephone = $phone;
        $prospect->email     = $email;
        $prospect->projet_id = $projet_id;
        $prospect->origin    = 'landingPage';
        $prospect->source    = $source ? $source->id : 3;
        $prospect->etat      = 1;
        $prospect->save();

        Log::info('✅ Prospect created via LandingPage', ['prospect_id' => $prospect->id]);

        // ============================================================
        // 2️⃣ AFFECTATION AUTOMATIQUE — CYCLE 50% PRIORITAIRE
        // ============================================================
        $systemUser = User::on('temp')
            ->where('role', 1)
            ->whereNull('deleted_at')
            ->first();

        $assignment = CommercialAssignmentHelper::assignProspect(
            $prospect->id,
            $projet_id,
            'temp',
            $systemUser?->id,
            'LandingPage'
        );

        $assignedCommercialId = $assignment['success']
            ? $assignment['commercial_id']
            : null;

        if (!$assignment['success']) {
            Log::warning('⚠️ LandingPage assignment failed', [
                'prospect_id' => $prospect->id,
                'message'     => $assignment['message'] ?? 'unknown',
            ]);
        }
        // ============================================================
        // 3️⃣ STATUT INITIAL
        // ============================================================
        $statutProspect = new StatutProspect();
        $statutProspect->setConnection('temp');
        $statutProspect->prospect_id = $prospect->id;
        $statutProspect->date_traitement = Carbon::now();

        if ($assignedCommercialId) {
            $statutProspect->statut = (string) StatutProspectEnum::Affecte->value;
            $statutProspect->user_id_traite = $systemUser ? $systemUser->id : null;
            $statutProspect->commentaire = 'Prospect créé via Landing Page et affecté automatiquement';
        } else {
            $statutProspect->statut = '0';
            $statutProspect->user_id_traite = null;
            $statutProspect->commentaire = 'Prospect créé via Landing Page';
        }
        $statutProspect->type_traitement_rdv_relance = 0;
        $statutProspect->save();

        // ============================================================
        // 4️⃣ COMMIT
        // ============================================================
        DB::connection('temp')->commit();
        Log::info('✅ Transaction committed for LandingPage prospect', ['prospect_id' => $prospect->id]);

        // ============================================================
        // 5️⃣ NOTIFICATIONS (hors transaction)
        // ============================================================
        try {
            $notif_helper = new NotificationHelper();
            $req = new \Illuminate\Http\Request();

            if ($assignedCommercialId) {
                Config::set('broadcasting.default', 'pusher_notify');
                $commercial = User::on('temp')->find($assignedCommercialId);

                if ($commercial && $commercial->user_id_origin) {
                    $notif_helper->storeNotification($req->merge([
                        'lien'        => '/crm/prospects/' . $prospect->id,
                        'date'        => Carbon::now(),
                        'type'        => 53,
                        'description' => 'Un nouveau prospect (Landing Page) vous a été affecté',
                        'user_id'     => $commercial->user_id_origin,
                        'role'        => null,
                        'visite_id'   => null,
                        'prospect_id' => $prospect->id,
                        'projet_id'   => $projet_id,
                    ]));
                    broadcast(new \App\Events\NotificationEvent($assignedCommercialId));
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to create landing page notification: ' . $e->getMessage());
        }

        return $prospect;

    } catch (\Exception $e) {
        DB::connection('temp')->rollBack();
        Log::error('❌ Store_LandingPage failed: ' . $e->getMessage());
        Log::error('Trace: ' . $e->getTraceAsString());
        return null;
    }
}
    /**
     * Display the specified resource.
     */
public function show($id)
{
    if (Auth::guard('api')->check()) {
        DatabaseHelper::Config();

        // =====================================================
        // ⭐ PROSPECT (KIMA KAN - MA TBEDELCH!)
        // =====================================================
        $prospect = Prospect::on('temp')->with([
            'client' => function($query) {
                $query->select('id')
                    ->with(['reservations' => function($q) {
                        $q->select('reservations.id', 'reservations.code_reservation', 'reservations.prix')
                            ->withSum('avances', 'montant')
                            ->where('etat', 1)
                            ->where('statut', 1)
                            ->whereRaw('reservations.prix > COALESCE((SELECT SUM(montant) FROM avances WHERE reservation_id = reservations.id), 0)')
                            ->without('user', 'projet', 'historiques', 'piece_jointe', 'bien', 'aquereurs', 'aquereurs_ancien');
                    }])->without('partenaire');
            },
            'traite_par_user' => function($query) {
                $query->select('id', 'name', 'prenom');
            },
            'partenaire' => function($query) {
                $query->select('id', 'description');
            },
            'commercial_affecte' => function($query) {
                $query->select('id', 'user_id_origin');
            },
            'appels' => function($query) {
                $query->without('prospect', 'projet');
            },
            'affecte_par_admin' => function($query) {
                $query->select('id', 'name', 'prenom');
            }
        ])->findOrFail($id);

        // =====================================================
        // ⭐ APPELS
        // =====================================================
        $appels = [];
        if ($prospect->appels && $prospect->appels->id) {
            $appels = TraitementAppel::on('temp')
                ->with([
                    'appel' => function($query) {
                        $query->select('*')
                            ->with([
                                'prospect' => function($q) {
                                    $q->select('*')
                                      ->without('affecte_par_admin', 'commercial_affecte');
                                }
                            ])->without('projet');
                    }, 'frein', 'relance', 'rdv', 'tranche', 'bloc', 'immeuble', 'type_biens'
                ])->where('appel_id', $prospect->appels->id)
                ->get();
        }

        // =====================================================
        // ⭐ VISITES
        // =====================================================
        $visites = Visite::on('temp')
            ->where('etat', 1)
            ->where('prospect_id', $id)
            ->latest('created_at')
            ->get();

        $toutesLesVisites = $visites;

        $groupedVisites = $toutesLesVisites->map(function ($visite) {
            $firstVisite = $visite;
            return [
                'id'                  => $firstVisite->id,
                'origin_id'           => $firstVisite->origin_id,
                'nom_cc'              => $firstVisite->user ? $firstVisite->user->name : null,
                'prenom_cc'           => $firstVisite->user ? $firstVisite->user->prenom : null,
                'date'                => $firstVisite->created_at,
                'prospect_id'         => $firstVisite->prospect ? $firstVisite->prospect->id : null,
                'interet'             => $firstVisite->interet,
                'statut'              => $firstVisite->statut,
                'bien'                => $firstVisite->bien ? $firstVisite->bien : '',
                'bien_id'             => $firstVisite->bien_id ?? '',
            ];
        });

        // =====================================================
        // ⭐ FUSIONS (B SELECT DIRECT - BLA $with)
        // =====================================================

        // Fusions li had prospect HOWA PRINCIPAL fihom
        $fusionsCommePrincipalRaw = \App\Models\ProspectFusion::on('temp')
            ->where('prospect_principal_id', $id)
            ->whereNull('deleted_at')
            ->get();

        $fusionsCommePrincipal = [];
        foreach ($fusionsCommePrincipalRaw as $fusion) {
            // ⭐ Jib l-prospect fusionné b DB::table (bla model, bla $with)
            $prospectFusionne = \Illuminate\Support\Facades\DB::connection('temp')
                ->table('prospects')
                ->select('id', 'nom', 'prenom', 'telephone', 'telephone_num2', 'cin', 'email')
                ->where('id', $fusion->prospect_fusion_id)
                ->first();

            $fusionsCommePrincipal[] = [
                'id' => $fusion->id,
                'prospect_principal_id' => $fusion->prospect_principal_id,
                'prospect_fusion_id' => $fusion->prospect_fusion_id,
                'user_id' => $fusion->user_id,
                'created_at' => $fusion->created_at,
                'updated_at' => $fusion->updated_at,
                'prospectFusionne' => $prospectFusionne ? [
                    'id' => $prospectFusionne->id,
                    'nom' => $prospectFusionne->nom,
                    'prenom' => $prospectFusionne->prenom,
                    'telephone' => $prospectFusionne->telephone,
                    'telephone_num2' => $prospectFusionne->telephone_num2,
                    'cin' => $prospectFusionne->cin,
                    'email' => $prospectFusionne->email,
                ] : null,
            ];
        }

        // Fusion li had prospect HOWA FUSIONNÉ fiha
        $fusionCommeFusionneRaw = \App\Models\ProspectFusion::on('temp')
            ->where('prospect_fusion_id', $id)
            ->whereNull('deleted_at')
            ->first();

        $fusionCommeFusionne = null;
        if ($fusionCommeFusionneRaw) {
            // ⭐ Jib l-principal b DB::table (bla model, bla $with)
            $prospectPrincipal = \Illuminate\Support\Facades\DB::connection('temp')
                ->table('prospects')
                ->select('id', 'nom', 'prenom', 'telephone', 'telephone_num2', 'cin', 'email')
                ->where('id', $fusionCommeFusionneRaw->prospect_principal_id)
                ->first();

            $fusionCommeFusionne = [
                'id' => $fusionCommeFusionneRaw->id,
                'prospect_principal_id' => $fusionCommeFusionneRaw->prospect_principal_id,
                'prospect_fusion_id' => $fusionCommeFusionneRaw->prospect_fusion_id,
                'user_id' => $fusionCommeFusionneRaw->user_id,
                'created_at' => $fusionCommeFusionneRaw->created_at,
                'updated_at' => $fusionCommeFusionneRaw->updated_at,
                'prospectPrincipal' => $prospectPrincipal ? [
                    'id' => $prospectPrincipal->id,
                    'nom' => $prospectPrincipal->nom,
                    'prenom' => $prospectPrincipal->prenom,
                    'telephone' => $prospectPrincipal->telephone,
                    'telephone_num2' => $prospectPrincipal->telephone_num2,
                    'cin' => $prospectPrincipal->cin,
                    'email' => $prospectPrincipal->email,
                ] : null,
            ];
        }

        // =====================================================
        // ⭐ RÉPONSE
        // =====================================================

        return response()->json([
            'prospect'       => $prospect,
            'appels'         => $appels,
            'visites'        => $groupedVisites->values(),
            'fusions' => [
                'est_principal' => count($fusionsCommePrincipal) > 0,
                'est_fusionne'  => $fusionCommeFusionne !== null,
                'comme_principal' => $fusionsCommePrincipal,
                'comme_fusionne'  => $fusionCommeFusionne,
            ],
        ], 200);

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
public function update(UpdateProspectRequest $request, $id)
{
    if (RoleHelper::ACSup() || RoleHelper::AgentAdmin() || RoleHelper::RespoCommercial()) {
        DatabaseHelper::Config();

        if ($request->cin != null) {
            $cin_exist = Prospect::on('temp')->where('cin', $request->cin)->where('id', '!=', $id)->count();
            if ($cin_exist > 0) {
                return response()->json(['errors' => 'Le Cin que vous avez saisi ' . $request->cin . ' appartient à un autre utilisateur'], 422);
            }
        }

        $prospect = Prospect::on('temp')->findOrFail($id);
        $update = $request->all();

        // Handle commercial_affecte update
        if ($request->has('commercial_affecte')) {
            $user = User::on('temp')->where('user_id_origin', $request->commercial_affecte)->first();
            $oldCommercialId = $prospect->commercial_affecte;
            $newCommercialId = $user->id;

            if ($oldCommercialId != $newCommercialId) {
                if ($oldCommercialId) {
                    \App\Models\User::on('temp')
                        ->where('id', $oldCommercialId)
                        ->decrement('nb_prospects');
                }

                if ($newCommercialId) {
                    \App\Models\User::on('temp')
                        ->where('id', $newCommercialId)
                        ->increment('nb_prospects');
                }

                $user = Auth::user();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();
                if ($userAuth) {
                    $prospect->affecte_par_admin_id = $userAuth->id;
                    $prospect->date_affectation = Carbon::now();

                    if ($newCommercialId) {
                        $statutProspect = new StatutProspect();
                        $statutProspect->setConnection('temp');
                        $statutProspect->prospect_id = $id;
                        $statutProspect->statut = (string) StatutProspectEnum::Affecte->value;
                        $statutProspect->date_traitement = Carbon::now();
                        $statutProspect->user_id_traite = $userAuth->id;
                        $statutProspect->commentaire = 'Prospect affecté au commercial';
                        $statutProspect->save();

                        $this->sendAffectationNotification($newCommercialId, $id, $prospect->projet_id);
                    }
                }
            }

            $prospect->commercial_affecte = $newCommercialId;
        }

        // 🔥 CORRECTION : Utiliser 'etat' au lieu de 'lead_qualifie'
        if ($request->has('lead_qualifie')) {
            $prospect->etat = $request->lead_qualifie;
        }

        foreach ($update as $key => $value) {
            // Exclure les champs gérés spécialement
            // Note: 'lead_qualifie' n'est pas dans la base, on le traite séparément
            if (!in_array($key, ['commercial_affecte', 'affecte_par_admin_id', 'date_affectation', 'lead_qualifie'])) {
                $prospect->$key = $value;
            }
        }

        $prospect->save();
        return response()->json(['prospect' => $prospect], 200);
    } else {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
}
    /**
 * Send notification to commercial when prospect is assigned to them
 */
    private function sendAffectationNotification($commercialId, $prospectId, $projetId)
    {
        DatabaseHelper::Config();
        $user_id_origin  = User::on('temp')->find($commercialId);
        try {
            Config::set('broadcasting.default', 'pusher_notify');

            $data_notif = [
                'lien'        => '/crm/prospects/' . $prospectId,
                'date'        => Carbon::now(),
                'type'        => 53, // Type pour affectation de prospect
                'user_id'     => $user_id_origin->user_id_origin,
                'description' => 'Un prospect vous a été affecté',
                'projet_id'   => $projetId,
                'prospect_id' => $prospectId,
            ];

            $notif_helper = new NotificationHelper();
            $notif_helper->storeNotification(new Request($data_notif));
            broadcast(new NotificationEvent($commercialId));

        } catch (\Exception $e) {
            \Log::error('Erreur envoi notification affectation: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
   public function destroy($id)
{
    if (RoleHelper::ACSup() || RoleHelper::AgentAdmin() || RoleHelper::RespoCommercial()) {

        DatabaseHelper::Config();

        // =====================================================
        // ⭐ CHECK 1: Fusions (mohim!)
        // =====================================================
        $aDesFusions = \App\Models\ProspectFusion::on('temp')
            ->where(function ($q) use ($id) {
                $q->where('prospect_principal_id', $id)
                  ->orWhere('prospect_fusion_id', $id);
            })
            ->whereNull('deleted_at')
            ->exists();

        if ($aDesFusions) {
            return response()->json([
                'error' => 'Il est impossible de supprimer ce prospect car il est impliqué dans une ou plusieurs fusions. Veuillez d\'abord le défusionner.'
            ], 422);
        }

        // =====================================================
        // ⭐ CHECK 2: Visites, appels, client (li kayn)
        // =====================================================
        $prospect = Prospect::on('temp')->findOrFail($id);

        if (count($prospect->visites) > 0 || $prospect->appels != null || $prospect->client != null) {
            return response()->json([
                'error' => 'Il est impossible de supprimer ce Prospect car il possède plusieurs dossiers liés à des visites, des appels ou client.'
            ], 422);
        }

        // =====================================================
        // ⭐ M7I NOTIFICATIONS
        // =====================================================
        $notifications = Notification::on('temp')->where('prospect_id', $id)->get();
        if (count($notifications)) {
            foreach ($notifications as $nt) {
                $nt->forceDelete();
            }
        }

        // =====================================================
        // ⭐ M7I PROSPECT
        // =====================================================
        if ($prospect->delete()) {
            return response()->json(['message' => 'Prospect supprimé avec succès.'], 200);
        } else {
            return response()->json(['error' => "Le prospect n'a pas été supprimé."], 404);
        }

    } else {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
}

    /**
 * Return all common variants of a phone number.
 * Example: 212661250121 →
 *   ['212661250121', '+212661250121', '0661250121']
 */
private function getPhoneVariants($phone)
{
    $clean = preg_replace('/[^0-9]/', '', trim($phone)); // strip +, spaces, dashes

    $variants = [
        $phone,          // original (ex: +212661250121)
        $clean,          // digits only (ex: 212661250121)
        '+' . $clean,    // with plus (ex: +212661250121)
    ];

    // Morocco: handle 212 <-> 0
    if (str_starts_with($clean, '212')) {
        $local = '0' . substr($clean, 3);
        $variants[] = $local;                    // 0661250121
        $variants[] = '+212' . substr($clean, 3);
    } elseif (str_starts_with($clean, '0')) {
        $intl = '212' . substr($clean, 1);
        $variants[] = $intl;                     // 212661250121
        $variants[] = '+' . $intl;               // +212661250121
    }

    return array_values(array_unique($variants));
}
   public function search_prospect_by_param($param_1, $value, $projet_id)
{
    // Recherche par CIN ou email
    if (RoleHelper::ACSup() || RoleHelper::AgentAdmin() || RoleHelper::RespoCommercial()) {
        DatabaseHelper::Config();

        if ($param_1 == 'cin' || $param_1 == 'email') {
            $prospect = Prospect::on('temp')->with(
                'visite_pre_reserves',
                'visite_first',
                'visites_perdu',
                'visites_perdu.freins',
                'visites_perdu.freins.freinTranche',
                'visites_perdu.freins.FreinEtage',
                'visites_perdu.freins.FreinOrientation',
                'visites_perdu.freins.FreinTypologie',
                'visites_perdu.freins.FreinVue',
                'appels'
            )
                ->where($param_1, $value)->where('projet_id', $projet_id)
                ->get()->first();

            // Récupérer le client avec les réservations ayant des paiements en attente
            $client = Client::on('temp')->with(['prospect', 'reservations_actives' => function ($query) {
                $query->select('reservations.id', 'reservations.code_reservation', 'reservations.prix')
                    ->withSum('avances', 'montant')
                    ->where('etat', 1)
                    ->where('statut', 1)
                    ->whereRaw('reservations.prix > COALESCE((SELECT SUM(montant) FROM avances WHERE reservation_id = reservations.id), 0)');
            }])
                ->where($param_1, $value)
                //->where('projet_id', $projet_id)
                ->get()->first();

        } else {
            // 👇 téléphone — générer toutes les variantes
            $phoneVariants = $this->getPhoneVariants($value);

            // Recherche par téléphone
            $prospect = Prospect::on('temp')->with(
                'visite_pre_reserves',
                'visite_first',
                'visites_perdu',
                'visites_perdu.freins',
                'visites_perdu.freins.freinTranche',
                'visites_perdu.freins.FreinEtage',
                'visites_perdu.freins.FreinOrientation',
                'visites_perdu.freins.FreinTypologie',
                'visites_perdu.freins.FreinVue',
                'appels'
            )
                ->where(function ($query) use ($phoneVariants) {
                    $query->whereIn('telephone', $phoneVariants)
                        ->orWhereIn('telephone_num2', $phoneVariants);
                })
                //  ->where('projet_id', $projet_id)
                ->get()->first();

            // Récupérer le client avec les réservations ayant des paiements en attente
            $client = Client::on('temp')->with(['prospect', 'reservations' => function ($query) {
                $query->select('reservations.id', 'reservations.code_reservation', 'reservations.prix')
                    ->withSum('avances', 'montant')
                    ->where('etat', 1)
                    ->where('statut', 1)
                    ->whereRaw('reservations.prix > COALESCE((SELECT SUM(montant) FROM avances WHERE reservation_id = reservations.id), 0)');
            }])
                ->where(function ($query) use ($phoneVariants) {
                    $query->whereIn('telephone_num1', $phoneVariants)
                        ->orWhereIn('telephone_num2', $phoneVariants);
                })
                //->where('projet_id', $projet_id)
                ->get()->first();
        }

        // =====================================================
        // ⭐ FUSIONS: Récupérer les fusions du prospect
        // =====================================================
        $fusionsCommePrincipal = [];
        $fusionCommeFusionne = null;

        if ($prospect != null) {
            // Fusions où ce prospect est le PRINCIPAL
            $fusionsCommePrincipalRaw = \App\Models\ProspectFusion::on('temp')
                ->where('prospect_principal_id', $prospect->id)
                ->whereNull('deleted_at')
                ->get();

            foreach ($fusionsCommePrincipalRaw as $fusion) {
                $prospectFusionne = \Illuminate\Support\Facades\DB::connection('temp')
                    ->table('prospects')
                    ->select('id', 'nom', 'prenom', 'telephone', 'telephone_num2', 'cin', 'email')
                    ->where('id', $fusion->prospect_fusion_id)
                    ->first();

                if ($prospectFusionne) {
                    $fusionsCommePrincipal[] = [
                        'id' => $fusion->id,
                        'prospect_principal_id' => $fusion->prospect_principal_id,
                        'prospect_fusion_id' => $fusion->prospect_fusion_id,
                        'user_id' => $fusion->user_id,
                        'created_at' => $fusion->created_at,
                        'updated_at' => $fusion->updated_at,
                        'prospectFusionne' => [
                            'id' => $prospectFusionne->id,
                            'nom' => $prospectFusionne->nom,
                            'prenom' => $prospectFusionne->prenom,
                            'telephone' => $prospectFusionne->telephone,
                            'telephone_num2' => $prospectFusionne->telephone_num2,
                            'cin' => $prospectFusionne->cin,
                            'email' => $prospectFusionne->email,
                        ],
                    ];
                }
            }

            // Fusion où ce prospect est FUSIONNÉ
            $fusionCommeFusionneRaw = \App\Models\ProspectFusion::on('temp')
                ->where('prospect_fusion_id', $prospect->id)
                ->whereNull('deleted_at')
                ->first();

            if ($fusionCommeFusionneRaw) {
                $prospectPrincipal = \Illuminate\Support\Facades\DB::connection('temp')
                    ->table('prospects')
                    ->select('id', 'nom', 'prenom', 'telephone', 'telephone_num2', 'cin', 'email')
                    ->where('id', $fusionCommeFusionneRaw->prospect_principal_id)
                    ->first();

                if ($prospectPrincipal) {
                    $fusionCommeFusionne = [
                        'id' => $fusionCommeFusionneRaw->id,
                        'prospect_principal_id' => $fusionCommeFusionneRaw->prospect_principal_id,
                        'prospect_fusion_id' => $fusionCommeFusionneRaw->prospect_fusion_id,
                        'user_id' => $fusionCommeFusionneRaw->user_id,
                        'created_at' => $fusionCommeFusionneRaw->created_at,
                        'updated_at' => $fusionCommeFusionneRaw->updated_at,
                        'prospectPrincipal' => [
                            'id' => $prospectPrincipal->id,
                            'nom' => $prospectPrincipal->nom,
                            'prenom' => $prospectPrincipal->prenom,
                            'telephone' => $prospectPrincipal->telephone,
                            'telephone_num2' => $prospectPrincipal->telephone_num2,
                            'cin' => $prospectPrincipal->cin,
                            'email' => $prospectPrincipal->email,
                        ],
                    ];
                }
            }
        }

        // Biens pré-réservés par appel
        $biens_traitement_freins = [];
        if ($prospect != null) {
            $biens_traitement_freins = TraitementFrein::on('temp')->with('bien', 'visite')
                ->whereHas('visite', function ($q) use ($prospect) {
                    $q->where('prospect_id', $prospect->id);
                })
                ->where('interet', InteretEnum::Intéressé->value)
                ->where('statut', StatutVisiteEnum::Pré_Réservation->value)
                ->orderby('created_at', 'desc')
                ->get(['bien_id', 'id'])
                ->take(1);
        }

        // Transformer les données des réservations
        if ($client) {
            if ($client->reservations_actives) {
                $client->reservations_actives->transform(function ($reservation) {
                    return [
                        'id' => $reservation->id,
                        'code_reservation' => $reservation->code_reservation,
                        'prix' => $reservation->prix,
                        'avances_sum_montant' => $reservation->avances_sum_montant,
                    ];
                });
            }
            if ($client->reservations) {
                $client->reservations->transform(function ($reservation) {
                    return [
                        'id' => $reservation->id,
                        'code_reservation' => $reservation->code_reservation,
                        'prix' => $reservation->prix,
                        'avances_sum_montant' => $reservation->avances_sum_montant,
                    ];
                });
            }
        }

        // =====================================================
        // ⭐ RÉPONSE (avec les fusions)
        // =====================================================
        return response()->json([
            'prospect' => $prospect,
            'client' => $client,
            'biens_traitement_freins' => $biens_traitement_freins,
            'fusions' => [
                'est_principal' => count($fusionsCommePrincipal) > 0,
                'est_fusionne'  => $fusionCommeFusionne !== null,
                'comme_principal' => $fusionsCommePrincipal,
                'comme_fusionne'  => $fusionCommeFusionne,
            ],
        ]);
    }
}
    public function VisitesByprospect(Request $request, $prospect_id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage'));
            $page    = $request->input('page', 1);
            $visites = Visite::on('temp')->latest('created_at')->where('etat', 1)
                ->select('visites.*')
                ->where('prospect_id', $prospect_id)
                ->get()->groupby('origin_id');

            $visites = $visites->map(function ($visite) {
                return [
                    'id'                  => $visite->first()->id,
                    'origin_id'           => $visite->first()->origin_id,
                    'nom_cc'              => $visite->first()->user->name,
                    'prenom_cc'           => $visite->first()->user->prenom,
                    'date'                => $visite->first()->created_at,
                    'prospect_id'         => $visite->first()->prospect->id,
                    'cin'                 => $visite->first()->prospect->cin,
                    'nom'                 => $visite->first()->prospect->nom,
                    'prenom'              => $visite->first()->prospect->prenom,
                    'telephone'           => $visite->first()->prospect->telephone,
                    'telephone2'          => $visite->first()->prospect->telephone_num2,
                    'interet'             => $visite->first()->interet,
                    'statut'              => $visite->first()->statut,
                    'propriete_dite_bien' => $visite->first()->bien_id ? $visite->first()->bien->propriete_dite_bien : '',
                    'etat_bien'           => $visite->first()->bien_id ? $visite->first()->bien->etat : '',
                    'bien_id'             => $visite->first()->bien_id ? $visite->first()->bien_id : '',
                    'visit_count'         => count($visite),

                ];
            });

            $data = PaginationHelper::paginate_array($visites->toArray(), $perPage, $page, $request->url());
            return response()->json(['visites' => $data], 200);

        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

     public function upload(Request $request)
    {
         // ✅ Validate that 'jsonData' is required and must not be null
        $request->validate([
            'jsonData' => 'required',
        ], [
            'jsonData.required' => 'Le champ des données est obligatoire.',
        ]);
        if (RoleHelper::ACSup() || RoleHelper::AgentAdmin() ) {
            DatabaseHelper::Config();
            $projet_id = $request->projet_id;
            set_time_limit(0);
            ini_set('memory_limit', '-1');
            $data = json_decode($request->input('jsonData', '[]'), true);

            $user = Auth::user();
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();
            $user_societes = User::where('id', $userAuth->user_id_origin)->first();
            $societe = Societe::findOrfail($user_societes->societe_id);

            $imp = new Import();
            $imp->setConnection('temp');
            $imp->projet_id = $projet_id;
            $imp->statut = '0';
            $imp->user_id = $userAuth->id;
            $imp->data = $data;
            $imp->type = '3';//prospects

            // Handle file upload only if file exists
             // MODIFICATION: Utiliser FichierHelper
        if ($request->hasFile('piece_jointe')) {
            $file = $request->file('piece_jointe');
            $client_origin_name = $file->getClientOriginalName();
            $date = str_replace(str_split('\\/:*?"<>|+-\s+'), '_', date("Y-m-d H:i:s"));
            $filename = pathinfo($client_origin_name, PATHINFO_FILENAME) . '_' . $date;
            $extension = pathinfo($client_origin_name, PATHINFO_EXTENSION);
            $fullFilename = $filename . '.' . $extension;

            FichierHelper::ajouter_fichier(
                $file,
                $societe->raison_sociale_concatene,
                $societe->id,
                'Import_prospects',
                $fullFilename
            );
            $imp->fichier = $fullFilename;
        }

            $imp->save();
            return response()->json('done stock fichier');
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }


        /*afffecte commercial par moins nb prospect
    public function autoAssignProspects(Request $request)
    {
        if (RoleHelper::ACSup()  || RoleHelper::AgentAdmin()||RoleHelper::RespoCommercial()) {
            DatabaseHelper::Config();

            // Custom validation since we're using temp connection
            if (!$request->has('prospect_ids') || !is_array($request->prospect_ids)) {
                return response()->json(['error' => 'prospect_ids is required and must be an array'], 422);
            }

            if (!$request->has('projet_id')) {
                return response()->json(['error' => 'projet_id is required'], 422);
            }

            // Validate that prospects exist and can be assigned
            $prospects = Prospect::on('temp')
                ->with('last_statut')
                ->whereIn('id', $request->prospect_ids)
                ->where('projet_id', $request->projet_id)
                ->get();

            if ($prospects->count() !== count($request->prospect_ids)) {
                return response()->json(['error' => 'Some prospect IDs are invalid'], 422);
            }

            // Allow auto assignment regardless of final status
            try {
                \DB::connection('temp')->beginTransaction();

                // Use only assignable prospect IDs
                $prospectIds = $prospects->pluck('id')->toArray();
                $projetId = $request->projet_id;

                // Get all commercials for this project with their current prospect counts
                $commercials = \App\Models\User::on('temp')
                    ->whereHas('projets', function($query) use ($projetId) {
                        $query->where('projet_id', $projetId);
                    })
                    ->where('role', 3) // Assuming 3 is commercial role
                    ->get();

                if ($commercials->isEmpty()) {
                    return response()->json(['error' => 'No commercials found for this project'], 400);
                }

                // Calculate actual current prospect counts for each commercial
                $commercialCounts = $commercials->map(function($commercial) use ($projetId) {
                    // Get actual count from database, not from nb_prospects field
                    $actualCount = Prospect::on('temp')
                        ->where('commercial_affecte', $commercial->id)
                        ->where('projet_id', $projetId)
                        ->count();

                    return [
                        'id' => $commercial->id,
                        'count' => $actualCount
                    ];
                })->sortBy('count')->values();

                // Get current assignments of prospects being reassigned
                $currentAssignments = Prospect::on('temp')
                    ->whereIn('id', $prospectIds)
                    ->whereNotNull('commercial_affecte')
                    ->pluck('commercial_affecte', 'id')
                    ->toArray();

                // Adjust counts by subtracting prospects that will be reassigned
                $adjustedCounts = $commercialCounts->map(function($commercial) use ($currentAssignments) {
                    $prospectsToRemove = array_filter($currentAssignments, function($commercialId) use ($commercial) {
                        return $commercialId == $commercial['id'];
                    });

                    return [
                        'id' => $commercial['id'],
                        'count' => $commercial['count'] - count($prospectsToRemove)
                    ];
                })->toArray();

                // Distribute prospects efficiently using adjusted counts
                $assignments = [];
                $counts = $adjustedCounts;

                foreach ($prospectIds as $prospectId) {
                    // Always assign to commercial with lowest adjusted count
                    usort($counts, function($a, $b) {
                        return $a['count'] - $b['count'];
                    });

                    $targetCommercial = $counts[0];
                    $assignments[] = [
                        'prospect_id' => $prospectId,
                        'commercial_id' => $targetCommercial['id']
                    ];

                    // Update count for next iteration
                    $counts[0]['count']++;
                }

                // Get current user for tracking
                $user = Auth::user();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();

                // Apply assignments
                foreach ($assignments as $assignment) {
                    $prospect = Prospect::on('temp')->find($assignment['prospect_id']);
                    $oldCommercialId = $prospect->commercial_affecte;
                    $newCommercialId = $assignment['commercial_id'];

                    // Update prospect assignment and track affectation
                    $prospect->commercial_affecte = $newCommercialId;
                    if ($userAuth) {
                        $prospect->affecte_par_admin_id = $userAuth->id;
                        $prospect->date_affectation = Carbon::now();

                        // Create "Affecte" status for each assigned prospect
                        $statutProspect = new StatutProspect();
                        $statutProspect->setConnection('temp');
                        $statutProspect->prospect_id = $assignment['prospect_id'];
                        $statutProspect->statut = strval(StatutProspectEnum::Affecte->value);
                        $statutProspect->date_traitement = Carbon::now();
                        $statutProspect->user_id_traite = $userAuth->id;
                        $statutProspect->commentaire = 'Prospect affecté automatiquement au commercial';
                        $statutProspect->save();
                        $this->sendAffectationNotification($newCommercialId,$assignment['prospect_id'], $request->projet_id);

                    }
                    $prospect->save();

                    // Update counters only if commercial actually changed
                    if ($oldCommercialId != $newCommercialId) {
                        if ($oldCommercialId) {
                            \App\Models\User::on('temp')
                                ->where('id', $oldCommercialId)
                                ->decrement('nb_prospects');
                        }

                        if ($newCommercialId) {
                            \App\Models\User::on('temp')
                                ->where('id', $newCommercialId)
                                ->increment('nb_prospects');
                        }
                    }
                }

                // Final synchronization: Update nb_prospects to match actual counts
                foreach ($commercials as $commercial) {
                    $actualCount = Prospect::on('temp')
                        ->where('commercial_affecte', $commercial->id)
                        ->where('projet_id', $projetId)
                        ->count();

                    \App\Models\User::on('temp')
                        ->where('id', $commercial->id)
                        ->update(['nb_prospects' => $actualCount]);
                }

                \DB::connection('temp')->commit();

                return response()->json([
                    'message' => 'Prospects assigned automatically',
                    'assignments' => $assignments
                ], 200);

            } catch (\Exception $e) {
                \DB::connection('temp')->rollback();
                return response()->json(['error' => 'Assignment failed: ' . $e->getMessage()], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }*/
// AFFECTER PAR moitie  si 20 perssone avec 2 commerciaux 10 pour chacun
public function autoAssignProspects(Request $request)
{
    if (RoleHelper::ACSup() || RoleHelper::AgentAdmin() || RoleHelper::RespoCommercial()) {
        DatabaseHelper::Config();

        // Custom validation
        if (!$request->has('prospect_ids') || !is_array($request->prospect_ids)) {
            return response()->json(['error' => 'prospect_ids is required and must be an array'], 422);
        }

        if (!$request->has('projet_id')) {
            return response()->json(['error' => 'projet_id is required'], 422);
        }

        // Validate prospects
        $prospects = Prospect::on('temp')
            ->with('last_statut')
            ->whereIn('id', $request->prospect_ids)
            ->where('projet_id', $request->projet_id)
            ->get();

        if ($prospects->count() !== count($request->prospect_ids)) {
            return response()->json(['error' => 'Some prospect IDs are invalid'], 422);
        }

        try {
            \DB::connection('temp')->beginTransaction();

            $prospectIds = $prospects->pluck('id')->toArray();
            $projetId = $request->projet_id;
            $totalProspects = count($prospectIds);

            \Log::info('========== AUTO ASSIGN PROSPECTS START ==========');
            \Log::info('Projet ID: ' . $projetId);
            \Log::info('Total Prospects: ' . $totalProspects);

            // ===== RÉCUPÉRER TOUS LES COMMERCIAUX =====
            $commercials = \App\Models\User::on('temp')
                ->whereHas('projets', function($query) use ($projetId) {
                    $query->where('projet_id', $projetId);
                })
                ->where('role', 3)
                ->where('is_actif', 1)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get();

            \Log::info('Commercials found: ' . $commercials->count());

            if ($commercials->isEmpty()) {
                \Log::error('No commercials found!');
                return response()->json(['error' => 'No commercials found'], 400);
            }

            $totalCommercials = count($commercials);
            \Log::info('Total Commercials: ' . $totalCommercials);

            // ============================================================
            // ✅ LOGIQUE: last_affected avec Reset Automatique
            // ============================================================

            $assignments = [];
            $affectedCommercials = [];

            // Distribute prospects one by one
            for ($i = 0; $i < $totalProspects; $i++) {

                // ✅ CHECK: Are all commercials have last_affected = 1?
                $allHaveLastAffected = true;
                foreach ($commercials as $commercial) {
                    if ($commercial->last_affected == 0) {
                        $allHaveLastAffected = false;
                        break;
                    }
                }

                // ✅ If all have last_affected = 1, reset ALL to 0
                if ($allHaveLastAffected) {
                    \Log::info('🔄 All commercials have last_affected = 1, resetting all to 0');
                    \App\Models\User::on('temp')
                        ->where('role', 3)
                        ->where('is_actif', 1)
                        ->whereNull('deleted_at')
                        ->update(['last_affected' => 0]);

                    // Refresh the collection
                    $commercials = \App\Models\User::on('temp')
                        ->whereHas('projets', function($query) use ($projetId) {
                            $query->where('projet_id', $projetId);
                        })
                        ->where('role', 3)
                        ->where('is_actif', 1)
                        ->whereNull('deleted_at')
                        ->orderBy('id')
                        ->get();

                    \Log::info('✅ All commercials reset to last_affected = 0');
                }

                // ✅ Step 1: Find commercial with last_affected = 0
                $targetCommercial = null;

                foreach ($commercials as $commercial) {
                    if ($commercial->last_affected == 0) {
                        $targetCommercial = $commercial;
                        \Log::info('✅ Found commercial with last_affected = 0', [
                            'commercial_id' => $commercial->id,
                            'name' => $commercial->name . ' ' . $commercial->prenom
                        ]);
                        break;
                    }
                }

                // ✅ Step 2: If all have last_affected = 1 (should not happen after reset), take the first one
                if (!$targetCommercial) {
                    $targetCommercial = $commercials->first();
                    \Log::info('🔄 All commercials have last_affected = 1, taking first', [
                        'commercial_id' => $targetCommercial->id,
                        'name' => $targetCommercial->name . ' ' . $targetCommercial->prenom
                    ]);
                }

                // Track affected commercials
                if (!in_array($targetCommercial->id, $affectedCommercials)) {
                    $affectedCommercials[] = $targetCommercial->id;
                }

                // Create assignment
                $assignments[] = [
                    'prospect_id' => $prospectIds[$i],
                    'commercial_id' => $targetCommercial->id,
                    'commercial_name' => $targetCommercial->name . ' ' . $targetCommercial->prenom,
                    'last_affected_before' => $targetCommercial->last_affected
                ];

                \Log::info('Assigned prospect ' . $prospectIds[$i] . ' to commercial ' .
                          $targetCommercial->id . ' (' . $targetCommercial->name . ' ' . $targetCommercial->prenom . ')');

                // ✅ Update last_affected for this commercial immediately (for next iteration)
                $targetCommercial->last_affected = 1;
            }

            \Log::info('Assignments created: ' . count($assignments));

            // ===== APPLIQUER LES ASSIGNATIONS =====
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();

            foreach ($assignments as $assignment) {
                $prospect = Prospect::on('temp')->find($assignment['prospect_id']);
                $oldCommercialId = $prospect->commercial_affecte;
                $newCommercialId = $assignment['commercial_id'];

                \Log::info('Applying assignment:');
                \Log::info('  - Prospect ID: ' . $assignment['prospect_id']);
                \Log::info('  - Old commercial: ' . ($oldCommercialId ?? 'null'));
                \Log::info('  - New commercial: ' . $newCommercialId);

                // Update prospect
                $prospect->commercial_affecte = $newCommercialId;
                if ($userAuth) {
                    $prospect->affecte_par_admin_id = $userAuth->id;
                    $prospect->date_affectation = Carbon::now();

                    // Create status
                    $statutProspect = new StatutProspect();
                    $statutProspect->setConnection('temp');
                    $statutProspect->prospect_id = $assignment['prospect_id'];
                    $statutProspect->statut = strval(StatutProspectEnum::Affecte->value);
                    $statutProspect->date_traitement = Carbon::now();
                    $statutProspect->user_id_traite = $userAuth->id;
                    $statutProspect->commentaire = 'Prospect affecté automatiquement au commercial';
                    $statutProspect->save();

                    $this->sendAffectationNotification($newCommercialId, $assignment['prospect_id'], $request->projet_id);
                }
                $prospect->save();

                // Update counters
                if ($oldCommercialId != $newCommercialId) {
                    if ($oldCommercialId) {
                        \App\Models\User::on('temp')
                            ->where('id', $oldCommercialId)
                            ->whereNull('deleted_at')
                            ->decrement('nb_prospects');
                        \Log::info('  - Decremented nb_prospects for commercial ' . $oldCommercialId);
                    }

                    if ($newCommercialId) {
                        \App\Models\User::on('temp')
                            ->where('id', $newCommercialId)
                            ->whereNull('deleted_at')
                            ->increment('nb_prospects');
                        \Log::info('  - Incremented nb_prospects for commercial ' . $newCommercialId);
                    }
                }
            }

            // ===== UPDATE last_affected FOR COMMERCIALS =====
            // Set last_affected = 1 for all affected commercials
            if (!empty($affectedCommercials)) {
                \App\Models\User::on('temp')
                    ->whereIn('id', $affectedCommercials)
                    ->whereNull('deleted_at')
                    ->update(['last_affected' => 1]);

                \Log::info('Set last_affected = 1 for commercials: ' . json_encode($affectedCommercials));
            }

            // Reset last_affected = 0 for all other active commercials
            \App\Models\User::on('temp')
                ->whereNotIn('id', $affectedCommercials)
                ->where('role', 3)
                ->whereNull('deleted_at')
                ->where('is_actif', 1)
                ->update(['last_affected' => 0]);

            \Log::info('Reset last_affected = 0 for other commercials');

            // ===== SYNCHRONISATION FINALE =====
            \Log::info('Final synchronization:');
            foreach ($commercials as $commercial) {
                $actualCount = Prospect::on('temp')
                    ->where('commercial_affecte', $commercial->id)
                    ->where('projet_id', $projetId)
                    ->whereNull('deleted_at')
                    ->count();

                \Log::info('  - Commercial ' . $commercial->id . ' actual count: ' . $actualCount);

                \App\Models\User::on('temp')
                    ->whereNull('deleted_at')
                    ->where('id', $commercial->id)
                    ->update(['nb_prospects' => $actualCount]);
            }

            \DB::connection('temp')->commit();

            // ===== RÉSUMÉ DE LA DISTRIBUTION =====
            $distributionSummary = [];
            foreach ($assignments as $assignment) {
                $commercialId = $assignment['commercial_id'];
                if (!isset($distributionSummary[$commercialId])) {
                    $distributionSummary[$commercialId] = [
                        'count' => 0,
                        'name' => $assignment['commercial_name']
                    ];
                }
                $distributionSummary[$commercialId]['count']++;
            }

            \Log::info('Final distribution summary: ' . json_encode($distributionSummary));
            \Log::info('========== AUTO ASSIGN PROSPECTS END ==========');

            return response()->json([
                'message' => 'Prospects assigned automatically',
                'assignments' => $assignments,
                'distribution' => $distributionSummary,
                'total_prospects' => $totalProspects,
                'total_commercials' => $totalCommercials,
                'affected_commercials' => $affectedCommercials
            ], 200);

        } catch (\Exception $e) {
            \DB::connection('temp')->rollback();
            \Log::error('Assignment error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Assignment failed: ' . $e->getMessage()], 500);
        }
    } else {
        \Log::warning('Unauthorized access to autoAssignProspects');
        return response()->json(['error' => 'Unauthorized'], 401);
    }
}
    // Controller method

 public function bulkWhatsApp(Request $request)
{
    try {
        // Validate request
        $request->validate([
            'prospect_ids' => 'required|array',
            'prospect_ids.*' => 'exists:prospects,id',
            'message' => 'required|string',
            'scheduled_date' => 'nullable|date',
            'projet_id' => 'required|exists:projets,id',
            'media_url' => 'nullable|string', // ✅ Allow string to handle encoded URLs
        ]);

        // Get prospects with phone numbers
        $prospects = Prospect::whereIn('id', $request->prospect_ids)
            ->whereNotNull('telephone')
            ->get();

        if ($prospects->isEmpty()) {
            return response()->json([
                'error' => 'Aucun prospect valide avec numéro de téléphone'
            ], 400);
        }

        $scheduledDate = $request->scheduled_date ? Carbon::parse($request->scheduled_date) : null;

        // Get the projet for file upload
        $projet = Projet::find($request->projet_id);

        // ✅ Sanitize media_url if provided
        $mediaUrl = null;
        if ($request->filled('media_url')) {
            // Clean the URL - replace spaces and encode special characters
            $mediaUrl = $this->sanitizeUrl($request->media_url);
        }

        // Create the record in historique_relances_whatsapp table
        $record = HistoriqueRelanceWhatsapp::create([
            'projet_id' => $request->projet_id,
            'user_id' => auth()->id(),
            'prospect_ids' => $request->prospect_ids,
            'message' => $request->message,
            'media_url' => $mediaUrl,
            'scheduled_date' => $scheduledDate,
            'status' => 'pending',
            'metadata' => [
                'total_prospects' => $prospects->count(),
                'phones' => $prospects->pluck('telephone')->toArray(),
                'prospect_names' => $prospects->map(function($p) {
                    return trim(($p->prenom ?? '') . ' ' . ($p->nom ?? ''));
                })->toArray(),
                'media_url' => $mediaUrl,
            ],
        ]);

        return response()->json([
            'message' => "Messages WhatsApp programmés avec succès",
            'total' => $prospects->count(),
            'scheduled_date' => $scheduledDate,
            'media_url' => $mediaUrl,
            'record_id' => $record->id,
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => 'Erreur de validation',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        \Log::error('Bulk WhatsApp error: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'request' => $request->all()
        ]);

        return response()->json([
            'message' => 'Une erreur est survenue lors de la programmation des messages',
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * Sanitize URL - replace spaces and encode special characters
 */
private function sanitizeUrl($url)
{
    if (empty($url)) {
        return null;
    }

    // Remove any leading/trailing whitespace
    $url = trim($url);

    // Replace spaces with %20
    $url = str_replace(' ', '%20', $url);

    // Encode special characters in the path
    $parts = parse_url($url);

    if ($parts !== false) {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = isset($parts['path']) ? implode('/', array_map('rawurlencode', explode('/', $parts['path']))) : '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        $sanitizedUrl = $scheme . $host . $port . $path . $query . $fragment;

        return $sanitizedUrl;
    }

    // Fallback: just replace spaces and encode
    return str_replace(' ', '%20', $url);
}
/**********************************reaffectation par commercial ****************************************/
    /******************by commercial concerne *********************/
    public function commercialReaffecterSelective(Request $request)
    {
        // Vérifier que c'est bien un commercial
        if (!RoleHelper::Com()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'prospect_ids' => 'required|array',
            'commercial_id' => 'required', // ID origine (user_id_origin) du commercial cible
        ]);

        DatabaseHelper::Config();
        $user = Auth::user();
        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();

        // Trouver le commercial cible dans la base temp
        $targetCommercial = User::on('temp')->where('user_id_origin', $request->commercial_id)->first();

        if (!$targetCommercial) {
            return response()->json(['error' => 'Commercial cible non trouvé'], 404);
        }

        DB::connection('temp')->beginTransaction();

        try {
            foreach ($request->prospect_ids as $prospectId) {
                $prospect = Prospect::on('temp')->find($prospectId);
                if (!$prospect) continue;

                $oldCommercialId = $prospect->commercial_affecte;
                $newCommercialId = $targetCommercial->id;

                if ($oldCommercialId != $newCommercialId) {

                    // 🔍 Récupérer l'ancien commercial pour la traçabilité
                    $oldCommercial = $oldCommercialId ? User::on('temp')->find($oldCommercialId) : null;
                    $oldName = $oldCommercial ? $oldCommercial->name . ' ' . $oldCommercial->prenom : 'Personne (Non affecté)';
                    $newName = $targetCommercial->name . ' ' . $targetCommercial->prenom;
                    $userName = $userAuth ? $userAuth->name . ' ' . $userAuth->prenom : 'Inconnu';

                    // Décrémenter l'ancien
                    if ($oldCommercialId) {
                        User::on('temp')->where('id', $oldCommercialId)->decrement('nb_prospects');
                    }

                    // Incrémenter le nouveau
                    User::on('temp')->where('id', $newCommercialId)->increment('nb_prospects');

                    // Mettre à jour le prospect
                    $prospect->commercial_affecte = $newCommercialId;
                    $prospect->affecte_par_admin_id = $userAuth->id;
                    $prospect->date_affectation = Carbon::now();
                    $prospect->save();

                    // Créer un statut de traçabilité
                    $statut = new StatutProspect();
                    $statut->setConnection('temp');
                    $statut->prospect_id = $prospectId;
                    $statut->statut = (string) StatutProspectEnum::Reaffecte->value;
                    $statut->date_traitement = Carbon::now();
                    $statut->user_id_traite = $userAuth->id;

                    // 📝 Commentaire avec l'ancien, le nouveau et celui qui a fait l'action
                    $statut->commentaire = sprintf(
                        'Prospect réaffecté de %s vers %s par le commercial %s',
                        $oldName,
                        $newName,
                        $userName
                    );

                    $statut->save();

                    // ✅ ENVOI DE LA NOTIFICATION
                    $this->sendAffectationNotification($newCommercialId, $prospectId, $prospect->projet_id);
                }
            }

            DB::connection('temp')->commit();
            return response()->json(['message' => 'Prospects réaffectés avec succès'], 200);

        } catch (\Exception $e) {
            DB::connection('temp')->rollBack();
            return response()->json(['error' => 'Erreur lors de la réaffectation'], 500);
        }
    }
    /******************reaaffectation automatique *********************/
     public function commercialReaffecterAuto(Request $request)
    {
        if (!RoleHelper::Com()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'prospect_ids' => 'required|array',
            'projet_id' => 'required',
        ]);

        DatabaseHelper::Config();

        try {
            DB::connection('temp')->beginTransaction();

            $prospectIds = $request->prospect_ids;
            $projetId = $request->projet_id;
            $totalProspects = count($prospectIds);

            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();
            $userName = $userAuth ? $userAuth->name . ' ' . $userAuth->prenom : 'Inconnu';

            // Récupérer les commerciaux du projet (en excluant le commercial connecté)
            $commercials = User::on('temp')
                ->whereHas('projets', function($query) use ($projetId) {
                    $query->where('projet_id', $projetId);
                })
                ->where('role', 3)
                ->where('is_actif', 1)
                ->whereNull('deleted_at')
                ->where('id', '!=', $userAuth->id) // 👈 EXCLURE LE COMMERCIAL CONNECTÉ
                ->orderBy('id')
                ->get();

            if ($commercials->isEmpty()) {
                return response()->json(['error' => 'Aucun autre commercial trouvé pour la réaffectation'], 400);
            }

            // Logique de distribution (Last Affected)
            $assignments = [];
            for ($i = 0; $i < $totalProspects; $i++) {
                $allHaveLastAffected = $commercials->every(fn($c) => $c->last_affected == 1);
                if ($allHaveLastAffected) {
                    User::on('temp')->where('role', 3)->where('is_actif', 1)->update(['last_affected' => 0]);
                    $commercials->each(fn($c) => $c->last_affected = 0);
                }

                $target = $commercials->firstWhere('last_affected', 0) ?? $commercials->first();
                $assignments[] = ['prospect_id' => $prospectIds[$i], 'commercial_id' => $target->id];
                $target->last_affected = 1;
            }

            // Appliquer les affectations
            foreach ($assignments as $assignment) {
                $prospect = Prospect::on('temp')->find($assignment['prospect_id']);
                $oldCommercialId = $prospect->commercial_affecte;
                $newCommercialId = $assignment['commercial_id'];

                if ($oldCommercialId != $newCommercialId) {

                    // 🔍 Récupérer l'ancien commercial pour la traçabilité
                    $oldCommercial = $oldCommercialId ? User::on('temp')->find($oldCommercialId) : null;
                    $oldName = $oldCommercial ? $oldCommercial->name . ' ' . $oldCommercial->prenom : 'Personne (Non affecté)';

                    // Récupérer le nouveau commercial
                    $newCommercial = User::on('temp')->find($newCommercialId);
                    $newName = $newCommercial ? $newCommercial->name . ' ' . $newCommercial->prenom : 'Inconnu';

                    if ($oldCommercialId) User::on('temp')->where('id', $oldCommercialId)->decrement('nb_prospects');
                    User::on('temp')->where('id', $newCommercialId)->increment('nb_prospects');

                    $prospect->commercial_affecte = $newCommercialId;
                    $prospect->affecte_par_admin_id = $userAuth->id;
                    $prospect->date_affectation = Carbon::now();
                    $prospect->save();

                    $statut = new StatutProspect();
                    $statut->setConnection('temp');
                    $statut->prospect_id = $assignment['prospect_id'];
                    $statut->statut = (string) StatutProspectEnum::Reaffecte->value;
                    $statut->date_traitement = Carbon::now();
                    $statut->user_id_traite = $userAuth->id;

                    // 📝 Commentaire avec l'ancien, le nouveau et celui qui a fait l'action
                    $statut->commentaire = sprintf(
                        'Prospect réaffecté de %s vers %s par le commercial %s',
                        $oldName,
                        $newName,
                        $userName
                    );

                    $statut->save();

                    // ✅ ENVOI DE LA NOTIFICATION
                    $this->sendAffectationNotification($newCommercialId, $assignment['prospect_id'], $prospect->projet_id);
                }
            }

            DB::connection('temp')->commit();
            return response()->json(['message' => 'Réaffectation automatique réussie'], 200);

        } catch (\Exception $e) {
            DB::connection('temp')->rollBack();
            return response()->json(['error' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

}
