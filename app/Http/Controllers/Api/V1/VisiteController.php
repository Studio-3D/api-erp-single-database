<?php
namespace App\Http\Controllers\Api\V1;

use App\Enum\EtatBien;
use App\Enum\InteretEnum;
use App\Enum\StatutVisiteEnum;
use App\Events\NotificationEvent;
use App\Events\NotifMenuEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\NotificationController;
use App\Http\Helpers\Bien_Helper;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\HistoriqueBienHelper;
use App\Http\Helpers\NotificationHelper;
use App\Http\Helpers\PaginationHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreFreinRequest;
use App\Http\Requests\StoreProspectRequest;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\StoreVisiteRequest;
use App\Http\Requests\Store_n_VisiteRequest;
use App\Http\Requests\UpdateDate_relance_Rdv;
use App\Http\Requests\UpdateFreinRequest;
use App\Http\Requests\UpdateVisiteRequest;
use App\Models\Bien;
use App\Models\Bloc;
use App\Models\Client;
use App\Models\Frein;
use App\Models\HistoriqueBien;
use App\Models\Immeuble;
use App\Models\Notification;
use App\Models\PreReservation;
use App\Models\Projet;
use App\Models\Prospect;
use App\Models\Relance_Rdv_Visite;
use App\Models\StatutProspect;
use App\Models\TraitementAppel;
use App\Models\TraitementFrein;
use App\Models\Tranche;
use App\Models\Typologie;
use App\Models\User;
use App\Models\Visite;
use App\Models\Vue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use \NumberFormatter;
use Illuminate\Support\Facades\DB;


class VisiteController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index(Request $request, $projet_id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page    = $request->input('page', 1);

            $visites = Visite::on('temp')->latest('created_at')->where('projet_id', $projet_id)->where('etat', 1)
                ->get()
                ->groupby('origin_id');
            $visites = $visites->map(function ($visite) {
                return [
                    'id'                  => $visite->first()->id,
                    'origin_id'           => $visite->first()->origin_id,
                    'nom_cc'              => $visite->first()->user->name,
                    'prenom_cc'           => $visite->first()->user->prenom,
                    'date'                => $visite->first()->created_at,
                    'cin'                 => $visite->first()->prospect->cin,
                    'nom'                 => $visite->first()->prospect->nom,
                    'prenom'              => $visite->first()->prospect->prenom,
                    'telephone'           => $visite->first()->prospect->telephone,
                    'telephone2'          => $visite->first()->prospect->telephone_num2,
                    'prospect_id'         => $visite->first()->prospect->id,
                    'interet'             => $visite->first()->interet,
                    'statut'              => $visite->first()->statut,
                    'propriete_dite_bien' => $visite->first()->bien_id ? $visite->first()->bien->propriete_dite_bien : '',
                    'etat_bien'           => $visite->first()->bien_id ? $visite->first()->bien->etat : '',
                    'bien_id'             => $visite->first()->bien_id ? $visite->first()->bien_id : '',
                    'visit_count'         => count($visite),
                    'reservation'         => $visite->first()->reservation,

                ];
            });

            $data = PaginationHelper::paginate_array($visites->toArray(), $perPage, $page, $request->url());
            return response()->json(['visites' => $data]);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function indexByProjet(Request $request, $projet_id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $size = $request->input('size', config('app.default_item_number_perpage'));
            $page = $request->input('page', 1);

            $query = Visite::on('temp')
                ->latest('created_at')
                ->where('projet_id', $projet_id)
                ->where('etat', 1);

            if ($request->filled('prospect_id')) {
                $query->where('prospect_id', $request->input('prospect_id'));
            }
            if ($request->filled('client_id')) {
                $client = Client::on('temp')->findOrFail($request->input('client_id'));
                $query->where('prospect_id', $client->prospect_id);
            }

            if ($request->filled('cin')) {
                $query->whereHas('prospect', function ($q) use ($request) {
                    $q->where('cin', 'like', '%' . $request->input('cin') . '%');
                });
            }
            if ($request->filled('nom')) {
                $query->whereHas('prospect', function ($q) use ($request) {
                    $q->where('nom', 'like', '%' . $request->input('nom') . '%');
                });
            }
            if ($request->filled('telephone')) {
                $query->whereHas('prospect', function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('telephone', 'like', '%' . $request->input('telephone') . '%')
                            ->orWhere('telephone_num2', 'like', '%' . $request->input('telephone') . '%');
                    });
                });
            }
            if ($request->filled('prenom')) {
                $query->whereHas('prospect', function ($q) use ($request) {
                    $q->where('prenom', 'like', '%' . $request->input('prenom') . '%');
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
            if ($request->filled('user_id')) {
                $realUserId = User::on('temp')
                    ->where('user_id_origin', $request->user_id)
                    ->value('id');

                if ($realUserId) {
                    $query->where('user_id', $realUserId);
                }
            }

            if ($request->filled('bien')) {
                $query->whereHas('bien', function ($q) use ($request) {
                    $q->where('propriete_dite_bien', 'like', '%' . $request->input('bien') . '%');
                });
            }

            if ($request->filled('statut')) {
                $query->where('statut', $request->input('statut'));
            }


            $visites = $query->get()->groupBy('origin_id');
// Apply interet filter after grouping
        if ($request->filled('interet')) {
            $visites = $visites->filter(function ($group) use ($request) {
                return $group->first()->interet == $request->input('interet');
            });
        }
            $visites = $visites->map(function ($visite) {
                $firstVisite = $visite->first();
                return [
                'id' => $firstVisite->id,
                'origin_id' => $firstVisite->origin_id,
                'nom_cc' => $firstVisite->user ? $firstVisite->user->name : null,
                'prenom_cc' => $firstVisite->user ? $firstVisite->user->prenom : null,
                'date' => $firstVisite->created_at,
                'cin' => $firstVisite->prospect ? $firstVisite->prospect->cin : null,
                'nom' => $firstVisite->prospect ? $firstVisite->prospect->nom : null,
                'prenom' => $firstVisite->prospect ? $firstVisite->prospect->prenom : null,
                'telephone' => $firstVisite->prospect ? $firstVisite->prospect->telephone : null,
                'telephone2' => $firstVisite->prospect ? $firstVisite->prospect->telephone_num2 : null,
                'prospect_id' => $firstVisite->prospect ? $firstVisite->prospect->id : null,
                'interet' => $firstVisite->interet,
                'statut' => $firstVisite->statut,
                'propriete_dite_bien' => $firstVisite->bien ? $firstVisite->bien->propriete_dite_bien : '',
                'etat_bien' => $firstVisite->bien ? $firstVisite->bien->etat : '',
                'bien_id' => $firstVisite->bien_id ?? '',
                'visit_count' => $visite->count(),
                'reservation' => $firstVisite->reservation ?? null,
            ];
            });

            // Paginate the array of visites
            $data = PaginationHelper::paginate_array($visites->toArray(), $size, $page, $request->url());

            $items = $data->items();

            $pagination = [
                'currentPage' => $data->currentPage(),
                'totalItems'  => $data->total(),
                'totalPages'  => $data->lastPage(),
            ];

            return response()->json([
                'data'       => $items,
                'pagination' => $pagination,
            ], 200);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }


    public function get_historiques($origin_id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $frein_h     = new FreinController();
            $historiques = Visite::on('temp')->with('relance_relation', 'rdv_relation')
                ->where('origin_id', $origin_id)->withTrashed()->orderby('created_at', 'desc')->get();
            foreach ($historiques as $histo) {
                if ($histo->interet == InteretEnum::Perdu->value) {
                    $frein_h_       = $frein_h->searchFreinByVisiteId($histo->id, 'with_row_deleted_at');
                    $histo['frein'] = $frein_h_;
                }
            }
            return response()->json(['historiques' => $historiques], 200);
        }
    }

    public function get_oldBien_visite_pre_reserve($origin_id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $biens_visite = Visite::on('temp')->where('origin_id', $origin_id)->where('interet', InteretEnum::Intéressé->value)->where('etat', 1)->where('statut', StatutVisiteEnum::Pré_Réservation->value)->orderby('created_at', 'desc')->get(['bien_id', 'id']);
            //bien pre reserve par appel on cas des biens disponibles
            $biens_traitement_freins = TraitementFrein::on('temp')->with('bien')->where('origin_id', $origin_id)
                ->where('interet', InteretEnum::Intéressé->value)
                ->where('statut', StatutVisiteEnum::Pré_Réservation->value)->orderby('created_at', 'desc')->get(['bien_id', 'id'])->take(1);
            return response()->json(['biens_visite' => $biens_visite, 'biens_traitement_freins' => $biens_traitement_freins], 200);
        }
    }
    public function update_visite_bien_pre_reserve($id, Request $request)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            foreach ($request->list_biens_visite as $key => $list) {
                //Annuler pre reservation

                if ($list['action'] == 2) {
                    $bien = Bien::on('temp')->findorfail($list['bien_id']);
                    //$bien->etat = EtatBien::DISPONIBLE->value;
                    if ($bien->save()) {
                        if (isset($list['visite_id'])) {
                            $visite         = Visite::on('temp')->findorfail($list['visite_id']);
                            $visite->statut = StatutVisiteEnum::Pré_Réservation_Perdu->value;
                            $visite->save();
                        } else if (isset($list['traitement_frein_id'])) {
                            $t_f         = TraitementFrein::on('temp')->findorfail($list['traitement_frein_id']);
                            $t_f->statut = StatutVisiteEnum::Pré_Réservation_Perdu->value;
                            $t_f->save();
                        }

                    }

                }
            }
            return response()->json(['message' => 'suceees'], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }
    /**
     * Show the form for creating a new resource.
     */

    /**
     * Store a newly created resource in storage.
     */

    public function convertToInternational($number)
    {
        // Vérifie si le numéro commence par "0" et le remplace par "+212"
        if (preg_match('/^0(\d{9})$/', $number, $matches)) {
            return '+212' . $matches[1];
        }
        return $number; // Retourne le numéro inchangé s'il ne commence pas par "0"
    }
    public function send_whatsapp($request)
    {
        // Récupérer les identifiants UltraMsg depuis le fichier .env
        $instanceId = env('INSTANCE_ID_ULTRA_MSG');
        $token      = env('TOKEN_ULTRA_MSG');
        $to         = $request->to;

        // Envoyer la requête à l'API UltraMsg
        $response = Http::timeout(60)->post("https://api.ultramsg.com/$instanceId/messages/chat", [
            'token' => $token,
            'to'    => $request->to,
            'body'  => $request->body,
        ]);

        return $response->json(); // Retourne la réponse de l'API pour vérification
    }

    public function store(StoreVisiteRequest $request)
    {
        /***liste des fonctions a ajouter
        Chercher s'il y a appel du meme client==>le convertir en visite
        convert
        lead to visite
         ****/
         DatabaseHelper::Config();
        Config::set('broadcasting.default', 'pusher_3');
        // Start database transaction
        DB::connection('temp')->beginTransaction();

        $user = Auth::user();
        if (RoleHelper::ACSup()) {
        try {
            $msg_sended = 0;
            $projet = Projet::on('temp')->findorfail($request->selectedProjet);
            ///decoder les stringfy
            $list_bien_interesse       = json_decode($request->input('list_bien_interesse', '[]'), true);
            $list_bien_transfere_vendu = json_decode($request->input('list_bien_transfere_vendu', '[]'), true);

            //store prospect si client n'existe pas

            if ($request->prospect_id == null) {
                $validatedData              = $request->validated();
                $validatedData['cin']       = $request->cin;
                $validatedData['email']     = $request->email;
                $validatedData['source']    = $request->source_id;
                $validatedData['projet_id'] = $request->selectedProjet;

                if ($request->source_txt == 'Partenaire') {
                    $validatedData['partenaire_id'] = $request->partenaire_id;
                } else {
                    $validatedData['partenaire_id'] = null;
                }
                $validatedData['telephone']      = $request->telephone;
                $validatedData['nom']            = $request->nom;
                $validatedData['prenom']         = $request->prenom;
                $validatedData['telephone_num2'] = $request->telephone_num2 == "null" ? null : $request->telephone_num2;
                $validatedData['ville']          = $request->input('ville');
                $validatedData['origin']         = 'visite';
                $validatedData['notifie']        = $request->notifie;
                $prospectController = new ProspectController();
                $prospect = $prospectController->store(new StoreProspectRequest($validatedData));
                // Si un client_id est fourni, on lie ce prospect au client
                if ($request->client_id && $prospect) {
                    $client = Client::on('temp')->find($request->client_id);
                    if ($client) {
                        $client->prospect_id = $prospect->id;
                        $client->save();
                    }
                    $prospect->client_id = $request->client_id;
                    $prospect->save();
                }

            } else {
                //recupere le prospect //modifier info
                $prospect = Prospect::on('temp')->findorfail($request->prospect_id);
                //$prospect->cin=$request->cin;
                if ($request->cin != null) {
                    $cin_exist = Prospect::on('temp')->where('cin', $request->cin)->where('id', '!=', $request->prospect_id)->count();
                    if ($cin_exist == 0) {
                        $prospect->cin   = $request->cin;
                        $prospect->email = $request->email;
                    }
                }
                $prospect->nom            = $request->nom;
                $prospect->prenom         = $request->prenom;
                $prospect->telephone      = $request->telephone;
                $prospect->telephone_num2 = $request->telephone_num2 == "null" ? '' : $request->telephone_num2;;
                $prospect->ville          = $request->input('ville');
                $prospect->source         = $request->source_id;
                if ($request->source_txt == 'Partenaire') {
                    $prospect->partenaire_id = $request->partenaire_id;
                }

                $prospect->notifie = $request->notifie;
                $prospect->save();
            }
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            //(storee first ou n visite )
            $origin_id   = null;
            $last_number = null;
            if ($request->prospect_id != null) {
                $visite_exist = Visite::on('temp')->where('prospect_id', $request->prospect_id)->where('etat', 1)->where('projet_id', $request->selectedProjet)->orderBy('created_at', 'DESC')->first();
                if ($visite_exist != null) {
                    $last_number = intval(explode(" ", $visite_exist->description)[2]);
                    $origin_id   = $visite_exist->origin_id;
                }
            }
            //si receptif ou perdu
            if ($request->interet == InteretEnum::Réceptif->value || $request->interet == InteretEnum::Perdu->value) {
                //rendre bien disponible si interet!=Intéressé ==>Réceptif ou Perdu
                if ($list_bien_interesse) {
                    foreach ($list_bien_interesse as $key => $list_biens) {
                        if ($list_biens['bien_id']) {
                            Bien_Helper::libererBien($list_biens['bien_id'], null, null);
                        }
                    }
                }
                $visite = new Visite();
                $visite->setConnection('temp');

                if ($last_number == null) {
                    $visite->description = 'CREATION VISITE 1';
                } else {
                    $visite->description = 'CREATION VISITE ' . ($last_number + 1);
                }
                $visite->origin_id   = $origin_id;
                $visite->user_id     = $userAuth->value('id');
                $visite->prospect_id = $prospect->id;
                $visite->projet_id   = $request->selectedProjet;
                $visite->commentaire = $request->commentaire;
                $visite->interet     = $request->interet;
                //first visite bien==>show=1 et related_sho meme id du visite
                $visite->show = 1;
                if ($visite->save()) {
                    $visite->related_show_id = $visite->id;
                    if ($visite->origin_id == null) {
                        $visite->origin_id = $visite->id;
                    }
                    if ($visite->save()) {
                        //store relances et rdv et notifications
                        if ($visite->interet == InteretEnum::Réceptif->value) {
                            if ($request->date_relance != null) {
                                $data_notif = [
                                    'lien'        => '/crm/visites/' . $visite->origin_id,
                                    'date'        => $request->date_relance,
                                    'type'        => 1,
                                    'description' => 'RELANCE VISITE',
                                    'user_id'     => Auth::guard('api')->user()->id,
                                    'role'        => null,
                                    'visite_id'   => $visite->getAttribute('id'),
                                    'prospect_id' => $visite->prospect_id,
                                    'projet_id'   => $visite->projet_id,

                                ];
                                $notif_helper = new NotificationHelper();
                                $notif_helper->storeNotification($request->merge($data_notif));
                                broadcast(new NotificationEvent($visite->id));

                                $relance = new Relance_Rdv_Visite();
                                $relance->setConnection('temp');
                                $relance->type            = 1; //relance
                                $relance->mode_relance    = $request->mode_relance;
                                $relance->date_relance    = $request->date_relance;
                                $relance->type_traitement = 0; //0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                                $relance->user_id         = $userAuth->value('id');
                                $relance->visite_id       = $visite->id;
                                $relance->save();
                            }

                        }
                        if ($visite->interet == InteretEnum::Perdu->value) {

                            $freinRequest['visite_id']            = $visite->getAttribute('id');
                             $freinRequest['prix_min']             = str_contains($request->frein, 'prix')?$request->prix_min:NULL;
                            $freinRequest['freins']               = $request->frein;
                            $freinRequest['prix_max']             = str_contains($request->frein, 'prix')?$request->prix_max:null;
                            $freinRequest['sup_min']              = str_contains($request->frein, 'superficie')?$request->sup_min:null;
                            $freinRequest['sup_max']              = str_contains($request->frein, 'superficie')?$request->sup_max:null;
                            $freinRequest['etat']                 = 1;
                            $freinRequest['avance']               = str_contains($request->frein, 'avance')?$request->avance:null;
                            $freinRequest['selectedTranches']     = str_contains($request->frein, 'tranche')?$request->tranches:"";
                            //-1 for 0 si on selection just le 0
                            $freinRequest['selectedEtages'] = ($request->etages == "0") ? -100 : (str_contains($request->frein, 'etage') ? $request->etages : "");
                            $freinRequest['selectedOrientations'] = str_contains($request->frein, 'orientation')?$request->orientations:"";
                            $freinRequest['selectedTypologies']   = str_contains($request->frein, 'typologie')?$request->typologies:"";
                            $freinRequest['selectedVues']         = str_contains($request->frein, 'vue')?$request->vues:"";
                            $freinRequest['description_autre']    = str_contains($request->frein, 'autre')?$request->description_autre:"";

                            $freinController = new FreinController();
                            $freinController->store(new StoreFreinRequest($freinRequest));
                        }
                        //traite/supprimer les relances rdv des old visite=>automatique
                        $old_visites = Visite::on('temp')->where('origin_id', $visite->origin_id)->where('id', '!=', $visite->id)->orderBy('created_at', 'DESC')->get();
                        if (count($old_visites) > 0) {
                            foreach ($old_visites as $old_visite) {
                                if ($visite->interet == InteretEnum::Réceptif->value || $visite->interet == InteretEnum::Perdu->value) {
                                    //Si lors de l'ancienne visite le client a préreservé==>libérer le bien et supprimer les relances
                                    if ($old_visite->statut == StatutVisiteEnum::Pré_Réservation->value) {
                                        $oldBien = Bien::on('temp')->find($old_visite->bien_id);
                                        if ($oldBien->etat == 'Pré_Réservation' && $oldBien->historique_bien_pre_reserve->visite_id == $old_visite->id) {
                                            Bien_Helper::libererBien($old_visite->bien_id, null, null);
                                            if ($old_visite->statut == StatutVisiteEnum::Pré_Réservation->value) {
                                                $old_visite->statut = StatutVisiteEnum::Pré_Réservation_Perdu->value;
                                            } elseif ($old_visite->statut == StatutVisiteEnum::Vendu->value) {
                                                $old_visite->statut = StatutVisiteEnum::Réservation_Perdu->value;
                                            }
                                            $old_visite->save();
                                        }
                                    }
                                }

                                //SUPPRIMER LES OLDS NOTIF
                                $notif_old_relance = Notification::on('temp')->where(function ($query) {
                                    $query->where('type', 1)
                                        ->orwhere('type', 2);
                                })
                                    ->where(function ($query_2) use ($old_visite) {
                                        $query_2->where('visite_id', $old_visite->id);
                                    })
                                    ->get();
                                if (($notif_old_relance->count()) > 0) {
                                    foreach ($notif_old_relance as $nt_r) {
                                        $nt_r->delete();
                                    }
                                }
                                /***RENDRE LES OLD RELANCES ET OLD RDV EN TRAITE AUTOMATIQUE****/
                                $old_relances_rdv = Relance_Rdv_Visite::on('temp')->where('visite_id', $old_visite->id)->where('type_traitement', 0)->get();
                                if (count($old_relances_rdv) > 0) {
                                    foreach ($old_relances_rdv as $old) {
                                        $old->type_traitement = 2; //auto
                                        $old->date_traitement = Carbon::now();
                                        //si old visite pre reserve en suite n visite Vendu ==>user_id_traite(l'ancien user)
                                        if ($old->visite->statut == StatutVisiteEnum::Pré_Réservation->value) {
                                            if ($visite->statut == StatutVisiteEnum::Vendu->value) {
                                                $old->user_id_traite = $old_visite->user_id;
                                            } else {
                                                $old->user_id_traite = $userAuth->value('id');
                                            }
                                        } else {
                                            $old->user_id_traite = $userAuth->value('id');
                                        }
                                        $old->save();
                                    }

                                }
                            }
                        }

                        //store visite_id to ==>traitement_appel
                        if ($request->id_t_appel != null) {
                            $t_appel                         = TraitementAppel::on('temp')->findorfail($request->id_t_appel);
                            $t_appel->visite_id              = $visite->origin_id;
                            $t_appel->date_convert_visite    = Carbon::now();
                            $t_appel->user_id_convert_visite = $userAuth->value('id');
                            $t_appel->save();
                        }

                    }

                }

            } else {

                //store interesse
                //list_interesse
                $array_v_id        = [];
                $first_v_id        = 0;
                $first_v_origin_id = 0;

                if ($list_bien_interesse) {

                    foreach ($list_bien_interesse as $key => $list_biens) {

                        //test si le user connecte celui qui a  fait la proposition // bien pre reserve par autre
                        if ($list_biens['bien_id'] != null && $request->interet == InteretEnum::Intéressé->value) {
                            $bien_prop = Bien::on('temp')->findorfail($list_biens['bien_id']);
                            if ($bien_prop->etat != 'DISPONIBLE') {
                                if ($bien_prop->etat == 'ENCOURS_DE_PROPOSITION') {
                                    //test si le user connecte celui qui a  fait la proposition
                                    if ($bien_prop->is_proposed->user_id != Auth::guard('api')->user()->id) {
                                        return response()->json(['error_33' => 'le bien choisi :' . $bien_prop->propriete_dite_bien . ' est en cours de proposition  par : ' . $bien_prop->is_proposed->user->name . ' ' . $bien_prop->is_proposed->user->prenom], 333);
                                    }
                                } else {
                                    //bien !=encours proposition ==>pre reserve
                                    return response()->json(['error_33' => 'le bien choisi :' . $bien_prop->propriete_dite_bien . ' est ' . $bien_prop->etat], 333);
                                }
                            }
                        }

                        $visite = new Visite();
                        $visite->setConnection('temp');
                        if ($last_number == null) {
                            $visite->description = 'CREATION VISITE 1';
                        } else {
                            $visite->description = 'CREATION VISITE ' . intval($last_number) + 1;
                        }
                        $visite->origin_id   = $origin_id;
                        $visite->user_id     = $userAuth->value('id');
                        $visite->prospect_id = $prospect->id;
                        $visite->projet_id   = $request->selectedProjet;
                        $visite->commentaire = $list_biens['commentaire'];
                        $visite->interet     = $request->interet;
                        $visite->bien_id     = $list_biens['bien_id'];
                        $visite->statut      = $list_biens['statut'];

                        if ($visite->save()) {
                            //push les vistes_id to array pour supprimer les relances where id not int array_v_id
                            array_push($array_v_id, $visite->id);
                            //first visite bien==>show=1 et related_sho meme id du visite
                            if ($key == 0) {
                                if ($visite->origin_id == null) {
                                    $visite->origin_id = $visite->id;
                                }
                                $visite->related_show_id = $visite->id;
                                $first_v_id              = $visite->id;
                                $first_v_origin_id       = $visite->origin_id;
                                $visite->show            = 1;
                            } else {
                                $visite->related_show_id = $first_v_id;
                                $visite->origin_id       = $first_v_origin_id;
                            }

                            if ($visite->save()) {
                                //STORE HISTORIQUE DU BIEN
                                if ($list_biens['bien_id'] != null) {
                                    if ($visite->statut == StatutVisiteEnum::Vendu->value) {
                                        HistoriqueBienHelper::createHistoriqueBien(5, "Creation visite Vendu du client :" . $prospect->cin . ' ' . $prospect->nom . ' ' . $prospect->prenom, $visite->bien_id, Auth::guard('api')->user()->id, $visite->id, null, null, null);
                                    } else if ($visite->statut == StatutVisiteEnum::Pré_Réservation->value) {
                                        HistoriqueBienHelper::createHistoriqueBien(5, "Creation visite pré reservé du client :" . $prospect->cin . ' ' . $prospect->nom . ' ' . $prospect->prenom, $visite->bien_id, Auth::guard('api')->user()->id, $visite->id, null, null, null);
                                    }
                                }
                                //store relances et rdv et notifications
                                if ($visite->statut == StatutVisiteEnum::Pré_Réservation->value) {
                                    if ($list_biens['date_relance'] != null) {
                                        $data_notif = [
                                            'lien'        =>'/crm/visites/' . $visite->origin_id,
                                            'date'        => $list_biens['date_relance'],
                                            'type'        => 1,
                                            'description' => 'RELANCE VISITE',
                                            'user_id'     => Auth::guard('api')->user()->id,
                                            'role'        => null,
                                            'visite_id'   => $visite->getAttribute('id'),
                                            'prospect_id' => $visite->prospect_id,
                                            'projet_id'   => $visite->projet_id,

                                        ];
                                        $notif_helper = new NotificationHelper();
                                        $notif_helper->storeNotification($request->merge($data_notif));

                                        broadcast(new NotificationEvent($visite->id));
                                        $relance = new Relance_Rdv_Visite();
                                        $relance->setConnection('temp');
                                        $relance->type            = 1; //relance
                                        $relance->mode_relance    = $list_biens['mode_relance'];
                                        $relance->date_relance    = $list_biens['date_relance'];
                                        $relance->type_traitement = 0; //0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                                        $relance->user_id         = $userAuth->value('id');
                                        $relance->visite_id       = $visite->id;
                                        $relance->save();
                                    }
                                    if ($list_biens['rdv'] != null) {

                                        $data_notif = [
                                            'lien'        => '/crm/visites/'. $visite->origin_id,
                                            'date'        => $list_biens['rdv'],
                                            'type'        => 2,
                                            'description' => 'RDV VISITE',
                                            'user_id'     => Auth::guard('api')->user()->id,
                                            'role'        => null,
                                            'visite_id'   => $visite->getAttribute('id'),
                                            'prospect_id' => $visite->prospect_id,
                                            'projet_id'   => $visite->projet_id,

                                        ];
                                        $notif_helper = new NotificationHelper();
                                        $notif_helper->storeNotification($request->merge($data_notif));

                                        broadcast(new NotificationEvent($visite->id));
                                        $rdv = new Relance_Rdv_Visite();
                                        $rdv->setConnection('temp');
                                        $rdv->type            = 2; //rdv
                                        $rdv->rdv             = $list_biens['rdv'];
                                        $rdv->type_traitement = 0; //0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                                        $rdv->user_id         = $userAuth->value('id');
                                        $rdv->visite_id       = $visite->id;
                                        $rdv->save();

                                        if ($prospect->telephone != null) {

                                            $bien       = Bien::on('temp')->findorfail($list_biens['bien_id']);
                                            $data_whtsp = [
                                                'to'   => $this->convertToInternational($prospect->telephone),
                                                'body' => 'Bonjour ' . $prospect->nom . ' ' . $prospect->prenom . ', '
                                                . 'Merci pour votre visite chez le Projet ' . $projet->nom . ' aujourd’hui. '
                                                . 'Nous espérons que votre expérience a été agréable. '
                                                . 'Un Rendez-vous est prévue pour vous le ' . $list_biens['rdv'] . '. '
                                                . 'Concernant le Bien ' . $bien->propriete_dite_bien . '. '
                                                . 'N’hésitez pas à nous contacter si vous avez des questions d’ici là.',
                                            ];

                                            $this->send_whatsapp($request->merge($data_whtsp));
                                            $msg_sended = 1;
                                        }
                                    }
                                }

                                //store code pre reserve to table ==>PreReservation
                                if ($visite->interet == InteretEnum::Intéressé->value && $visite->statut == StatutVisiteEnum::Pré_Réservation->value) {
                                    $bien_c = new BienController();
                                    $bien_c->prereserverBien($visite->bien_id, $visite->id, null, null);

                                } elseif ($visite->interet == InteretEnum::Intéressé->value && $visite->statut == StatutVisiteEnum::Vendu->value) {
                                    //set visite pre reserve

                                    $reservationController = new ReservationController();
                                    $reservationRequest    = new StoreReservationRequest();

                                    $dataReservation = [
                                        'nb_acquereurs'        => 1,
                                        'code_reservation'     => $list_biens['code_reservation'],
                                        'prix'                 => $list_biens['prix'],
                                        'mode_financement'     => $list_biens['mode_financement'],
                                        'date_reservation'     => $list_biens['date_reservation'],
                                        'commentaire'          => $list_biens['commentaire_res'],
                                        'visite_id'            => $visite->id,
                                        'prix_remise'          => $list_biens['prix_remise'],
                                        'prix_forfetaire'      => $list_biens['prix_forfetaire'],
                                        'bien_id'              => $list_biens['bien_id'],
                                        'projet_id'            => $request->selectedProjet,
                                        'verifierPourcentages' => true,
                                        'origin'               => 'visite',
                                        'cin'                  => $request->cin,
                                        'nom'                  => $request->nom,
                                        'prenom'               => $request->prenom,
                                        'telephone_num1'       => $request->telephone,
                                        'telephone_num2'       => $request->telephone_num2 == "null" ? null : $request->telephone_num2,
                                        'notifie'              => $request->notifie==""?0:1,
                                        'prospect_id'          => $prospect->id,
                                        'civilite'             => '1',
                                        'type_client'          => 1,
                                        'situation_familliale' => 1,
                                        'sr'                   => $list_biens['sr'],
                                        'type_encaissement'    => 1,
                                        'avance'               => $list_biens['avance_res'],
                                        'mode_paiement'        => $list_biens['mode_paiement'],
                                        'numero_paiement'      => $list_biens['numero_paiement'],
                                        'date_reglement'       => $list_biens['date_reglement'],
                                        'echeance'             => $list_biens['echeance'],
                                        'banque_id'            => $list_biens['banque_id'],
                                        'commentaireAvance'    => $list_biens['commentaireAvance'],
                                        'num_remise'           => $list_biens['num_remise'],
                                        'date_encaissement'    => $list_biens['date_encaissement'],
                                        //'files_avance' => $request->selectedFiles_avc[$key],

                                    ];
                                    $reservationRequest->merge($dataReservation);
                                    $reservationController->store($reservationRequest);
                                }
                            }
                            //convert appel to visite
                            //store visite_id to ==>traitement_appel
                            if ($request->id_t_appel != null) {
                                $t_appel                         = TraitementAppel::on('temp')->findorfail($request->id_t_appel);
                                $t_appel->visite_id              = $first_v_id;
                                $t_appel->date_convert_visite    = Carbon::now();
                                $t_appel->user_id_convert_visite = $userAuth->value('id');
                                $t_appel->save();
                            }

                        }

                    }
                }

                //list des bien transfere vendu
                if ($list_bien_transfere_vendu != null) {
                    //list des biens interesse
                    foreach ($list_bien_transfere_vendu as $key => $list_biens) {
                        $visite = new Visite();
                        $visite->setConnection('temp');
                        if ($last_number == null) {
                            $visite->description = 'CREATION VISITE 1';
                        } else {
                            $visite->description = 'CREATION VISITE ' . intval($last_number) + 1;
                        }
                        $visite->origin_id   = $origin_id;
                        $visite->user_id     = $userAuth->value('id');
                        $visite->prospect_id = $prospect->id;
                        $visite->projet_id   = $request->selectedProjet;
                        $visite->commentaire = $list_biens['commentaire'];
                        $visite->interet     = $request->interet;
                        $visite->bien_id     = $list_biens['bien_id'];
                        $visite->statut      = $list_biens['statut'];

                        if ($visite->save()) {
                            //push les vistes_id to array pour supprimer les relances where id not int array_v_id
                            array_push($array_v_id, $visite->id);
                            //first visite bien==>show=1 et related_sho meme id du visite
                            if ($list_bien_interesse == null) {
                                if ($key == 0) {
                                    if ($visite->origin_id == null) {
                                        $visite->origin_id = $visite->id;
                                    }
                                    $visite->related_show_id = $visite->id;
                                    $first_v_id              = $visite->id;
                                    $first_v_origin_id       = $visite->origin_id;
                                    $visite->show            = 1;
                                } else {
                                    $visite->related_show_id = $first_v_id;
                                    $visite->origin_id       = $first_v_origin_id;
                                }
                            } else {
                                $visite->related_show_id = $first_v_id;
                                $visite->origin_id       = $first_v_origin_id;
                            }

                            if ($visite->save()) {
                                //STORE HISTORIQUE DU BIEN
                                if ($list_biens['bien_id'] != null) {
                                    if ($visite->statut == StatutVisiteEnum::Vendu->value) {
                                        HistoriqueBienHelper::createHistoriqueBien(5, "Creation visite Vendu du client :" . $prospect->cin . ' ' . $prospect->nom . ' ' . $prospect->prenom, $visite->bien_id, Auth::guard('api')->user()->id, $visite->id, null, null, null);
                                    } else if ($visite->statut == StatutVisiteEnum::Pré_Réservation->value) {
                                        HistoriqueBienHelper::createHistoriqueBien(5, "Creation visite pré reservé du client :" . $prospect->cin . ' ' . $prospect->nom . ' ' . $prospect->prenom, $visite->bien_id, Auth::guard('api')->user()->id, $visite->id, null, null, null);
                                    }
                                }
                                //store relances et rdv et notifications
                                if ($visite->statut == StatutVisiteEnum::Pré_Réservation->value) {
                                    if ($list_biens['date_relance'] != null) {
                                        $data_notif = [
                                            'lien'        => '/crm/visites/' . $visite->origin_id,
                                            'date'        => $list_biens['date_relance'],
                                            'type'        => 1,
                                            'description' => 'RELANCE VISITE',
                                            'user_id'     => Auth::guard('api')->user()->id,
                                            'role'        => null,
                                            'visite_id'   => $visite->getAttribute('id'),
                                            'prospect_id' => $visite->prospect_id,
                                            'projet_id'   => $visite->projet_id,

                                        ];
                                        $notif_helper = new NotificationHelper();
                                        $notif_helper->storeNotification($request->merge($data_notif));

                                        broadcast(new NotificationEvent($visite->id));
                                        $relance = new Relance_Rdv_Visite();
                                        $relance->setConnection('temp');
                                        $relance->type            = 1; //relance
                                        $relance->mode_relance    = $list_biens['mode_relance'];
                                        $relance->date_relance    = $list_biens['date_relance'];
                                        $relance->type_traitement = 0; //0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                                        $relance->user_id         = $userAuth->value('id');
                                        $relance->visite_id       = $visite->id;
                                        $relance->save();
                                    }
                                    if ($list_biens['rdv'] != null) {
                                        $data_notif = [
                                            'lien'        => '/crm/visites/' . $visite->origin_id,
                                            'date'        => $list_biens['rdv'],
                                            'type'        => 2,
                                            'description' => 'RDV VISITE',
                                            'user_id'     => Auth::guard('api')->user()->id,
                                            'role'        => null,
                                            'visite_id'   => $visite->getAttribute('id'),
                                            'prospect_id' => $visite->prospect_id,
                                            'projet_id'   => $visite->projet_id,

                                        ];
                                        $notif_helper = new NotificationHelper();
                                        $notif_helper->storeNotification($request->merge($data_notif));

                                        broadcast(new NotificationEvent($visite->id));
                                        $rdv = new Relance_Rdv_Visite();
                                        $rdv->setConnection('temp');
                                        $rdv->type            = 2; //rdv
                                        $rdv->rdv             = $list_biens['rdv'];
                                        $rdv->type_traitement = 0; //0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                                        $rdv->user_id         = $userAuth->value('id');
                                        $rdv->visite_id       = $visite->id;
                                        $rdv->save();

                                        if ($prospect->telephone != null) {
                                            $bien       = Bien::on('temp')->findorfail($list_biens['bien_id']);
                                            $data_whtsp = [
                                                'to'   => $this->convertToInternational($prospect->telephone),
                                                'body' => 'Bonjour ' . $prospect->nom . ' ' . $prospect->prenom . ', '
                                                . 'Merci pour votre visite chez le Projet ' . $projet->nom . ' aujourd’hui. '
                                                . 'Nous espérons que votre expérience a été agréable. '
                                                . 'Un Rendez-vous est prévue pour vous le ' . $list_biens['rdv'] . '. '
                                                . 'Concernant le Bien ' . $bien->propriete_dite_bien . '. '
                                                . 'N’hésitez pas à nous contacter si vous avez des questions d’ici là.',
                                            ];

                                            $this->send_whatsapp($request->merge($data_whtsp));
                                            $msg_sended = 1;
                                        }
                                    }
                                }

                                //store code pre reserve to table ==>PreReservation
                                if ($visite->interet == InteretEnum::Intéressé->value && $visite->statut == StatutVisiteEnum::Pré_Réservation->value) {
                                    $bien_c = new BienController();
                                    $bien_c->prereserverBien($visite->bien_id, $visite->id, null, null);

                                } elseif ($visite->interet == InteretEnum::Intéressé->value && $visite->statut == StatutVisiteEnum::Vendu->value) {
                                    //set visite pre reserve

                                    $reservationController = new ReservationController();
                                    $reservationRequest    = new StoreReservationRequest();

                                    $dataReservation = [
                                        'nb_acquereurs'        => 1,
                                        'code_reservation'     => $list_biens['code_reservation'],
                                        'prix'                 => $list_biens['prix'],
                                        'mode_financement'     => $list_biens['mode_financement'],
                                        'date_reservation'     => $list_biens['date_reservation'],
                                        'commentaire'          => $list_biens['commentaire_res'],
                                        'visite_id'            => $visite->id,
                                        'prix_remise'          => $list_biens['prix_remise'],
                                        'prix_forfetaire'      => $list_biens['prix_forfetaire'],
                                        'bien_id'              => $list_biens['bien_id'],
                                        'projet_id'            => $request->selectedProjet,
                                        'verifierPourcentages' => true,
                                        'origin'               => 'visite',
                                        'cin'                  => $request->cin,
                                        'nom'                  => $request->nom,
                                        'prenom'               => $request->prenom,
                                        'telephone_num1'       => $request->telephone,
                                        'telephone_num2'       => $request->telephone_num2 == "null" ? null : $request->telephone_num2,
                                        'notifie'              => $request->notifie==""?0:1,
                                        'prospect_id'          => $prospect->id,
                                        'civilite'             => '1',
                                        'type_client'          => 1,
                                        'situation_familliale' => 1,
                                        'sr'                   => $list_biens['sr'],
                                        'type_encaissement'    => 1,
                                        'avance'               => $list_biens['avance_res'],
                                        'mode_paiement'        => $list_biens['mode_paiement'],
                                        'numero_paiement'      => $list_biens['numero_paiement'],
                                        'date_reglement'       => $list_biens['date_reglement'],
                                        'echeance'             => $list_biens['echeance'],
                                        'banque_id'            => $list_biens['banque_id'],
                                        'commentaireAvance'    => $list_biens['commentaireAvance'],
                                        'num_remise'           => $list_biens['num_remise'],
                                        'date_encaissement'    => $list_biens['date_encaissement'],
                                        'files_avance'         => $list_biens['selectedFiles_avc'],

                                    ];
                                    $reservationRequest->merge($dataReservation);
                                    $reservationController->store($reservationRequest);

                                }

                                //set old visite to pre _reservation_vendu
                                if (isset($list_biens['visite_id'])) {
                                    $old_visite_transfere         = Visite::on('temp')->findorfail($list_biens['visite_id']);
                                    $old_visite_transfere->statut = StatutVisiteEnum::Pré_Réservation_Vendu->value;
                                    $old_visite_transfere->save();
                                } else if (isset($list_biens['traitement_frein_id'])) {
                                    $t_f         = TraitementFrein::on('temp')->findorfail($list_biens['traitement_frein_id']);
                                    $t_f->statut = StatutVisiteEnum::Pré_Réservation_Vendu->value;
                                    $t_f->save();
                                }
                            }
                            //convert appel to visite
                            //store visite_id to ==>traitement_appel
                            if ($request->id_t_appel != null) {
                                $t_appel                         = TraitementAppel::on('temp')->findorfail($request->id_t_appel);
                                $t_appel->visite_id              = $first_v_id;
                                $t_appel->date_convert_visite    = Carbon::now();
                                $t_appel->user_id_convert_visite = $userAuth->value('id');
                                $t_appel->save();
                            }
                        }
                    }
                }

                //traite/supprimer les relances rdv des old visite=>automatique
                $old_visites = Visite::on('temp')->where('origin_id', $origin_id)->whereNotIn('id', $array_v_id)->orderBy('created_at', 'DESC')->get();
                if (count($old_visites) > 0) {
                    foreach ($old_visites as $old_visite) {

                        //SUPPRIMER LES OLDS NOTIF
                        $notif_old_relance = Notification::on('temp')->where(function ($query) {
                            $query->where('type', 1)
                                ->orwhere('type', 2);
                        })
                            ->where(function ($query_2) use ($old_visite) {
                                $query_2->where('visite_id', $old_visite->id);
                            })
                            ->get();
                        if (($notif_old_relance->count()) > 0) {
                            foreach ($notif_old_relance as $nt_r) {
                                $nt_r->delete();
                            }
                        }
                        /***RENDRE LES OLD RELANCES ET OLD RDV EN TRAITE AUTOMATIQUE****/
                        $old_relances_rdv = Relance_Rdv_Visite::on('temp')->where('visite_id', $old_visite->id)->where('type_traitement', 0)->get();
                        if (count($old_relances_rdv) > 0) {
                            foreach ($old_relances_rdv as $old) {
                                $old->type_traitement = 2; //auto
                                $old->date_traitement = Carbon::now();
                                //si old visite pre reserve en suite n visite Vendu ==>user_id_traite(l'ancien user)
                                if ($old->visite->statut == StatutVisiteEnum::Pré_Réservation->value) {
                                    if ($visite->statut == StatutVisiteEnum::Vendu->value) {
                                        $old->user_id_traite = $old_visite->user_id;
                                    } else {
                                        $old->user_id_traite = $userAuth->value('id');
                                    }
                                } else {
                                    $old->user_id_traite = $userAuth->value('id');
                                }
                                $old->save();
                            }

                        }
                    }
                }

            }
            // store initial statut du prospect based on programmed actions
            // If created from a visite, do NOT mark as Converti_en_visite by default.
            // Use RDV programmé (1) if any RDV exists, else Relance programmée (3) if any relance exists,
            // otherwise En attente (0).
            $visitIds = \App\Models\Visite::on('temp')
                ->where('origin_id', $origin_id)
                ->pluck('id');
            $hasRdv = \App\Models\Relance_Rdv_Visite::on('temp')
                ->whereIn('visite_id', $visitIds)
                ->where('type', 2) // 2 => RDV
                ->exists();
            $hasRelance = \App\Models\Relance_Rdv_Visite::on('temp')
                ->whereIn('visite_id', $visitIds)
                ->where('type', 1) // 1 => Relance
                ->exists();

            $initialStatut = '0';
            $comment = 'Prospect créé via visite';
            if ($hasRdv) {
                $initialStatut = '1'; // Planification_RDV => Rendez-vous programmé
                $comment = 'Rendez-vous programmé via création de visite';
            } elseif ($hasRelance) {
                $initialStatut = '3'; // Rappel => Relance programmée
                $comment = 'Relance programmée via création de visite';
            }

            $statut_pro = new StatutProspect();
            $statut_pro->setConnection('temp');
            $statut_pro->prospect_id     = $prospect->id;
            $statut_pro->statut          = $initialStatut;
            $statut_pro->date_traitement = Carbon::now();
            $statut_pro->user_id_traite  = $userAuth->value('id');
            $statut_pro->visite_id       = $origin_id;
            $statut_pro->commentaire     = $comment;
            $statut_pro->save();
            //send message WhatsApp de bienvenue en cas de n'existe pas de relance ou rendez-vous ou frein

            if ($msg_sended == 0  && $prospect->telephone != null) {
                    $data_whtsp = [
                        'to'   => $this->convertToInternational($prospect->telephone),
                        'body' => 'Bonjour ' . $prospect->nom . ' ' . $prospect->prenom . ', '
                        . 'Merci pour votre visite chez le Projet ' . $projet->nom . ' aujourd’hui. '
                        . 'Nous espérons que votre expérience a été agréable. '
                        . 'N’hésitez pas à nous contacter si vous avez des questions d’ici là.',
                    ];

                    $this->send_whatsapp($request->merge($data_whtsp));
            }


            // Commit transaction if everything is successful
            DB::connection('temp')->commit();

            return response()->json(['success' => 'Visite created successfully'], 200);

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::connection('temp')->rollBack();

            \Log::error("Visite creation failed: " . $e->getMessage());
            return response()->json(['error' => 'Visite creation failed: ' . $e->getMessage()], 500);
        }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }

    /**
     * Display the specified resource.
     */
    public function get_propriete_bien_concat($id)
    {
        DatabaseHelper::Config();
        $b_pr      = Bien::on('temp')->with(['tranche', 'bloc', 'immeuble'])->findorfail($id);
        $propriete = 0;
        $nom       = '';

        // Tranche + Bloc + Immeuble
        if ($b_pr->tranche && $b_pr->bloc && $b_pr->immeuble) {
            $nom = $b_pr->tranche->nom . '-' . $b_pr->bloc->nom . '-' . $b_pr->immeuble->nom;
        }
        // Tranche + Bloc
        elseif ($b_pr->tranche && $b_pr->bloc && ! $b_pr->immeuble) {
            $nom = $b_pr->tranche->nom . '-' . $b_pr->bloc->nom;
        }
        // Tranche + Immeuble
        elseif ($b_pr->tranche && ! $b_pr->bloc && $b_pr->immeuble) {
            $nom = $b_pr->tranche->nom . '-' . $b_pr->immeuble->nom;
        }
        // Bloc + Immeuble
        elseif (! $b_pr->tranche && $b_pr->bloc && $b_pr->immeuble) {
            $nom = $b_pr->bloc->nom . '-' . $b_pr->immeuble->nom;
        }
        // Bloc only
        elseif (! $b_pr->tranche && $b_pr->bloc && ! $b_pr->immeuble) {
            $nom = $b_pr->bloc->nom;
        }
        // Immeuble only
        elseif (! $b_pr->tranche && ! $b_pr->bloc && $b_pr->immeuble) {
            $nom = $b_pr->immeuble->nom;
        }
        // Tranche only
        elseif ($b_pr->tranche && ! $b_pr->bloc && ! $b_pr->immeuble) {
            $nom = $b_pr->tranche->nom;
        }
        return response()->json($nom);

    }

    public function relance_rdv_by_visite($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $histo = Visite::on('temp')->with('historique_relances_rdvs')->where('origin_id', $id)->where('etat', 1)->orderby('created_at', 'DESC')->get();
            return response()->json(['histo' => $histo], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function show($id)
    {

        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();

            $visite = Visite::on('temp')->with('relance_relation', 'rdv_relation', 'reservation')->findOrfail($id);
            $frein  = new FreinController();
            if ($visite->interet == InteretEnum::Perdu->value) {
                $visite['frein'] = $frein->searchFreinByVisiteId($visite->id, 'without_row_deleted');
            }

            $relatedVisites = Visite::on('temp')->with('freins', 'pre_reservation_visite', 'relance_relation', 'rdv_relation', 'reservation', 'traitement_frein', 'traitement_frein.bien', 'traitement_frein.rdv_relation', 'traitement_frein.frein')->where('origin_id', $visite->id)->where('etat', 1)->orderby('created_at', 'DESC')->get();

            foreach ($relatedVisites as $relatedVisite) {
                if ($relatedVisite->interet == InteretEnum::Perdu->value) {
                    $frein_v                = $frein->searchFreinByVisiteId($relatedVisite->id, 'without_row_deleted');
                    $relatedVisite['frein'] = $frein_v;
                }
            }
            $relatedVisites_show = Visite::on('temp')->with('pre_reservation_visite', 'relance_relation', 'rdv_relation', 'reservation')->where('origin_id', $visite->id)->where('etat', 1)->where('show', 1)->orderby('created_at', 'DESC')->get();
            //get nom propriete _dite_bien concat utilisé dans edit visite
            $propriete = null;
            if ($visite->bien_id != null) {
                $propriete = $this->get_propriete_bien_concat($visite->bien_id);
            }

            return response()->json(['visite' => $visite, 'propriete_dite_bien' => $propriete, 'visites' => $relatedVisites, 'visites_show' => $relatedVisites_show], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public static function traiter_relance_rdv_visite($id, UpdateDate_relance_Rdv $request)
    {
        if (RoleHelper::ACSup()) {
            // Config::set('broadcasting.default', 'pusher_5');
            DatabaseHelper::Config();
            $user     = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $relance  = Relance_Rdv_Visite::on('temp')->findOrFail($id);
            //if date !=null (nouvelle relance )
            if ($request->date != null) {

                $visite_id                = $relance->visite_id;
                $prospect_id              = $relance->visite->prospect_id;
                $old_mode_relance         = $relance->mode_relance;
                $relance->type_traitement = 3; //demi traitement
                $relance->date_traitement = Carbon::now();
                $relance->commentaire     = $request->commentaire;
                $relance->user_id_traite  = $userAuth->value('id');
                if ($relance->save()) {
                    //delete old notificcation
                    $notif_exist_relance = Notification::on('temp')->where('type', $relance->type)->where('visite_id', $visite_id)->get();
                    if (count($notif_exist_relance) > 0) {
                        foreach ($notif_exist_relance as $nt) {
                            $nt->delete();
                        }
                    }
                    //store new relance
                    $new_relance = new Relance_Rdv_Visite();
                    $new_relance->setConnection('temp');
                    if ($relance->type == 1) {
                        $new_relance->type         = 1; //relance
                        $new_relance->mode_relance = $old_mode_relance;
                        $new_relance->date_relance = $request->date;

                    } else {
                                                //rdv
                        $new_relance->type = 2; //rdv
                        $new_relance->rdv  = $request->date;
                    }
                    $new_relance->type_traitement = 0; //0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                    $new_relance->user_id         = $userAuth->value('id');
                    $new_relance->visite_id       = $visite_id;
                    $new_relance->save();

                    if ($relance->type == 1) {
                        //store new notification
                        Config::set('broadcasting.default', 'pusher_3');

                        $data_notif = [
                            'lien'        => '/crm/visites/'. $new_relance->visite->origin_id,
                            'date'        => $request->date,
                            'type'        => 1,
                            'description' => 'RELANCE VISITE',
                            'user_id'     => Auth::guard('api')->user()->id,
                            'role'        => null,
                            'visite_id'   => $visite_id,
                            'prospect_id' => $prospect_id,
                            'projet_id'   => $new_relance->visite->projet_id,

                        ];
                        $notif_helper = new NotificationHelper();
                        $notif_helper->storeNotification($request->merge($data_notif));
                        broadcast(new NotificationEvent($new_relance->id));
                        Config::set('broadcasting.default', 'pusher_5');
                        broadcast(new NotifMenuEvent('A'));
                    } else {
                        //store new notification
                        Config::set('broadcasting.default', 'pusher_3');

                        $data_notif = [
                            'lien'        => '/crm/visites/' . $new_relance->visite->origin_id,
                            'date'        => $request->date,
                            'type'        => 2,
                            'description' => 'RDV VISITE',
                            'user_id'     => Auth::guard('api')->user()->id,
                            'role'        => null,
                            'visite_id'   => $visite_id,
                            'prospect_id' => $prospect_id,
                            'projet_id'   => $new_relance->visite->projet_id,

                        ];
                        $notif_helper = new NotificationHelper();
                        $notif_helper->storeNotification($request->merge($data_notif));
                        broadcast(new NotificationEvent($new_relance->id));
                        Config::set('broadcasting.default', 'pusher_5');
                        broadcast(new NotifMenuEvent('B'));

                    }
                    return response()->json(['message' => $new_relance], 200);
                }
            } else {
                // si date ==null la relance /rdv  est traité

                $relance->type_traitement = 1; //manuelle
                $relance->commentaire     = $request->commentaire;
                $relance->date_traitement = Carbon::now();
                $relance->user_id_traite  = $userAuth->value('id');
                if ($relance->save()) {
                    //delete old notificcation
                    $notif_exist_relance = Notification::on('temp')->where('type', $relance->type)->where('visite_id', $relance->visite_id)->get();
                    if (count($notif_exist_relance) > 0) {
                        foreach ($notif_exist_relance as $nt) {
                            $nt->delete();
                        }
                    }
                    // broadcast(new NotificationEvent($relance->id));
                    Config::set('broadcasting.default', 'pusher_5');
                    if ($relance->type == 1) {
                        //relance
                        broadcast(new NotifMenuEvent('A'));
                    } else {
                        //rdv
                        broadcast(new NotifMenuEvent('B'));
                    }
                    return response()->json(['message' => 'Validé avec succès.'], 200);
                }

            }

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Visite $visite)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVisiteRequest $request, $id)
    {

        $user = Auth::user();
        if (RoleHelper::ACSup()) {
                DatabaseHelper::Config();
                Config::set('broadcasting.default', 'pusher_3');
                     // Start database transaction
                DB::connection('temp')->beginTransaction();

                try {
                    $userAuth        = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                    $old_visite      = Visite::on('temp')->findOrFail($id);
                    $old_description = $old_visite->description;
                    /************************Track changes historique************** */


                    // Initialize changes array
                    $changes = [];

                    // Define fields to track
                    $fieldsToTrack = [
                        'commentaire', 'interet', 'statut', 'bien_id',
                    ];

                    // Track changes for basic fields
                    foreach ($fieldsToTrack as $field) {
                        if ($request->has($field) && $request->input($field) != $old_visite->$field) {
                            $changes[$field] = [
                                'old' => $old_visite->$field,
                                'new' => $request->input($field)
                            ];
                        }
                    }

                    // Track prospect changes separately
                    $prospectChanges = [];
                    $prospectFields = [
                        'cin', 'email', 'nom', 'prenom', 'telephone',
                        'telephone_num2', 'ville', 'notifie'
                    ];

                        $prospect = Prospect::on('temp')->findorfail($old_visite->prospect_id);
                    // Special handling for source (track source instead of source_id)
                    if ($request->has('source_id') && $request->input('source_id') != $prospect->source) {
                        $prospectChanges['source'] = [
                            'old' => $prospect->source,
                            'new' => $request->input('source_id') // This will store the source ID
                        ];
                    }

                    // Special handling for partenaire_id (keep this as is)
                    if ($request->has('partenaire_id') && $request->input('partenaire_id') != $prospect->partenaire_id) {
                        $prospectChanges['partenaire_id'] = [
                            'old' => $prospect->partenaire_id,
                            'new' => $request->input('partenaire_id')
                        ];
                    }

                    foreach ($prospectFields as $field) {
                        if ($request->has($field) && $request->input($field) != $prospect->$field) {
                            // Special handling for CIN
                            if ($field == 'cin' && $request->cin != null) {
                                $cin_exist = Prospect::on('temp')->where('cin', $request->cin)
                                    ->where('id', '!=', $old_visite->prospect_id)->count();
                                if ($cin_exist > 0) continue; // Skip if CIN exists for another prospect
                            }

                            $prospectChanges[$field] = [
                                'old' => $prospect->$field,
                                'new' => $request->input($field)
                            ];
                        }
                    }

                    if (!empty($prospectChanges)) {
                        $changes['prospect'] = $prospectChanges;
                    }
                    /************************Track changes historique************** */

                    //test si le user connecte celui qui a  fait la proposition
                    if ($request->bien_id != null && $request->interet == InteretEnum::Intéressé->value) {
                        $bien_prop = Bien::on('temp')->findorfail($request->bien_id);

                        if ($bien_prop->etat == 'ENCOURS_DE_PROPOSITION' && $bien_prop->is_proposed->user_id != Auth::guard('api')->user()->id) {
                            return response()->json(['error_33' => 'le bien choisi :' . $bien_prop->propriete_dite_bien . ' est en cours de proposition  par : ' . $bien_prop->is_proposed->user->name . ' ' . $bien_prop->is_proposed->user->prenom], 333);
                        }
                    }

                    //copier ancien visite et mettre new visite
                    $visite = $old_visite->replicate();
                    $visite->setConnection('temp');
                    if ($visite->save()) {
                        $old_visite->etat = 0;
                        $old_visite->save();
                    }
                    //calcul nb visite
                    /*$description=null;
                    $visites_count=Visite::on('temp')->where('origin_id',$visite->origin_id)->where('etat',1)->orderBy('created_at', 'DESC')->count();
                    if($visites_count>0){
                    $description='MODIFICATION VISITE '.$visites_count;
                    }*/

                    //recupere le prospect //modifier info
                    $prospect = Prospect::on('temp')->findorfail($visite->prospect_id);
                    if ($request->cin != null) {
                        $cin_exist = Prospect::on('temp')->where('cin', $request->cin)->where('id', '!=', $visite->prospect_id)->count();
                        if ($cin_exist == 0) {
                            $prospect->cin   = $request->cin;
                            $prospect->email = $request->email;
                        }
                    }
                    $prospect->nom            = $request->nom;
                    $prospect->prenom         = $request->prenom;
                    $prospect->telephone      = $request->telephone;
                    $prospect->telephone_num2 = $request->telephone_num2 == "null" ? null : $request->telephone_num2;
                    $prospect->ville          = $request->input('ville');
                    $prospect->source         = $request->source_id;
                    if ($request->source_txt == 'Partenaire') {
                        $prospect->partenaire_id = $request->partenaire_id;
                    }
                    $prospect->notifie = $request->notifie;
                    $prospect->save();

                    /****Libérer le bien de l'ancienne visite**/
                    //chngement de bien
                    if ($request->interet == InteretEnum::Intéressé->value) {
                        if (($visite->statut == StatutVisiteEnum::Pré_Réservation->value || $visite->statut == StatutVisiteEnum::Vendu->value) && $visite->bien_id != null) {
                            if ($visite->bien_id != $request->bien_id) {
                                $oldBien = Bien::on('temp')->find($visite->bien_id);
                                if ($oldBien->etat == 'PRE_RESERVATION' || $oldBien->etat == 'VENDU') {
                                    Bien_Helper::libererBien($visite->bien_id, null, null);
                                    $newBien = Bien::on('temp')->find($request->bien_id);
                                $changes['bien'] = [
                                        'old' => $oldBien ? $oldBien : $visite->bien_id,
                                        'new' => $newBien ? $newBien : $request->bien_id
                                        ];
                                }
                            }
                        }

                    }

                    //changement d'interet (Réceptif ou Perdu)
                    if ($visite->bien_id != null && $request->interet != InteretEnum::Intéressé->value) {
                        Bien_Helper::libererBien($visite->bien_id, null, null);

                    }

                    $visite->user_id     = $userAuth->value('id');
                    $visite->commentaire = $request->commentaire;
                    if (str_contains($old_description, 'CREATION') == true) {
                        $visite->description = str_replace('CREATION', 'MODIFICATION', $old_description);
                    } else {
                        $visite->description = $old_description;

                    }
                    $visite->old_v_id = $id;
                    $visite->interet  = $request->interet;

                    //Intéressé
                    if ($request->interet == InteretEnum::Intéressé->value) {
                        $visite->bien_id = $request->bien_id;
                        $visite->statut  = $request->statut;
                    } elseif ($request->interet == InteretEnum::Réceptif->value) {
                        $visite->statut  = null;
                        $visite->bien_id = null;
                    } elseif ($request->interet == InteretEnum::Perdu->value) {
                        $visite->statut  = null;
                        $visite->bien_id = null;
                    }
                    $visite->save();

                    /** store relances et rdv **/
                    //STORE HISTORIQUE DU BIEN
                    if ($visite->bien_id != null) {
                        if ($visite->statut == StatutVisiteEnum::Vendu->value) {
                            HistoriqueBienHelper::createHistoriqueBien(5, "Modification visite vendu du client :" . $prospect->cin . ' ' . $prospect->nom . ' ' . $prospect->prenom, $visite->bien_id, Auth::guard('api')->user()->id, $visite->id, null, null, null);
                        } else if ($visite->statut == StatutVisiteEnum::Pré_Réservation->value) {
                            HistoriqueBienHelper::createHistoriqueBien(5, "Modification visite pré reservé du client :" . $prospect->cin . ' ' . $prospect->nom . ' ' . $prospect->prenom, $visite->bien_id, Auth::guard('api')->user()->id, $visite->id, null, null, null);
                        }
                    }
                    /**si ancien Perdu avec notif des bien dispo on supprime la notif** pas encours */
                    //supprimer ancien notif relance rdv
                    $notif_exist_relance_rdv = Notification::on('temp')->whereIN('type', [1, 2])->where('visite_id', $old_visite->id)->get();
                    if (count($notif_exist_relance_rdv) > 0) {
                        foreach ($notif_exist_relance_rdv as $nt) {
                            $nt->delete();
                        }
                    }

                    //store relances et rdv et notifications
                if ($visite->statut == StatutVisiteEnum::Pré_Réservation->value || $visite->interet == InteretEnum::Réceptif->value) {
                                // Track relance/rdv fields only for this condition
                                $relanceFields = ['date_relance', 'mode_relance', 'rdv'];
                                foreach ($relanceFields as $field) {
                                    if ($request->has($field) && $request->input($field) != $old_visite->$field) {
                                        if ($field == 'rdv') {
                                            $changes[$field] = [
                                                'old' => $old_visite->rdv_relation ? $old_visite->rdv_relation->$field : null,
                                                'new' => $request->input($field)
                                            ];
                                        } else {
                                            $changes[$field] = [
                                                'old' => $old_visite->relance_relation ? $old_visite->relance_relation->$field : null,
                                                'new' => $request->input($field)
                                            ];
                                        }
                                    }
                                }
                        if ($request->date_relance != null) {
                            if ($old_visite->relance_relation != null) {
                                $old_visite->relance_relation->delete();
                            }
                            $data_notif = [
                                'lien'        => '/crm/visites/'. $visite->origin_id,
                                'date'        => $request->date_relance,
                                'type'        => 1,
                                'description' => 'RELANCE VISITE',
                                'user_id'     => Auth::guard('api')->user()->id,
                                'role'        => null,
                                'visite_id'   => $visite->id,
                                'prospect_id' => $visite->prospect_id,
                                'projet_id'   => $visite->projet_id,

                            ];

                            
                            $notif_helper = new NotificationHelper();
                            $notif_helper->storeNotification($request->merge($data_notif));

                            broadcast(new NotificationEvent($visite->id));
                            $relance = new Relance_Rdv_Visite();
                            $relance->setConnection('temp');
                            $relance->type            = 1; //relance
                            $relance->mode_relance    = $request->mode_relance;
                            $relance->date_relance    = $request->date_relance;
                            $relance->type_traitement = 0; //0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                            $relance->user_id         = $userAuth->value('id');
                            $relance->visite_id       = $visite->id;
                            $relance->save();
                        }
                        if ($request->rdv != null) {
                            if ($old_visite->rdv_relation != null) {
                                $old_visite->rdv_relation->delete();
                            }
                            $data_notif = [
                                'lien'        => '/crm/visites/'. $visite->origin_id,
                                'date'        => $request->rdv,
                                'type'        => 2,
                                'description' => 'RDV VISITE',
                                'user_id'     => Auth::guard('api')->user()->id,
                                'role'        => null,
                                'visite_id'   => $visite->getAttribute('id'),
                                'prospect_id' => $visite->prospect_id,
                                'projet_id'   => $visite->projet_id,

                            ];
                            $notif_helper = new NotificationHelper();
                            $notif_helper->storeNotification($request->merge($data_notif));

                            broadcast(new NotificationEvent($visite->id));
                            $rdv = new Relance_Rdv_Visite();
                            $rdv->setConnection('temp');
                            $rdv->type            = 2; //rdv
                            $rdv->rdv             = $request->rdv;
                            $rdv->type_traitement = 0; //0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                            $rdv->user_id         = $userAuth->value('id');
                            $rdv->visite_id       = $visite->id;
                            $rdv->save();
                        }
                    }
                    /*//store code pre reserve to table ==>PreReservation
                    if ($old_visite->statut != StatutVisiteEnum::Pré_Réservation->value) {
                        if ($visite->interet == InteretEnum::Intéressé->value && $visite->statut == StatutVisiteEnum::Pré_Réservation->value) {
                            $bien_c = new BienController();
                            $bien_c->prereserverBien($visite->bien_id, $visite->id, null,null);
                            HistoriqueBienHelper::createHistoriqueBien(2, "Pre Reserve pour le prospect :" . $prospect->cin . ' ' . $prospect->nom . ' ' . $prospect->prenom, $visite->bien_id, Auth::guard('api')->user()->id, $visite->id, null,null,null);

                        }
                    }
                    //IF CHANGE BIEN NOT CHANGING STATUT
                    if ($visite->interet == InteretEnum::Intéressé->value && $visite->statut == StatutVisiteEnum::Pré_Réservation->value) {
                        if($old_visite->bien_id!=$visite->bien_id){
                            //pre reserve le new bien
                            $bien_c = new BienController();
                            $bien_c->prereserverBien($visite->bien_id, $visite->id, null,null);
                            HistoriqueBienHelper::createHistoriqueBien(2, "Pre Reserve pour le prospect :" . $prospect->cin . ' ' . $prospect->nom . ' ' . $prospect->prenom, $visite->bien_id, Auth::guard('api')->user()->id, $visite->id, null,null,null);

                        }
                    }*/
                    // Conditions communes
                    $isInteresseEtPreReserve = $visite->interet == InteretEnum::Intéressé->value &&
                    $visite->statut == StatutVisiteEnum::Pré_Réservation->value;

                    // Condition 1 : Passage en pré-réservation
                    $wasNotPreReserve = $old_visite->statut != StatutVisiteEnum::Pré_Réservation->value;

                    // Condition 2 : Changement de bien
                    $bienChanged = $old_visite->bien_id != $visite->bien_id;

                    if ($isInteresseEtPreReserve && ($wasNotPreReserve || $bienChanged)) {
                        $bienController = new BienController();
                        $bienController->prereserverBien($visite->bien_id, $visite->id, null, null);

                    }

                    //if($old_visite->statut!=StatutVisiteEnum::Vendu->value ){
                    if ($visite->interet == InteretEnum::Intéressé->value && $visite->statut == StatutVisiteEnum::Vendu->value) {

                            $reservationChanges = [];

                        $reservationFields = [
                            'code_reservation', 'prix', 'mode_financement', 'date_reservation',
                            'commentaire_res', 'prix_remise', 'prix_forfetaire', 'avance_res',
                            'mode_paiement', 'numero_paiement', 'date_reglement', 'echeance',
                            'banque_id', 'commentaireAvance', 'num_remise', 'date_encaissement'
                        ];

                        foreach ($reservationFields as $field) {
                            if ($request->has($field)) {
                                $reservationChanges[$field] = [
                                    'new' => $request->input($field)
                                ];
                            }
                        }


                        if (!empty($reservationChanges)) {
                            $changes['reservation'] = $reservationChanges;
                        }

                    /***********************Track reservation************* */
                        $reservationController = new ReservationController();
                        $reservationRequest    = new StoreReservationRequest();

                        $numberToWords          = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
                        $prix_remise_lettre     = $numberToWords->format($request->prix_remise);
                        $prix_forfetaire_lettre = $numberToWords->format($request->prix_forfetaire);

                        $dataReservation = [
                            'nb_acquereurs'          => 1,
                            'code_reservation'       => $request->code_reservation,
                            'prix'                   => $request->prix,
                            'mode_financement'       => $request->mode_financement,
                            'date_reservation'       => $request->date_reservation,
                            'commentaire'            => $request->commentaire_res,
                            'visite_id'              => $visite->id,
                            'prix_remise'            => $request->prix_remise,
                            'prix_remise_lettre'     => $request->prix_remise_lettre,
                            'prix_forfetaire'        => $request->prix_forfetaire,
                            'prix_forfetaire_lettre' => $request->prix_forfetaire_lettre,
                            'bien_id'                => $request->bien_id,
                            'projet_id'              => $visite->projet_id,
                            'verifierPourcentages'   => true,
                            'origin'                 => 'visite',
                            'cin'                    => $prospect->cin,
                            'nom'                    => $prospect->nom,
                            'prenom'                 => $prospect->prenom,
                            'telephone_num1'         => $prospect->telephone,
                            'telephone_num2'         => $prospect->telephone_num2,
                            'ville'                  => $prospect->ville,
                            'notifie'                => $prospect->notifie,
                            'prospect_id'            => $prospect->id,
                            'civilite'               => '1',
                            'type_client'            => 1,
                            'situation_familliale'   => 1,
                            'sr'                     => ($request->sr === 'false' || $request->sr === null) ? 0 : 1,
                            'check_montant'          => ($request->check_montant === 'false' || $request->check_montant === null) ? 0 : 1,
                            'type_encaissement'      => 1,
                            'avance'                 => $request->avance_res,
                            'mode_paiement'          => $request->mode_paiement,
                            'numero_paiement'        => $request->numero_paiement,
                            'date_reglement'         => $request->date_reglement,
                            'echeance'               => $request->echeance,
                            'banque_id'              => $request->banque_id,
                            'commentaireAvance'      => $request->commentaireAvance,
                            'num_remise'             => $request->num_remise,
                            'date_encaissement'      => $request->date_encaissement,
                            'files_avance'           => $request->selectedFiles_avc,

                        ];
                        $reservationRequest->merge($dataReservation);
                        $reservationController->store($reservationRequest);

                    }



                    if ($visite->interet == InteretEnum::Perdu->value) {
                        /************************Track visite Perdu*******************
                            $freinChanges = [];

                            // Track frein fields
                            $freinFields = [
                                'prix_min', 'prix_max', 'sup_min', 'sup_max', 'avance',
                                'description_autre'
                            ];

                            foreach ($freinFields as $field) {
                                if ($request->has($field) && $request->input($field) != $old_visite->$field) {
                                    $freinChanges[$field] = [
                                        'old' => $old_visite->$field ?? null,
                                        'new' => $request->input($field)
                                    ];
                                }
                            }

                            // Track array fields (tranches, etages, orientations, etc.)
                            $arrayFields = [
                                'tranches', 'etages', 'orientations', 'typologies', 'vues', 'frein'
                            ];

                            foreach ($arrayFields as $field) {
                                if ($request->has($field)) {
                                    $newValue = is_array($request->input($field)) ? $request->input($field) : json_decode($request->input($field), true);
                                    $freinChanges[$field] = [
                                        'new' => $newValue
                                    ];
                                }
                            }

                            if (!empty($freinChanges)) {
                                $changes['frein'] = $freinChanges;
                            }
                            /************************Track visite Perdu*******************/
                            $frein_id                             = Frein::on('temp')->where('visite_id', $visite->id)->get();
                            $freinRequest['prix_min']             = str_contains($request->frein, 'PRIX')?$request->prix_min:null;
                            $freinRequest['freins']               = $request->frein;
                            $freinRequest['prix_max']             = str_contains($request->frein, 'PRIX')?$request->prix_max:null;
                            $freinRequest['sup_min']              = str_contains($request->frein, 'SUPERFICIE')?$request->sup_min:null;
                            $freinRequest['sup_max']              = str_contains($request->frein, 'SUPERFICIE')?$request->sup_max:null;
                            $freinRequest['etat']                 = 1;
                            $freinRequest['avance']               = str_contains($request->frein, 'AVANCE')?$request->avance:null;
                            $freinRequest['selectedTranches']     = str_contains($request->frein, 'TRANCHE')?$request->tranches:"";
                            //-1 for 0 si on selection just le 0
                            $freinRequest['selectedEtages'] = ($request->etages == "0") ? -100 : (str_contains($request->frein, 'ETAGE') ? $request->etages : "");
                            $freinRequest['selectedOrientations'] = str_contains($request->frein, 'ORIENTATION')?$request->orientations:"";
                            $freinRequest['selectedTypologies']   = str_contains($request->frein, 'TYPOLOGIE')?$request->typologies:"";
                            $freinRequest['selectedVues']         = str_contains($request->frein, 'VUE')?$request->vues:"";
                            $freinRequest['description_autre']    = str_contains($request->frein, 'AUTRE')?$request->description_autre:"";

                            $freinController = new FreinController();
                            if (! $frein_id->isEmpty()) {

                                $freinController->update(new UpdateFreinRequest($freinRequest), $frein_id->value('id'));
                            } else {

                                $freinRequest['visite_id'] = $visite->id;
                            $freinController->store(new StoreFreinRequest($freinRequest));

                            }
                            } else {

                                $frein = Frein::on('temp')->where('visite_id', $id)->get();
                                if (! $frein->isEmpty()) {
                                    $freinController = new FreinController();
                                    $freinController->destroy($frein->value('id'));
                                }
                            }


                            // Store history with all changes
                        if (!empty($changes)) {
                            $visite->historique_modification=json_encode($changes);
                            $visite->save();
                        }
                // Commit transaction if everything is successful
                    DB::connection('temp')->commit();

                    return response()->json(['success' => 'Visite created successfully'], 200);

                } catch (\Exception $e) {
                    // Rollback transaction on error
                    DB::connection('temp')->rollBack();

                    \Log::error("Visite creation failed: " . $e->getMessage());
                    return response()->json(['error' => 'Visite creation failed: ' . $e->getMessage()], 500);
                }

            }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        DatabaseHelper::Config();
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $visite = Visite::on('temp')->findOrFail($id);

            //appels
            $t_appel           = new AppelController();
            $traitement_appels = TraitementAppel::on('temp')->where('visite_id', $id)->get();
            if (count($traitement_appels) > 0) {
                foreach ($traitement_appels as $tr) {
                    $t_appel->destroy_t_appel($tr->id, 1);
                }
            }
            //historique bien
            $histo_b = HistoriqueBien::on('temp')->where('visite_id', $id)->get();
            if (count($histo_b) > 0) {
                foreach ($histo_b as $h) {
                    $h->delete();
                }
            }
            $pre = PreReservation::on('temp')->where('visite_id', $id)->get();
            if (count($pre) > 0) {
                foreach ($pre as $p) {
                    $p->delete();
                }
            }

            if ($visite->interet == InteretEnum::Intéressé->value) {
                if ($visite->bien_id) {
                    Bien_Helper::libererBien($visite->bien_id, null, null);
                }
            }
            if ($visite->interet == InteretEnum::Perdu->name) {
                $frein           = Frein::on('temp')->where('visite_id', $visite->id)->get();
                $freinController = new FreinController();
                $freinController->destroy($frein->id);
            }
            //relance_rdv
            $relance_rdv = Relance_Rdv_Visite::on('temp')->where('visite_id', $id)->get();
            if (count($relance_rdv) > 0) {
                foreach ($relance_rdv as $r) {
                    $r->delete();
                }
            }
            //notifications
            $notif = new NotificationController();
            $notif->destory_force_by_column_id('visite', $id);

            //set show=1 to first related visite
            if ($visite->show == 1) {
                $related_visites = Visite::on('temp')->where('show', null)->where('related_show_id', $visite->related_show_id)->orderBy('created_at', 'ASC')->get();
                if (count($related_visites) > 0) {
                    foreach ($related_visites as $key => $rel_v) {
                        if ($key == 0) {
                            $rel_v->show = 1;
                            $rel_v->save();
                        }
                    }
                }
            }

            if ($visite->delete()) {
                return response()->json(['message' => 'Visite supprimée avec succès.'], 200);
            } else {
                return response()->json(['error' => "La visite n'a pas été supprimée."], 404);
            }

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }
    //store n visite
    public function store_n_visite($id, Store_n_VisiteRequest $request)
    {

        DatabaseHelper::Config();
        Config::set('broadcasting.default', 'pusher_3');
        $originalVisite = Visite::on('temp')->find($id);
        if (! $originalVisite) {return response()->json(['error' => "L'original de la visite n'a pas été trouvé."]);}

        $user = Auth::user();
        DB::connection('temp')->beginTransaction();
        $last_origin_id_prospect=$request->last_origin_id_of_prospect;

        $origin=($last_origin_id_prospect !="null" && $last_origin_id_prospect != null)
                                        ? $last_origin_id_prospect
                                        : $id;
        if (RoleHelper::ACSup()) {
            try {
            //si interet on store cin du client
            if($request->prospect_id!=null){
                $prospect = Prospect::on('temp')->findorfail($request->prospect_id);
                if ($request->cin != null) {
                        $prospect->cin = $request->cin;
                        $prospect->save();
                }
            }
            //get origin id of the last prospect

            $list_bien_interesse       = json_decode($request->input('list_bien_interesse', '[]'), true);
            $list_bien_transfere_vendu = json_decode($request->input('list_bien_transfere_vendu', '[]'), true);
            $last_number               = null;
            $visite_exist              = Visite::on('temp')->where('origin_id', $origin)->where('etat', 1)->orderBy('created_at', 'DESC')->first();
            if ($visite_exist != null) {
                $last_number = intval(explode(" ", $visite_exist->description)[2]);
            }
            //receptif ou perdu
            if ($request->interet == InteretEnum::Réceptif->value || $request->interet == InteretEnum::Perdu->value) {
                //rendre bien disponible si interet!=Intéressé ==>Réceptif ou Perdu
                if ($list_bien_interesse) {
                    foreach ($list_bien_interesse as $key => $list_biens) {
                        if ($list_biens['bien_id']) {
                            Bien_Helper::libererBien($list_biens['bien_id'], null, null);
                        }
                    }
                }
                if ($list_bien_transfere_vendu) {
                    foreach ($list_bien_transfere_vendu as $key => $list_biens_ve) {
                        if ($list_biens_ve['bien_id']) {
                            Bien_Helper::libererBien($list_biens_ve['bien_id'], null, null);
                        }
                    }
                }
                //store n visite
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                $newVisit = new Visite();
                $newVisit->setConnection('temp');
                $newVisit->user_id     = $userAuth->value('id');
                $newVisit->prospect_id = $request->prospect_id;
                $newVisit->projet_id   = $request->selectedProjet;
                $newVisit->description = 'CREATION VISITE ' . ($last_number + 1);
                $newVisit->origin_id = $origin;
                $newVisit->commentaire = $request->commentaire;
                $newVisit->interet     = $request->interet;
                $newVisit->show        = 1;
                if ($newVisit->save()) {
                    $newVisit->related_show_id = $newVisit->id;
                    //store relances et rdv et notifications du new visite
                    if ($newVisit->save()) {
                        if ($newVisit->interet == InteretEnum::Réceptif->value) {
                            if ($request->date_relance != null) {

                                $data_notif = [
                                    'lien'        => '/crm/visites/' . $newVisit->origin_id,
                                    'date'        => $request->date_relance,
                                    'type'        => 1,
                                    'description' => 'RELANCE VISITE',
                                    'user_id'     => Auth::guard('api')->user()->id,
                                    'role'        => null,
                                    'visite_id'   => $newVisit->getAttribute('id'),
                                    'prospect_id' => $newVisit->prospect_id,
                                    'projet_id'   => $newVisit->projet_id,

                                ];
                                $notif_helper = new NotificationHelper();
                                $notif_helper->storeNotification($request->merge($data_notif));

                                broadcast(new NotificationEvent($newVisit->id));

                                $relance = new Relance_Rdv_Visite();
                                $relance->setConnection('temp');
                                $relance->type            = 1; //relance
                                $relance->mode_relance    = $request->mode_relance;
                                $relance->date_relance    = $request->date_relance;
                                $relance->type_traitement = 0; //0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                                $relance->user_id         = $userAuth->value('id');
                                $relance->visite_id       = $newVisit->id;
                                $relance->save();
                            }
                        }
                        $old_visites = Visite::on('temp')->where('origin_id', $origin)->where('id', '!=', $newVisit->id)->orderBy('created_at', 'DESC')->get();
                        if (count($old_visites) > 0) {
                            foreach ($old_visites as $old_visite) {
                                //si le n visite receptif || perdu
                                if ($newVisit->interet == InteretEnum::Réceptif->value || $newVisit->interet == InteretEnum::Perdu->value) {
                                    //Si lors de l'ancienne visite le client a préreservé==>libérer le bien et supprimer les relances
                                    if ($old_visite->statut == StatutVisiteEnum::Pré_Réservation->value) {
                                        $oldBien = Bien::on('temp')->find($old_visite->bien_id);
                                        if ($oldBien->etat == 'Pré_Réservation' && $oldBien->historique_bien_pre_reserve->visite_id == $old_visite->id) {
                                            Bien_Helper::libererBien($old_visite->bien_id, null, null);
                                            if ($old_visite->statut == StatutVisiteEnum::Pré_Réservation->value) {
                                                $old_visite->statut = StatutVisiteEnum::Pré_Réservation_Perdu->value;
                                            } elseif ($old_visite->statut == StatutVisiteEnum::Vendu->value) {
                                                $old_visite->statut = StatutVisiteEnum::Réservation_Perdu->value;
                                            }
                                            $old_visite->save();
                                        }
                                    }
                                }

                                //SUPPRIMER LES OLDS NOTIF
                                $notif_old_relance = Notification::on('temp')->where(function ($query) {
                                    $query->where('type', 1)
                                        ->orwhere('type', 2);
                                })
                                    ->where(function ($query_2) use ($old_visite) {
                                        $query_2->where('visite_id', $old_visite->id);
                                    })
                                    ->get();
                                if (($notif_old_relance->count()) > 0) {
                                    foreach ($notif_old_relance as $nt_r) {
                                        $nt_r->delete();
                                    }
                                }
                                /***RENDRE LES OLD RELANCES ET OLD RDV EN TRAITE AUTOMATIQUE****/
                                $old_relances_rdv = Relance_Rdv_Visite::on('temp')->where('visite_id', $old_visite->id)->where('type_traitement', 0)->get();
                                if (count($old_relances_rdv) > 0) {
                                    foreach ($old_relances_rdv as $old) {
                                        $old->type_traitement = 2; //auto
                                        $old->date_traitement = Carbon::now();
                                        //si old visite pre reserve en suite n visite Vendu ==>user_id_traite(l'ancien user)
                                        if ($old->visite->statut == StatutVisiteEnum::Pré_Réservation->value) {
                                            if ($newVisit->statut == StatutVisiteEnum::Vendu->value) {
                                                $old->user_id_traite = $old_visite->user_id;
                                            } else {
                                                $old->user_id_traite = $userAuth->value('id');
                                            }
                                        } else {
                                            $old->user_id_traite = $userAuth->value('id');
                                        }
                                        $old->save();
                                    }

                                }
                            }
                        }
                        if ($newVisit->interet == InteretEnum::Perdu->value) {
                            $freinRequest['visite_id']            = $newVisit->getAttribute('id');
                            $freinRequest['prix_min']             = str_contains($request->frein, 'prix')?$request->prix_min:null;
                            $freinRequest['freins']               = $request->frein;
                            $freinRequest['prix_max']             = str_contains($request->frein, 'prix')?$request->prix_max:null;
                            $freinRequest['sup_min']              = str_contains($request->frein, 'superficie')?$request->sup_min:null;
                            $freinRequest['sup_max']              = str_contains($request->frein, 'superficie')?$request->sup_max:null;
                            $freinRequest['etat']                 = 1;
                            $freinRequest['avance']               = str_contains($request->frein, 'avance')?$request->avance:null;
                            $freinRequest['selectedTranches']     = str_contains($request->frein, 'tranche')?$request->tranches:"";
                            //-1 for 0 si on selection just le 0
                            $freinRequest['selectedEtages'] = ($request->etages == "0") ? -100 : (str_contains($request->frein, 'etage') ? $request->etages : "");
                            $freinRequest['selectedOrientations'] = str_contains($request->frein, 'orientation')?$request->orientations:"";
                            $freinRequest['selectedTypologies']   = str_contains($request->frein, 'typologie')?$request->typologies:"";
                            $freinRequest['selectedVues']         = str_contains($request->frein, 'vue')?$request->vues:"";
                            $freinRequest['description_autre']    = str_contains($request->frein, 'autre')?$request->description_autre:"";

                            $freinController = new FreinController();
                            $freinController->store(new StoreFreinRequest($freinRequest));
                        }
                    }

                }
            } else {
                //interesse
                $first_v_id = 0;
                $array_v_id = [];

                if ($list_bien_interesse != null) {
                    //list des biens interesse
                    foreach ($list_bien_interesse as $key => $list_biens) {
                        //test si le user connecte celui qui a  fait la proposition // bien pre reserve par autre
                        if ($list_biens['bien_id'] != null && $request->interet == InteretEnum::Intéressé->value) {
                            $bien_prop = Bien::on('temp')->findorfail($list_biens['bien_id']);
                            if ($bien_prop->etat != 'DISPONIBLE') {
                                if ($bien_prop->etat == 'ENCOURS_DE_PROPOSITION') {
                                    //test si le user connecte celui qui a  fait la proposition
                                    if ($bien_prop->is_proposed->user_id != Auth::guard('api')->user()->id) {
                                        return response()->json(['error_33' => 'le bien choisi :' . $bien_prop->propriete_dite_bien . ' est en cours de proposition  par : ' . $bien_prop->is_proposed->user->name . ' ' . $bien_prop->is_proposed->user->prenom], 333);
                                    }
                                } else {
                                    //bien !=encours proposition ==>pre reserve
                                    return response()->json(['error_33' => 'le bien choisi :' . $bien_prop->propriete_dite_bien . ' est ' . $bien_prop->etat], 333);
                                }
                            }
                        }
                        //store n visite

                        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                        $newVisit = new Visite();
                        $newVisit->setConnection('temp');
                        $newVisit->user_id     = $userAuth->value('id');
                        $newVisit->prospect_id = $request->prospect_id;
                        $newVisit->projet_id   = $request->selectedProjet;
                        $newVisit->description = 'CREATION VISITE ' . ($last_number + 1);
                        $newVisit->origin_id = $origin;
                        $newVisit->commentaire = $request->commentaire;
                        $newVisit->interet     = $request->interet;
                        $newVisit->bien_id     = $list_biens['bien_id'];
                        $newVisit->statut      = $list_biens['statut'];

                        if ($newVisit->save()) {
                            //push les vistes_id to array pour supprimer les relances where id not int array_v_id
                            array_push($array_v_id, $newVisit->id);
                            //first visite bien==>show=1 et related_sho meme id du visite
                            if ($key == 0) {
                                $newVisit->related_show_id = $newVisit->id;
                                $first_v_id                = $newVisit->id;
                                $newVisit->show            = 1;
                            } else {
                                $newVisit->related_show_id = $first_v_id;
                            }

                            if ($newVisit->save()) {
                                //STORE HISTORIQUE DU BIEN
                                if ($list_biens['bien_id'] != null) {
                                    if ($newVisit->statut == StatutVisiteEnum::Vendu->value) {
                                        HistoriqueBienHelper::createHistoriqueBien(5, "Creation visite Vendu du client :" . $prospect->cin . ' ' . $prospect->nom . ' ' . $prospect->prenom, $newVisit->bien_id, Auth::guard('api')->user()->id, $newVisit->id, null, null, null);
                                    } else if ($newVisit->statut == StatutVisiteEnum::Pré_Réservation->value) {
                                        HistoriqueBienHelper::createHistoriqueBien(5, "Creation visite pré reservé du client :" . $prospect->cin . ' ' . $prospect->nom . ' ' . $prospect->prenom, $newVisit->bien_id, Auth::guard('api')->user()->id, $newVisit->id, null, null, null);
                                    }
                                }
                                //store relances et rdv et notifications
                                if ($newVisit->statut == StatutVisiteEnum::Pré_Réservation->value) {
                                    if ($list_biens['date_relance'] != null) {
                                        $data_notif = [
                                            'lien'        => '/crm/visites/' . $newVisit->origin_id,
                                            'date'        => $list_biens['date_relance'],
                                            'type'        => 1,
                                            'description' => 'RELANCE VISITE',
                                            'user_id'     => Auth::guard('api')->user()->id,
                                            'role'        => null,
                                            'visite_id'   => $newVisit->getAttribute('id'),
                                            'prospect_id' => $newVisit->prospect_id,
                                            'projet_id'   => $newVisit->projet_id,

                                        ];
                                        $notif_helper = new NotificationHelper();
                                        $notif_helper->storeNotification($request->merge($data_notif));

                                        broadcast(new NotificationEvent($newVisit->id));
                                        $relance = new Relance_Rdv_Visite();
                                        $relance->setConnection('temp');
                                        $relance->type            = 1; //relance
                                        $relance->mode_relance    = $list_biens['mode_relance'];
                                        $relance->date_relance    = $list_biens['date_relance'];
                                        $relance->type_traitement = 0; //0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                                        $relance->user_id         = $userAuth->value('id');
                                        $relance->visite_id       = $newVisit->id;
                                        $relance->save();
                                    }
                                    if ($list_biens['rdv'] != null) {
                                        $data_notif = [
                                            'lien'        => '/crm/visites/'. $newVisit->origin_id,
                                            'date'        => $list_biens['rdv'],
                                            'type'        => 2,
                                            'description' => 'RDV VISITE',
                                            'user_id'     => Auth::guard('api')->user()->id,
                                            'role'        => null,
                                            'visite_id'   => $newVisit->getAttribute('id'),
                                            'prospect_id' => $newVisit->prospect_id,
                                            'projet_id'   => $newVisit->projet_id,

                                        ];
                                        $notif_helper = new NotificationHelper();
                                        $notif_helper->storeNotification($request->merge($data_notif));

                                        broadcast(new NotificationEvent($newVisit->id));

                                        $rdv = new Relance_Rdv_Visite();
                                        $rdv->setConnection('temp');
                                        $rdv->type            = 2; //rdv
                                        $rdv->rdv             = $list_biens['rdv'];
                                        $rdv->type_traitement = 0; //0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                                        $rdv->user_id         = $userAuth->value('id');
                                        $rdv->visite_id       = $newVisit->id;
                                        $rdv->save();
                                    }
                                }

                                //store code pre reserve to table ==>PreReservation
                                if ($newVisit->interet == InteretEnum::Intéressé->value && $newVisit->statut == StatutVisiteEnum::Pré_Réservation->value) {
                                    $bien_c = new BienController();
                                    $bien_c->prereserverBien($newVisit->bien_id, $newVisit->id, null, null);

                                } elseif ($newVisit->interet == InteretEnum::Intéressé->value && $newVisit->statut == StatutVisiteEnum::Vendu->value) {
                                    //store visite vendu

                                    $reservationController = new ReservationController();
                                    $reservationRequest    = new StoreReservationRequest();

                                    $dataReservation = [
                                        'nb_acquereurs'        => 1,
                                        'code_reservation'     => $list_biens['code_reservation'],
                                        'prix'                 => $list_biens['prix'],
                                        'mode_financement'     => $list_biens['mode_financement'],
                                        'date_reservation'     => $list_biens['date_reservation'],
                                        'commentaire'          => $list_biens['commentaire_res'],
                                        'visite_id'            => $newVisit->id,
                                        'prix_remise'          => $list_biens['prix_remise'],
                                        'prix_forfetaire'      => $list_biens['prix_forfetaire'],
                                        'bien_id'              => $list_biens['bien_id'],
                                        'projet_id'            => $request->selectedProjet,
                                        'verifierPourcentages' => true,
                                        'cin'                  => $prospect->cin,
                                        'nom'                  => $prospect->nom,
                                        'prenom'               => $prospect->prenom,
                                        'telephone_num1'       => $prospect->telephone,
                                        'telephone_num2'       => $prospect->telephone_num2,
                                        'ville'                => $prospect->ville,
                                        'notifie'              => $prospect->notifie,
                                        'prospect_id'          => $prospect->id,
                                        'civilite'             => '1',
                                        'type_client'          => 1,
                                        'situation_familliale' => 1,
                                        'sr'                   => $list_biens['sr'],
                                        'type_encaissement'    => 1,
                                        'avance'               => $list_biens['avance_res'],
                                        'mode_paiement'        => $list_biens['mode_paiement'],
                                        'numero_paiement'      => $list_biens['numero_paiement'],
                                        'date_reglement'       => $list_biens['date_reglement'],
                                        'echeance'             => $list_biens['echeance'],
                                        'banque_id'            => $list_biens['banque_id'],
                                        'commentaireAvance'    => $list_biens['commentaireAvance'],
                                        'num_remise'           => $list_biens['num_remise'],
                                        'date_encaissement'    => $list_biens['date_encaissement'],
                                        'files_avance'         => $list_biens['selectedFiles_avc'],


                                    ];
                                    $reservationRequest->merge($dataReservation);
                                    $reservationController->store($reservationRequest);

                                }
                            }
                        }
                    }
                }

                //list des bien transfere vendu
                if ($list_bien_transfere_vendu != null) {
                    //list des biens interesse
                    foreach ($list_bien_transfere_vendu as $key => $list_biens) {
                        //store n visite
                        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                        $newVisit = new Visite();
                        $newVisit->setConnection('temp');
                        $newVisit->user_id     = $userAuth->value('id');
                        $newVisit->prospect_id = $request->prospect_id;
                        $newVisit->projet_id   = $request->selectedProjet;
                        $newVisit->description = 'CREATION VISITE ' . ($last_number + 1);
                        $newVisit->origin_id = $origin;
                        $newVisit->commentaire = $request->commentaire;
                        $newVisit->interet     = $request->interet;
                        $newVisit->bien_id     = $list_biens['bien_id'];
                        $newVisit->statut      = $list_biens['statut'];

                        if ($newVisit->save()) {
                            //push les vistes_id to array pour supprimer les relances where id not int array_v_id
                            array_push($array_v_id, $newVisit->id);
                            // related_sho meme id du visite
                            if ($list_bien_interesse == null) {
                                if ($key == 0) {
                                    $newVisit->related_show_id = $newVisit->id;
                                    $first_v_id                = $newVisit->id;
                                    $newVisit->show            = 1;
                                } else {
                                    $newVisit->related_show_id = $first_v_id;
                                }
                            } else {
                                $newVisit->related_show_id = $first_v_id;
                            }

                            if ($newVisit->save()) {
                                //STORE HISTORIQUE DU BIEN
                                if ($list_biens['bien_id'] != null) {
                                    if ($newVisit->statut == StatutVisiteEnum::Vendu->value) {
                                        HistoriqueBienHelper::createHistoriqueBien(5, "Creation visite Vendu du client :" . $prospect->cin . ' ' . $prospect->nom . ' ' . $prospect->prenom, $newVisit->bien_id, Auth::guard('api')->user()->id, $newVisit->id, null, null, null);
                                    }
                                }

                                if ($newVisit->interet == InteretEnum::Intéressé->value && $newVisit->statut == StatutVisiteEnum::Vendu->value) {
                                    //store visite vendu

                                    $reservationController = new ReservationController();
                                    $reservationRequest    = new StoreReservationRequest();

                                    $dataReservation = [
                                        'nb_acquereurs'        => 1,
                                        'code_reservation'     => $list_biens['code_reservation'],
                                        'prix'                 => $list_biens['prix'],
                                        'mode_financement'     => $list_biens['mode_financement'],
                                        'date_reservation'     => $list_biens['date_reservation'],
                                        'commentaire'          => $list_biens['commentaire_res'],
                                        'visite_id'            => $newVisit->id,
                                        'prix_remise'          => $list_biens['prix_remise'],
                                        'prix_forfetaire'      => $list_biens['prix_forfetaire'],
                                        'bien_id'              => $list_biens['bien_id'],
                                        'projet_id'            => $request->selectedProjet,
                                        'verifierPourcentages' => true,
                                        'origin'               => 'visite',
                                        'cin'                  => $prospect->cin,
                                        'nom'                  => $prospect->nom,
                                        'prenom'               => $prospect->prenom,
                                        'telephone_num1'       => $prospect->telephone,
                                        'telephone_num2'       => $prospect->telephone_num2,
                                        'ville'                => $prospect->ville,
                                        'notifie'              => $prospect->notifie,
                                        'prospect_id'          => $prospect->id,
                                        'civilite'             => '1',
                                        'type_client'          => 1,
                                        'situation_familliale' => 1,
                                        'sr'                   => $list_biens['sr'],
                                        'type_encaissement'    => 1,
                                        'avance'               => $list_biens['avance_res'],
                                        'mode_paiement'        => $list_biens['mode_paiement'],
                                        'numero_paiement'      => $list_biens['numero_paiement'],
                                        'date_reglement'       => $list_biens['date_reglement'],
                                        'echeance'             => $list_biens['echeance'],
                                        'banque_id'            => $list_biens['banque_id'],
                                        'commentaireAvance'    => $list_biens['commentaireAvance'],
                                        'num_remise'           => $list_biens['num_remise'],
                                        'date_encaissement'    => $list_biens['date_encaissement'],
                                        'files_avance'         => $list_biens['selectedFiles_avc'],

                                    ];
                                    $reservationRequest->merge($dataReservation);
                                    $reservationController->store($reservationRequest);

                                }
                                //set old visite to pre _reservation_vendu
                                if (isset($list_biens['visite_id'])) {
                                    $old_visite_transfere         = Visite::on('temp')->findorfail($list_biens['visite_id']);
                                    $old_visite_transfere->statut = StatutVisiteEnum::Pré_Réservation_Vendu->value;
                                    $old_visite_transfere->save();
                                } else if (isset($list_biens['traitement_frein_id'])) {
                                    $t_f         = TraitementFrein::on('temp')->findorfail($list_biens['traitement_frein_id']);
                                    $t_f->statut = StatutVisiteEnum::Pré_Réservation_Vendu->value;
                                    $t_f->save();
                                }

                            }
                        }
                    }
                }
                //traite/supprimer les relances rdv des old visite=>automatique

                $old_visites = Visite::on('temp')->where('origin_id', $origin)->whereNotIn('id', $array_v_id)->orderBy('created_at', 'DESC')->get();
                if (count($old_visites) > 0) {

                    foreach ($old_visites as $old_visite) {

                        //SUPPRIMER LES OLDS NOTIF
                        $notif_old_relance = Notification::on('temp')->where(function ($query) {
                            $query->where('type', 1)
                                ->orwhere('type', 2);
                        })
                            ->where(function ($query_2) use ($old_visite) {
                                $query_2->where('visite_id', $old_visite->id);
                            })
                            ->get();
                        if (($notif_old_relance->count()) > 0) {
                            foreach ($notif_old_relance as $nt_r) {
                                $nt_r->delete();
                            }
                        }
                        /***RENDRE LES OLD RELANCES ET OLD RDV EN TRAITE AUTOMATIQUE****/
                        $old_relances_rdv = Relance_Rdv_Visite::on('temp')->where('visite_id', $old_visite->id)->where('type_traitement', 0)->get();
                        if (count($old_relances_rdv) > 0) {
                            foreach ($old_relances_rdv as $old) {
                                $old->type_traitement = 2; //auto
                                $old->date_traitement = Carbon::now();
                                $old->user_id_traite  = $userAuth->value('id');
                                $old->save();
                            }
                        }
                    }
                }
            }

            //Si un CIN existe déjà pour un autre prospect, on associe toutes les visites du nouveau prospect à l'ancien et on supprime le nouveau prospect
            if ($origin != $id) {
                // Get all visits with the current origin_id
                $visits = Visite::on('temp')->where('origin_id', $id)->get();

                // Get the original prospect ID from the first visit (if exists)
                $originalProspectId = $visits->first()?->prospect_id;

                if ($originalProspectId) {
                    // Update all visits without mass assignment
                    $visits->each(function ($visit) use ($origin, $request) {
                        $visit->origin_id = $origin;
                        $visit->prospect_id = $request->prospect_id;
                        $visit->save();
                    });

                    // Get the prospect to be merged and deleted
                    $prospectToMerge = Prospect::on('temp')->with(['appels', 'client'])->find($originalProspectId);

                    if ($prospectToMerge) {
                        // Update all calls
                        if( count($prospectToMerge->all_appels)>0){
                            $prospectToMerge->appels->each(function ($call) use ($request) {
                            $call->prospect_id = $request->prospect_id;
                            $call->save();
                        });
                        }


                        // Update client if exists
                        if ($prospectToMerge->client!=null) {
                            $prospectToMerge->client->prospect_id = $request->prospect_id;
                            $prospectToMerge->client->save();
                        }

                        // Delete the prospect
                        $prospectToMerge->delete();
                    }
                }
            }
            // Commit transaction if everything is successful
            DB::connection('temp')->commit();

            return response()->json(['success' => 'Visite created successfully'], 200);
             } catch (\Exception $e) {
            // Rollback transaction on error
            DB::connection('temp')->rollBack();

            \Log::error("Visite creation failed: " . $e->getMessage());
            return response()->json(['error' => 'Visite creation failed: ' . $e->getMessage()], 500);
             }


        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function getAllAttributes()
    {
        $tranches   = Tranche::where('projet_id', Session::get('projet_id'))->get();
        $etages     = Tranche::where('projet_id', Session::get('projet_id'))->max('niveau_etage')->value();
        $blocs      = Bloc::where('projet_id', Session::get('projet_id'))->get();
        $immeubles  = Immeuble::where('projet_id', Session::get('projet_id'))->get();
        $biens      = Bien::where([['projet_id', Session::get('projet_id')], ['etat', EtatBien::DISPONIBLE->name]])->get();
        $typologies = Typologie::where('projet_id', Session::get('projet_id'))->get();
        $vues       = Vue::where('projet_id', Session::get('projet_id'))->get();
        $formData   = [
            'tranches'   => $tranches,
            'etages'     => $etages,
            'blocs'      => $blocs,
            'immeubles'  => $immeubles,
            'biens'      => $biens,
            'typologies' => $typologies,
            'vues'       => $vues,
        ];

        return response()->json($formData);
    }

    public function get_relances_rdv_visites(Request $request, $projet_id)
    {

        if (Auth::guard('api')->check()) {
            // Default values for pagination null si non pas envoyer avec la raquete
            $size = $request->input('size', null);
            $page = $request->input('page', null);

            DatabaseHelper::Config();
            $user     = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $query    = Relance_Rdv_Visite::on('temp')->with('visite')
                ->where('type', $request->type)->where('type_traitement', 0)
                ->whereHas('visite', function ($q) use ($projet_id) {
                    $q->where('projet_id', $projet_id)->where('etat', 1);
                });
            if (! RoleHelper::AdminSup()) {
                $query->where('user_id', $userAuth->value('id'));
            }
            if ($request->type == 1) {
                $query->whereDate('date_relance', '<=', Carbon::now());
            } else {
                $query->whereDate('rdv', '<=', Carbon::now());
            }
            if ($request->filled('nom_prenom')) {
                $query->whereHas('visite.prospect', function ($q) use ($request) {
                    $q->where('nom', 'like', '%' . $request->input('nom_prenom') . '%')
                        ->orWhere('prenom', 'like', '%' . $request->input('nom_prenom') . '%');
                });
            }
            if ($request->filled('cin')) {
                $query->whereHas('visite.prospect', function ($q) use ($request) {
                    $q->where('cin', 'like', '%' . $request->input('cin') . '%');
                });
            }
            if ($request->filled('telephone')) {
                $query->whereHas('visite.prospect', function ($q) use ($request) {
                    $q->where('telephone', 'like', '%' . $request->input('telephone') . '%')
                        ->orWhere('telephone_num2', 'like', '%' . $request->input('telephone') . '%');
                });
            }
            if ($request->filled('mode_relance')) {
                $query->where('mode_relance', $request->input('mode_relance'));
            }

            if ($request->filled('date_relance')) {
                $start = Carbon::parse($request->input('date_relance'));
                $query->whereDate('date_relance', $start);
            }
            if ($request->filled('rdv')) {
                $start = Carbon::parse($request->input('rdv'));
                $query->whereDate('rdv', $start);
            }

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {

                $relances = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                $pagination = [
                    'currentPage' => $relances->currentPage(),
                    'totalItems'  => $relances->total(),
                    'totalPages'  => $relances->lastPage(),
                ];

                $relances = $relances->items();

                return response()->json([
                    'data'       => $relances,
                    'pagination' => $pagination,
                ], 200);
            } else {
                $relances = $query->orderBy('created_at', 'desc')
                    ->get();
                return response()->json(['data' => $relances]);

            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

}
