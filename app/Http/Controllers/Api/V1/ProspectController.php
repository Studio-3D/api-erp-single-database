<?php
namespace App\Http\Controllers\Api\V1;

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
use App\Models\Source;
use App\Models\StatutProspect;
use App\Models\TraitementFrein;
use App\Models\User;
use App\Models\Visite;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Enum\StatutProspectEnum;

class ProspectController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function indexByProjet(Request $request, $projet_id)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', null);
            $page = $request->input('page', null);

            DatabaseHelper::Config();

            // Démarrer la requête directement sur le modèle

            $query = prospect::on('temp')->with('client','visites','appels','last_statut')->where('projet_id', $projet_id);
             if ($request->filled('telephone')) {
            $query->where(function ($q) use ($request) {
                if ($request->filled('telephone')) {
                    $q->where(function ($subQuery) use ($request) {
                        $subQuery->where('telephone', 'like', '%' . $request->input('telephone') . '%')
                            ->orWhere('telephone_num2', 'like', '%' . $request->input('telephone') . '%');
                    });
                }

            });}
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
            if ($request->filled('statut')) {
                $query->where('prenom', 'like', '%' . $request->input('prenom') . '%');
            }
            if ($request->filled('statut')) {
                $query->whereHas('last_statut', function ($q) use ($request) {
                    $q->where('statut', $request->input('statut'));
                });
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

    public function index(Request $request)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', null);
            $page = $request->input('page', null);
            DatabaseHelper::Config();

            // Démarrer la requête directement sur le modèle
            $query = prospect::on('temp')->with('client', 'visites', 'appels');
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
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $user      = Auth::user();
            $userAuth  = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $prospect  = Prospect::on('temp')->findOrFail($id);
            
            // Update prospect with traitement tracking
            $prospect->traite_par_user_id = $userAuth->value('id');
            $prospect->date_traitement = Carbon::now();

            // Unassign prospect from commercial for certain final statuses
            $finalStatuses = [
                StatutProspectEnum::Perdu->value,              // Lost prospects should be unassigned
                StatutProspectEnum::Converti_en_visite->value, // Converted prospects move to next stage
            ];

            if (in_array($request->statut, $finalStatuses)) {
                $oldCommercialId = $prospect->commercial_affecte;
                $prospect->commercial_affecte = null;
                $prospect->affecte_par_admin_id = null;
                $prospect->date_affectation = null;

                // Update commercial's prospect counter
                if ($oldCommercialId) {
                    \App\Models\User::on('temp')
                        ->where('id', $oldCommercialId)
                        ->decrement('nb_prospects');
                }
            }

            $prospect->save();
            
            $ps_statut = new statutProspect();
            $ps_statut->setConnection('temp');
            $ps_statut->prospect_id     = $id;
            // Coerce to numeric string to enforce storage policy
            $ps_statut->statut          = is_numeric($request->statut)
                ? (string) $request->statut
                : (string) (\App\Enum\StatutProspectEnum::tryFrom($request->statut)?->value ?? '0');

            // Add unassignment note to comment for final statuses
            $commentaire = $request->commentaire;
            if (in_array($request->statut, $finalStatuses)) {
                $commentaire = $commentaire ? $commentaire . ' (Prospect désaffecté du commercial)' : 'Prospect désaffecté du commercial';
            }
            $ps_statut->commentaire     = $commentaire;

            $ps_statut->user_id_traite  = $userAuth->value('id');
            $ps_statut->date_traitement = Carbon::now();
            if ($request->statut == 1) {
                $ps_statut->rdv = $request->rdv;
            } elseif ($request->statut == 3) {
                $ps_statut->date_rappel = $request->date_rappel;
            }

            if ($ps_statut->save()) {
                if ($request->statut == 2) {
                    //rappel
                    Config::set('broadcasting.default', 'pusher_3');
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

                }
                if ($request->statut == 1) {
                    //rdv
                    Config::set('broadcasting.default', 'pusher_3');
                    $data_notif = [
                        'lien'        => '/crm/prospects/' . $id,
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
            $size = $request->input('size', config('app.default_item_number_perpage')); // Default size if not provided
            $page = $request->input('page', 1);                                         // Default page if not provided

            DatabaseHelper::Config();

            $query = StatutProspect::on('temp')->with('user')->where('prospect_id', $id)->orderBy('date_traitement', 'desc');
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
    public function store(StoreProspectRequest $request)
    {
        if (RoleHelper::ACSup()) {
            Log::info($request);
            DatabaseHelper::Config();
            $prospect = new Prospect();
            $prospect->setConnection("temp");
            $prospect->cin            = $request->cin;
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
            $prospect->save();

            // Create default "en_attente" status unless created from a visite
            if (($prospect->origin ?? ($request->origin ?? null)) !== 'visite') {
                $user = Auth::user();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();

                $statutProspect = new StatutProspect();
                $statutProspect->setConnection('temp');
                $statutProspect->prospect_id = $prospect->id;

            $statutProspect = new StatutProspect();
            $statutProspect->setConnection('temp');
            $statutProspect->prospect_id = $prospect->id;
            $statutProspect->statut = '0';
            $statutProspect->date_traitement = Carbon::now();
            $statutProspect->user_id_traite = $userAuth ? $userAuth->id : null;
            $statutProspect->commentaire = 'Prospect créé manuellement';
            $statutProspect->save();

               
            }

            return $prospect;

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public static function Store_WhatsApp($phone_number_id, $from, $msg_body, $name, $societe_id, $projet_id = null)
    {
        DatabaseHelper::Config($societe_id);
        $prospect = new Prospect();
        $prospect->setConnection("temp");
        $prospect->cin       = null;
        $prospect->message   = $msg_body;
        $prospect->nom       = $name;
        $prospect->telephone = $from;
        $prospect->email     = null;
        $prospect->origin    = 'whatssap';
        $prospect->source    = 1;
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
    }

    public static function Store_LandingPage($name, $prenom, $phone, $email, $societe_id, $comment = null)
    {
        DatabaseHelper::Config($societe_id);
        $prospect = new Prospect();
        $prospect->setConnection("temp");
        $prospect->cin       = null;
        $prospect->message   = $comment; // store optional comment
        $prospect->nom       = $name;
        $prospect->prenom    = $prenom;
        $prospect->telephone = $phone;
        $prospect->email     = $email;
        $prospect->origin    = 'landingPage';
        $prospect->source    = 3;
        $prospect->save();

        // Create default "en_attente" status (numeric)
        $statutProspect = new StatutProspect();
        $statutProspect->setConnection('temp');
        $statutProspect->prospect_id = $prospect->id;
        $statutProspect->statut = '0';
        $statutProspect->date_traitement = Carbon::now();
        $statutProspect->user_id_traite = null; // No specific user for landing page
        $statutProspect->commentaire = 'Prospect créé via Landing Page';
        $statutProspect->save();

        // Create a notification for the new landing page prospect
        try {
            $data_notif = [
                'lien'        => '/crm/prospects/' . $prospect->id,
                'date'        => Carbon::now(),
                'type'        => 50, // custom type for Landing Page prospect
                'description' => 'Nouveau prospect via Landing Page',
                'user_id'     => null,
                'role'        => null,
                'visite_id'   => null,
                'prospect_id' => $prospect->id,
                'projet_id'   => null,
            ];
            $notif_helper = new NotificationHelper();
            $req = new \Illuminate\Http\Request();
            $notif_helper->storeNotification($req->merge($data_notif));
        } catch (\Exception $e) {
            Log::warning('Failed to create landing page notification: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $prospect = Prospect::on('temp')->with('visites_perdu','appels')->findOrfail($id);
            return response()->json(['prospect' => $prospect], 200);
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
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            if ($request->cin != null) {
                $cin_exist = Prospect::on('temp')->where('cin', $request->cin)->where('id', '!=', $id)->count();
                if ($cin_exist > 0) {
                    return response()->json(['errors' => 'Le Cin que vous avez saisi' . $request->cin . ' apprtient à un autre utilisateur'], 422);
                }
            }
            $prospect = Prospect::on('temp')->findOrFail($id);
            $update   = $request->all();
            
            // Handle commercial_affecte update
            if ($request->has('commercial_affecte')) {
                // Allow reassignment regardless of final status
                $lastStatus = $prospect->last_statut;

                $oldCommercialId = $prospect->commercial_affecte;
                $newCommercialId = $request->commercial_affecte;
                
                // If commercial is changing, update counters and track affectation
                if ($oldCommercialId != $newCommercialId) {
                    // Decrement old commercial's counter
                    if ($oldCommercialId) {
                        \App\Models\User::on('temp')
                            ->where('id', $oldCommercialId)
                            ->decrement('nb_prospects');
                    }
                    
                    // Increment new commercial's counter
                    if ($newCommercialId) {
                        \App\Models\User::on('temp')
                            ->where('id', $newCommercialId)
                            ->increment('nb_prospects');
                    }
                    
                    // Track who made the affectation
                    $user = Auth::user();
                    $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();
                    if ($userAuth) {
                        $prospect->affecte_par_admin_id = $userAuth->id;
                        $prospect->date_affectation = Carbon::now();
                        
                        // Create "Affecte" status when assigning to a commercial
                        if ($newCommercialId) {
                            $statutProspect = new StatutProspect();
                            $statutProspect->setConnection('temp');
                            $statutProspect->prospect_id = $id;
                            $statutProspect->statut = (string) StatutProspectEnum::Affecte->value;
                            $statutProspect->date_traitement = Carbon::now();
                            $statutProspect->user_id_traite = $userAuth->id;
                            $statutProspect->commentaire = 'Prospect affecté au commercial';
                            $statutProspect->save();
                        }
                    }
                }
                
                $prospect->commercial_affecte = $newCommercialId;
            }
            
            foreach ($update as $key => $value) {
                if (!in_array($key, ['commercial_affecte', 'affecte_par_admin_id', 'date_affectation'])) {
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
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $prospect = Prospect::on('temp')->findOrFail($id);
            if (count($prospect->visites) > 0 || $prospect->appels != null || $prospect->client != null) {
                return response()->json(['error' => 'Il est impossible de supprimer ce Prospect car il possède plusieurs dossiers liés à des visites, des appels ou client.'], 422);
            } else {
                $notifications = Notification::on('temp')->where('prospect_id', $id)->get();
                if (count($notifications)) {
                    foreach ($notifications as $nt) {
                        $nt->forceDelete();
                    }
                }
                if ($prospect->delete()) {
                    return response()->json(['message' => 'Prospect supprimé avec succès.'], 200);
                } else {
                    return response()->json(['error' => "Le prospect n'a pas été supprimé."], 404);
                }
            }

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function search_prospect_by_param($param_1, $value)
    {
        //cin ou email
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            if ($param_1 == 'cin' || $param_1 == 'email') {
                $prospect = Prospect::on('temp')->with('visite_pre_reserves', 'visites', 'visites.freins', 'visites.freins.freinTranche', 'visites.freins.FreinEtage', 'visites.freins.FreinOrientation', 'visites.freins.FreinTypologie', 'visites.freins.FreinVue', 'appels')->where($param_1, $value)
                    ->get()->first();
                $client = Client::on('temp')->with('prospect')->where($param_1, $value)->get()->first();

            } else {
                //telephone
                $prospect = Prospect::on('temp')->with('visite_pre_reserves', 'visites', 'visites.freins', 'visites.freins.freinTranche', 'visites.freins.FreinEtage', 'visites.freins.FreinOrientation', 'visites.freins.FreinTypologie', 'visites.freins.FreinVue', 'appels')
                    ->where(function ($query) use ($value) {
                        $query->where('telephone', $value)
                            ->orwhere('telephone_num2', $value)
                        ;
                    })
                    ->get()->first();
                $client = Client::on('temp')->with('prospect')
                    ->where(function ($query) use ($value) {
                        $query->where('telephone_num1', $value)
                            ->orwhere('telephone_num2', $value)
                        ;
                    })
                    ->get()->first();
            }

            //bien pre reserve par appel on cas des biens disponibles
            $biens_traitement_freins = [];
            if ($prospect != null) {
                $biens_traitement_freins = TraitementFrein::on('temp')->with('bien', 'visite')
                    ->whereHas('visite', function ($q) use ($prospect) {
                        $q->where('prospect_id', $prospect->id);
                    })
                    ->where('interet', InteretEnum::Intéressé->value)
                    ->where('statut', StatutVisiteEnum::Pré_Réservation->value)->orderby('created_at', 'desc')->get(['bien_id', 'id'])->take(1);
            }

            return response()->json(['prospect' => $prospect, 'client' => $client, 'biens_traitement_freins' => $biens_traitement_freins]);
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
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            set_time_limit(0);
            ini_set('memory_limit', '-1');

            $data = $request->input('jsonData');
            if (count($data) > 0) {
                $user = Auth::user();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();

                foreach ($data as $row) {
                    $prospect_cin   = 0;
                    $prospect_email = 0;
                    $prospect_tel   = 0;
                    $prospect_tel2  = 0;

                    //cin unique
                    if (! empty($row['cin'])) {
                        $prospect_cin = Prospect::on('temp')->where('cin', $row['cin'])->count();
                    }

                    //tel1 unique
                    if (! empty($row['telephone'])) {
                        $prospect_tel = Prospect::on('temp')
                            ->where(function ($subQuery) use ($row) {
                                $subQuery->where('telephone', $row['telephone'])
                                    ->orWhere('telephone_num2', $row['telephone']);
                            })->count();
                    }

                    //tel2 unique
                    if (! empty($row['telephone_num2'])) {
                        $prospect_tel2 = Prospect::on('temp')
                            ->where(function ($subQuery) use ($row) {
                                $subQuery->where('telephone', $row['telephone_num2'])
                                    ->orWhere('telephone_num2', $row['telephone_num2']);
                            })->count();
                    }
                    if (! empty($row['email'])) {
                        $prospect_email = Prospect::on('temp')->where('email', $row['email'])->count();
                    }

                    if ($prospect_cin == 0 && $prospect_email == 0 && $prospect_tel == 0 && $prospect_tel2 == 0) {
                        $source_id = null;
                        if (! empty($row['source'])) {
                            $source = Source::on('temp')->where('source', $row['source'])->first();
                            if ($source != null) {
                                $source_id = $source->id;
                            }
                        }

                        $prospect = new Prospect();
                        $prospect->setConnection("temp");
                        $prospect->cin            = $row['cin'];
                        $prospect->nom            = $row['nom'];
                        $prospect->prenom         = $row['prenom'];
                        $prospect->telephone      = $row['telephone'];
                        $prospect->telephone_num2 = empty($row['telephone_num2']) ? null : $row['telephone_num2'];
                        $prospect->email          = empty($row['email']) ? null : $row['email'];
                        $prospect->origin         = 'import';
                        $prospect->notifie        = 0;
                        $prospect->source         = $source_id;
                        $prospect->partenaire_id  = null;
                        $prospect->message        = null;
                        $prospect->projet_id      = $request->projet_id;
                        $prospect->ville          = empty($row['ville']) ? null : $row['ville'];
                        $prospect->save();

                        // Create default "en_attente" status
                        $statutProspect = new StatutProspect();
                        $statutProspect->setConnection('temp');
                        $statutProspect->prospect_id = $prospect->id;
                        // Enforce numeric-only status
                        $statutProspect->statut = '0';
                        $statutProspect->date_traitement = Carbon::now()->toDateString();
                        $statutProspect->user_id_traite = null;
                        $statutProspect->commentaire = 'Prospect créé par importation';
                        $statutProspect->save();
                    }
                }
                return response()->json('done');
            } else {
                return response()->json(['error' => 'Le fichier doit être rempli.'], 400);
            }

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }
    public function autoAssignProspects(Request $request)
    {
        if (RoleHelper::ACSup()) {
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
                        $statutProspect->statut = (string) StatutProspectEnum::Affecte->value;
                        $statutProspect->date_traitement = Carbon::now();
                        $statutProspect->user_id_traite = $userAuth->id;
                        $statutProspect->commentaire = 'Prospect affecté automatiquement au commercial';
                        $statutProspect->save();
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
    }
}
