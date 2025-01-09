<?php

namespace App\Http\Controllers\Api\V1;
use App\Events\NotifMenuEvent;
use App\Enum\EtatBien;
use App\Events\PropositionUpdated;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Bien_Helper;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\HistoriqueBienHelper;
use App\Http\Helpers\PaginationHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreBienRequest;
use App\Http\Requests\UpdateBienRequest;
use App\Models\Bien;
use App\Models\Bloc;
use App\Models\Frein;
use App\Models\Frein_Bien;
use App\Models\HistoriqueBien;
use App\Models\Immeuble;
use App\Models\PreReservation;
use App\Models\Proposition;
use App\Models\Tranche;
use App\Models\Remboursement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use App\Http\Helpers\NotificationHelper;
use App\Events\NotificationEvent;
use Carbon\Carbon;
use App\Enum\RoleEnum;
use Illuminate\Support\Facades\DB;

class BienController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', config('app.default_item_number_perpage'));
            $page = $request->input('page', 1);
            $projet_id = $request->input('projet_id');
            DatabaseHelper::Config();

            $query = Bien::on('temp');

            if ($projet_id) {
                $query->where('projet_id', $projet_id);
            }

            if ($request->filled('propriete_dite_bien')) {
                $query->where('propriete_dite_bien', 'like', '%' . $request->input('propriete_dite_bien') . '%');
            }

            if ($request->filled('niveau')) {
                $query->where('niveau', 'like', '%' . $request->input('niveau') . '%');
            }
            if ($request->filled('orientation')) {
                $query->where('orientation', 'like', '%' . $request->input('orientation') . '%');
            }

            if ($request->filled('etat')) {
                $query->where('etat', 'like', '%' . $request->input('etat') . '%');
            }
            if ($request->filled('prix_min')) {
                $query->where('prix', '>=', $request->input('prix_min'));
            }

            if ($request->filled('prix_max')) {
                $query->where('prix', '<=', $request->input('prix_max'));
            }

            if ($request->filled('superficie_min')) {
                $query->where('superficie_habitable', '>=', $request->input('superficie_min'));
            }

            if ($request->filled('superficie_max')) {
                $query->where('superficie_habitable', '<=', $request->input('superficie_max'));
            }
            if ($request->filled('tranche')) {
                $query->whereHas('tranche', function ($subQuery) use ($request) {
                    $subQuery->where('nom', $request->input('tranche'));
                });
            }
            if ($request->filled('bloc')) {
                $query->whereHas('bloc', function ($subQuery) use ($request) {
                    $subQuery->where('nom', $request->input('bloc'));
                });
            }
            if ($request->filled('immeuble')) {
                $query->whereHas('immeuble', function ($subQuery) use ($request) {
                    $subQuery->where('nom', $request->input('immeuble'));
                });
            }
            if ($request->filled('type')) {
                $query->whereHas('typeBien', function ($subQuery) use ($request) {
                    $subQuery->where('type', $request->input('type'));
                });
            }

            if ($request->filled('vue')) {
                $query->whereHas('vue', function ($subQuery) use ($request) {
                    $subQuery->where('vue', $request->input('vue'));
                });
            }
            if ($request->filled('typologie')) {
                $query->whereHas('typologie', function ($subQuery) use ($request) {
                    $subQuery->where('typologie', $request->input('typologie'));
                });
            }
            $biens = $query->orderBy('created_at', 'desc')
                ->paginate($size, ['*'], 'page', $page);

            $pagination = [
                'currentPage' => $biens->currentPage(),
                'totalItems' => $biens->total(),
                'totalPages' => $biens->lastPage(),
            ];

            $biens = $biens->items();

            return response()->json([
                'data' => $biens,
                'pagination' => $pagination,
            ], 200);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function indexByProjet(Request $request, $projet_id)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', null);
            $page = $request->input('page', null);
            DatabaseHelper::Config();

            $query = Bien::on('temp')->where('projet_id', $projet_id)->with(['reservation'])->with(['last_pre_reservation']);

            // Appliquer les filtres si présents
            if ($request->filled('propriete_dite_bien')) {
                $query->where('propriete_dite_bien', 'like', '%' . $request->input('propriete_dite_bien') . '%');
            }

            if ($request->filled('niveau')) {
                $query->where('niveau', 'like', '%' . $request->input('niveau') . '%');
            }
            if ($request->filled('orientation')) {
                $query->where('orientation', 'like', '%' . $request->input('orientation') . '%');
            }

            if ($request->filled('etat')) {
                $query->where('etat', 'like', '%' . $request->input('etat') . '%');
            }
            if ($request->filled('etat_bien') && $request->input('etat_bien')!="null" ) {
                $query->where('etat',  $request->input('etat_bien') );
            }
            if ($request->filled('prix_min')) {
                $query->where('prix', '>=', $request->input('prix_min'));
            }

            if ($request->filled('prix_max')) {
                $query->where('prix', '<=', $request->input('prix_max'));
            }

            if ($request->filled('superficie_min')) {
                $query->where('superficie_habitable', '>=', $request->input('superficie_min'));
            }

            if ($request->filled('superficie_max')) {
                $query->where('superficie_habitable', '<=', $request->input('superficie_max'));
            }
            if ($request->filled('tranche_id')) {
                $query->where('tranche_id', $request->input('tranche_id'));
            }
            if ($request->filled('bloc_id')) {
                $query->where('bloc_id', $request->input('bloc_id'));
            }
            if ($request->filled('immeuble_id')) {
                $query->where('immeuble_id', $request->input('immeuble_id'));
            }
            if ($request->filled('tranche')) {
                $query->whereHas('tranche', function ($subQuery) use ($request) {
                    $subQuery->where('nom', $request->input('tranche'));
                });
            }
            if ($request->filled('bloc')) {
                $query->whereHas('bloc', function ($subQuery) use ($request) {
                    $subQuery->where('nom', $request->input('bloc'));
                });
            }
            if ($request->filled('immeuble')) {
                $query->whereHas('immeuble', function ($subQuery) use ($request) {
                    $subQuery->where('nom', $request->input('immeuble'));
                });
            }
            if ($request->filled('type')) {
                $query->whereHas('typeBien', function ($subQuery) use ($request) {
                    $subQuery->where('type', $request->input('type'));
                });
            }

            if ($request->filled('vue')) {
                $query->whereHas('vue', function ($subQuery) use ($request) {
                    $subQuery->where('vue', $request->input('vue'));
                });
            }
            if ($request->filled('typologie')) {
                $query->whereHas('typologie', function ($subQuery) use ($request) {
                    $subQuery->where('typologie', $request->input('typologie'));
                });
            }
            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {

                $biens = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                $pagination = [
                    'currentPage' => $biens->currentPage(),
                    'totalItems' => $biens->total(),
                    'totalPages' => $biens->lastPage(),
                ];

                $biens = $biens->items();
                
                $counts = DB::connection('temp')
                ->table('biens')->selectRaw("
                    etat,
                    COUNT(*) as total
                ")
                ->where('projet_id', 1)
                ->groupBy('etat')
                ->get()
                ->keyBy('etat');

                return response()->json([
                    'data' => $biens,
                    'pagination' => $pagination,
                    'counts' => $counts,
                ], 200);
            } else {
                // Return all results if pagination parameters are not provided or invalid
                $biens = $query->orderBy('created_at', 'desc')
                    ->get();

                return response()->json(['biens' => $biens], 200);
            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function biens_proposition(Request $request, $projet_id)
    {

        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);
            if (RoleHelper::AdminSup()) {
                $biens = Proposition::on('temp')->join('biens', 'biens.id', '=', 'propositions.bien_id')->latest('propositions.created_at')->where('biens.projet_id', $projet_id)->where('biens.etat', 'ENCOURS_DE_PROPOSITION')
                    ->select('propositions.*')
                    ->get()
                    ->groupby('bien_id');
                $biens = $biens->map(function ($bn) {
                    return [
                        'id' => $bn->first()->id,
                        'propriete_dite_bien' => $bn->first()->bien->propriete_dite_bien,
                        'responsable' => $bn->first()->user->name . ' ' . $bn->first()->user->prenom,
                        'created_at' => $bn->first()->created_at,
                    ];
                });
                $data = PaginationHelper::paginate_array($biens->toArray(), $perPage, $page, $request->url());
            } else {
                //commercial
                $biens = Proposition::on('temp')->join('biens', 'biens.id', '=', 'propositions.bien_id')->latest('propositions.created_at')->where('biens.projet_id', $projet_id)->where('propositions.user_id', Auth::guard('api')->user()->id)->where('biens.etat', 'ENCOURS_DE_PROPOSITION')
                    ->select('propositions.*')
                    ->get()
                    ->groupby('bien_id');
                $biens = $biens->map(function ($bn) {
                    return [
                        'id' => $bn->first()->id,
                        'propriete_dite_bien' => $bn->first()->bien->propriete_dite_bien,
                        'responsable' => $bn->first()->user->name . ' ' . $bn->first()->user->prenom,
                        'created_at' => $bn->first()->created_at,
                    ];
                });
                $data = PaginationHelper::paginate_array($biens->toArray(), $perPage, $page, $request->url());

            }
            return response()->json(['biens' => $data], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function getBiensByTranche_tva(Request $request, $projet_id)
    {

        if (Auth::guard('api')->check()) {
            // Default values for pagination null si non pas envoyer avec la raquete
            $size = $request->input('size', null);
            $page = $request->input('page', null);

            DatabaseHelper::Config();
            $query = Bien::on('temp')->with('reservation', 'Bien_tva', 'tva_collectes', 'tva_collectes_ancien_reservation')->where('projet_id', $projet_id);
            if ($request->filled('tranche_id')) {
                $query->where('tranche_id', $request->input('tranche_id'));
            }
            if ($request->filled('nom')) {
                $query->where('propriete_dite_bien', $request->input('nom'));
            }
            if ($request->filled('code_reservation')) {
                $query->whereHas('reservation', function ($q) use ($request) {
                    $q->where('code_reservation', $request->input('code_reservation'));
                });
            }
            if ($request->filled('superficie')) {
                $query->where('superficie_total', 'like', '%' . $request->input('superficie') . '%');
            }
            if ($request->filled('prix_ttc')) {
                $query->whereHas('bien_tva', function ($q) use ($request) {
                    $q->where('prix_ttc', 'like', '%' . $request->input('prix_ttc') . '%');
                });
            }
            if ($request->filled('tva')) {
                $query->whereHas('bien_tva', function ($q) use ($request) {
                    $q->where('tva', 'like', '%' . $request->input('tva') . '%');
                });
            }
            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {

                $biens = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                $pagination = [
                    'currentPage' => $biens->currentPage(),
                    'totalItems' => $biens->total(),
                    'totalPages' => $biens->lastPage(),
                ];

                $biens = $biens->items();

                return response()->json([
                    'data' => $biens,
                    'pagination' => $pagination,
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
    public function store(StoreBienRequest $request)
    {
        if (RoleHelper::AdminSup()) {

            DatabaseHelper::Config();
            $bien = new bien();
            $bien->setConnection('temp');

            $bien->propriete_dite_bien = $request->propriete_dite_bien;
            $bien->numero = $request->numero;
            $bien->niveau = $request->niveau;
            $bien->orientation = $request->orientation;
            $bien->conventionne = $request->conventionne;
            $bien->prix_unitaire = $request->prix_unitaire;
            $bien->prix = $request->prix;
            $bien->superficie_architecte = $request->superficie_architecte;
            $bien->superficie_habitable = $request->superficie_habitable;
            $bien->superficie_total = $request->superficie_total;
            $bien->superficie_vendable = $request->superficie_vendable;
            $bien->nbre_facades = $request->nbre_facades;
            $bien->superficie_parking = $request->superficie_parking;
            $bien->superficie_box = $request->superficie_box;
            $bien->superficie_terrasse = $request->superficie_terrasse;
            $bien->superficie_jardin = $request->superficie_jardin;
            $bien->superficie_jardin_calculer = $request->superficie_jardin_calculer;
            $bien->titre_foncier = $request->titre_foncier;
            $bien->avance_minimale = $request->avance_minimale;
            $bien->etat = $request->etat;
            $bien->type_id = $request->type_id;
            $bien->projet_id = $request->projet_id;
            $bien->tranche_id = $request->tranche_id;
            $bien->bloc_id = $request->bloc_id;
            $bien->immeuble_id = $request->immeuble_id;
            $bien->vue_id = $request->vue_id;
            $bien->typologie_id = $request->typologie_id;
            $bien->prix_parking = $request->prix_parking;
            $bien->num_parking = $request->num_parking;
            $bien->num_box = $request->num_box;
            $bien->prix_box = $request->prix_box;
            $bien->superficie_terrasse_calculer = $request->superficie_terrasse_calculer;
            $bien->superficie_balcon_calculer = $request->superficie_balcon_calculer;
            $bien->superficie_balcon = $request->superficie_balcon;

            if ($request->bloc_id && ($request->tranche_id === null || !$request->tranche_id)) {
                $bloc = Bloc::on('temp')->findOrfail($request->bloc_id);
                $bien->tranche_id = $bloc->tranche_id;

            }
            if ($request->immeuble_id) {
                $immeuble = Immeuble::on('temp')->findOrfail($request->immeuble_id);
                if ($request->tranche_id === null || !$request->tranche_id) {
                    $bien->tranche_id = $immeuble->tranche_id;
                }
                if ($request->bloc_id === null || !$request->bloc_id) {
                    $bien->bloc_id = $immeuble->bloc_id;
                }
            }
            if ($bien->save()) {

                if ($bien->etat == 'disponible') {
                    Bien_Helper::store_bien_frein($bien->id,null);
                }

            }

            return response()->json(['bien' => $bien], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $bien = bien::on('temp')->with('reservation', 'Bien_tva')->withSum('tva_collectes', 'tva_a_payer')->findOrfail($id);
            return response()->json(['bien' => $bien], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function libererBien_function($id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            Config::set('broadcasting.default', 'pusher_4');
            Bien_Helper::libererBien($id, null, null);
            event(new PropositionUpdated($id, null));
            return response()->json('le bien est liberé');
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $bien = bien::on('temp')->findOrfail($id);
            return response()->json(['message' => $bien], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBienRequest $request, $id)
    {

        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $bien = bien::on('temp')->findOrfail($id);
            $update = $request->all();
            foreach ($update as $key => $value) {
                $bien->$key = $value;
            }
            if ($bien->save()) {
                if ($bien->etat == 'DISPONIBLE') {
                    Bien_Helper::libererBien($bien->id, null, null);
                }
            }

            return response()->json(['message' => $bien], 200);
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
            $bien = bien::on('temp')->findOrfail($id);
            if ($bien->delete()) {
                return response()->json(['message' => 'bien deleted succesfully'], 200);
            } else {
                return response()->json(['message' => 'bien non deleted'], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }
    public function restoreBien($bien_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            Bien::on('temp')->where('id', $bien_id)->withTrashed()->restore();

            return response()->json(['message' => 'Bien restauré avec succès'], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedBiens()
    {

        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $biens = Bien::on('temp')->onlyTrashed()->get();

            return response()->json(['bien' => $biens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function bloquerBien($bien_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $bien = Bien::on('temp')->findOrFail($bien_id);
            $bien->etat = EtatBien::BLOQUE->value;
            if ($bien->save()) {
                $this->libere_bien_frein($bien->id);
            }
            HistoriqueBienHelper::createHistoriqueBien(4, "bloquer", $bien_id, Auth::guard('api')->user()->id, null, null);

            return response()->json(['message' => $bien], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function reserverBien($bien_id, $visite_id, $reservation_id)
    {
        if (RoleHelper::AdminSup()) {
            $request=new \Illuminate\Http\Request();
            DatabaseHelper::Config();
            $bien = Bien::on('temp')->findOrFail($bien_id);
            $bien->etat = EtatBien::RESERVATION->value;

            if($bien->save()){

                $action=0;
                //si bien est desisté on fait remboursement etat=1 en on envoie notification du bien desisté est vendu
                if ($bien->desistement_id!=NULL) {
                    $remboursements = Remboursement::on('temp')->where('desistement_id',$bien->desistement_id)
                    ->where('etat',0)->where('statut',0)
                    ->where(function ($query) {
                        $query->where('mode_rembourse', 'apres_vente')
                            ->orwhere('mode_rembourse', 'transfert_rem_apres_vente')
                        ;})
                    ->get();

                    foreach($remboursements as $remb){
                       // $remb->setConnection('temp');
                        $remb->etat=1;
                        $remb->save();
                        $action=1;
                    }
                    //notif menu demande pre remboursement
                    Config::set('broadcasting.default', 'pusher_5');
                    broadcast(new NotifMenuEvent(4));
                    if($action==1){
                        //to admin et commerciaux
                        Config::set('broadcasting.default', 'pusher_3');
                        $data_notif = [
                            'lien' => '/remboursements/demande',
                            'date' => Carbon::now(),
                            'type' => 19,
                            'description' => 'bien desisté est vendu',
                            'role'=>RoleEnum::ADMIN->value,
                            'projet_id'=>$bien->projet_id,
                            'bien_id'=>$bien_id,
                            'reservation_id'=>$reservation_id

                        ];
                        $notif_helper = new NotificationHelper();
                        $notif_helper->storeNotification($request->merge($data_notif));
                        broadcast(new NotificationEvent(0));

                        if( $bien->desistement->user->role==3){

                            $data_notif = [
                                'lien' => '/remboursements/demande',
                                'date' => Carbon::now(),
                                'type' => 19,
                                'description' => 'bien desisté est vendu',
                                'role'=>RoleEnum::COMMERCIAL->value,
                                'user_id'=>$bien->desistement->user->user_id_origin,
                                'projet_id'=>$bien->projet_id,
                                'bien_id'=>$bien_id,
                                'reservation_id'=>$reservation_id

                            ];
                            $notif_helper = new NotificationHelper();
                            $notif_helper->storeNotification($request->merge($data_notif));
                            broadcast(new NotificationEvent(0));

                        }

                    }
                       //on vide la column desistement_id car il est vendu et si le bien a des ancien tva on archive pour affichier tva collecte de l'ancien Reservation

                       $bien->desistement_id=null;
                       if($bien->save()){
                             //set tva collecte ancien to archive 4==>5
                             if(count($bien->tva_collectes_ancien_reservation)>0){
                               foreach($bien->tva_collectes_ancien_reservation as $t_c_a){
                                   $t_c_a->setConnection('temp');
                                   $t_c_a->delete();
                               }
                           }
                           //set tva collecte to 4
                           if(count($bien->tva_collectes)>0){
                               foreach($bien->tva_collectes as $t_c){
                                   $t_c->setConnection('temp');
                                   $t_c->etat=4;
                                   $t_c->save();
                               }
                           }


                       }
                }

                $this->libere_bien_frein($bien->id);
                HistoriqueBienHelper::createHistoriqueBien(3, "reserver", $bien_id, Auth::guard('api')->user()->id, $visite_id, $reservation_id);
            }

            return response()->json(['message' => $bien], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function prereserverBien($bien_id, $visite_id, $appel_id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $bien = Bien::on('temp')->findOrFail($bien_id);
            $bien->etat = EtatBien::PRE_RESERVATION->name;
            if ($bien->save()) {
                $code = '';
                $biens_get_pre = PreReservation::on('temp')->orderByRaw("CAST(code_pre_reserve as UNSIGNED) DESC")
                    ->get('code_pre_reserve')->first();
                if ($biens_get_pre != null) {
                    $code = $biens_get_pre->code_pre_reserve + 1;
                } else {
                    $code = 1;
                }
                $bien_visite_pre_reserve = new PreReservation();
                $bien_visite_pre_reserve->setConnection('temp');
                $bien_visite_pre_reserve->bien_id = $bien_id;
                $bien_visite_pre_reserve->visite_id = $visite_id;
                $bien_visite_pre_reserve->appel_id = $appel_id;
                $bien_visite_pre_reserve->code_pre_reserve = $code;
                $bien_visite_pre_reserve->save();
                //liber bien fron frein_bien
                $this->libere_bien_frein($bien->id);

            }

            HistoriqueBienHelper::createHistoriqueBien(2, "pre_reserver", $bien_id, Auth::guard('api')->user()->id, $visite_id, null);
            return response()->json(['message' => $bien], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function pre_reservations_index(Request $request, $projet_id)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', null);
            $page = $request->input('page', null);
            DatabaseHelper::Config();

            // Démarrer la requête directement sur le modèle
            $query = PreReservation::on('temp')->with('bien','visite','visite.rdv_relation');
            $query->whereHas('bien', function ($subQuery) use ($projet_id) {
                $subQuery->where('projet_id', $projet_id);
            });
            $query->whereHas('visite', function ($subQuery)  {
                $subQuery->where('statut',1);
            });
            if ($request->filled('nature_travaux')) {
                $query->where('nature_travaux', 'like', '%' . $request->input('nature_travaux') . '%');
            }

            if ($request->filled('cout')) {
                $query->where('cout',  $request->input('cout') );
            }
            if ($request->filled('date_validation')) {
                $start = Carbon::parse($request->input('date_validation'));
                $query->whereDate('date_validation', $start);
            }

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                $biens = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Extraire les propriétés du paginateur
                $pagination = [
                    'currentPage' => $biens->currentPage(),
                    'totalItems' => $biens->total(),
                    'totalPages' => $biens->lastPage(),
                ];

                // Extraire les éléments d'utilisateur du paginateur
                $biens = $biens->items();

                // Retourner la réponse simplifiée
                return response()->json([
                    'data' => $biens,
                    'pagination' => $pagination,
                ], 200);
            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function libere_bien_frein($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();

            $array_fr_id = array();

            $frein_biens = Frein_Bien::on('temp')->where('bien_id', $id)->get();
            if (count($frein_biens) > 0) {
                //delete bien_id fron frein_bien
                foreach ($frein_biens as $fr_b) {
                    //push all _fr_id to array
                    array_push($array_fr_id, $fr_b->frein_id);
                    $fr_b->forceDelete();
                }

                //if array is full
                if (count($array_fr_id) > 0) {
                    //test if frein_bien not contains fr_id(id)
                    for ($i = 0; $i <= sizeof($array_fr_id) - 1; $i++) {
                        $frein_id_count = Frein_Bien::on('temp')->where('frein_id', $array_fr_id[$i])->count();
                        if ($frein_id_count == 0) {
                            $frein = Frein::on('temp')->findOrFail($array_fr_id[$i]);
                            $frein->etat = 1; //reset etat frein to 1 (no bien disponible)
                            $frein->save();
                        }
                    }
                }
            }

            return response()->json('done', 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function getHistoriqueBien($bien_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $Historique_bien = HistoriqueBien::on('temp')->where('bien_id', $bien_id)->get();
            return response()->json(['message' => $Historique_bien], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function getBiensDispoByProjet($projet_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $biens = Bien::on('temp')->where('projet_id', $projet_id)->where('etat', EtatBien::DISPONIBLE->name)->get();
            return response()->json(['message' => $biens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function getBiensDispoByTranche($tranche_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $biens = Bien::on('temp')->where('tranche_id', $tranche_id)->where('etat', EtatBien::DISPONIBLE->name)->get();
            return response()->json(['message' => $biens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }
    public function getBiensDispoByBloc($bloc_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $biens = Bien::on('temp')->where('bloc_id', $bloc_id)->where('etat', EtatBien::DISPONIBLE->name)->get();
            return response()->json(['message' => $biens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }
    public function getBiensDispoByImmeuble($immeuble_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $biens = Bien::on('temp')->where('immeuble_id', $immeuble_id)->where('etat', EtatBien::DISPONIBLE->name)->get();
            return response()->json(['message' => $biens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }

    }
    public function setPropostionBien($bien_id, $old_id)
    {
        if (Auth::guard('api')->check() && RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            Config::set('broadcasting.default', 'pusher_4');
            if ($old_id != 0) {
                Bien_Helper::libererBien($old_id, null, null);
            }
            $bien = Bien::on('temp')->findOrFail($bien_id);
            $bien->etat = EtatBien::ENCOURS_DE_PROPOSITION->value;
            if ($bien->save()) {
                $bien_propose = new Proposition();
                $bien_propose->setConnection('temp');
                $bien_propose->bien_id = $bien_id;
                $bien_propose->user_id = Auth::guard('api')->user()->id;
                $bien_propose->save();
                event(new PropositionUpdated($bien_id, $bien_propose->user_id));
            }

            return response()->json(['message' => $bien], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }

    public function getEtatBien($bien_id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();

            $bien = Bien::on('temp')
                ->findOrFail($bien_id);
            return response()->json(['bienEtat' => $bien->etat], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }
    /**
     * get bien by projet_id and concat tranche bloc immeuble
     */
    public function getBiensByProjet_Concat($projet_id)
    {

        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();

            $biens_pr = Bien::on('temp')->with('is_proposed', 'tranche', 'bloc', 'immeuble')
                ->select('propriete_dite_bien AS propriete_dite_bien', 'id', 'etat', 'tranche_id', 'bloc_id', 'immeuble_id', 'prix', 'avance_minimale', 'prix_unitaire', 'superficie_terrasse_calculer', 'superficie_jardin_calculer', 'superficie_balcon_calculer', 'superficie_habitable', 'prix_box', 'prix_parking')
                ->where(function ($query) {
                    $query->where('etat', 'DISPONIBLE')->orwhere('etat', 'ENCOURS_DE_PROPOSITION');
                })
                ->where('projet_id', $projet_id)->get();
            $biens = array();
            foreach ($biens_pr as $b_pr) {
                //tranches bloc w immeuble
                if ($b_pr->tranche_id != null && $b_pr->bloc_id != null && $b_pr->immeuble_id != null) {
                    array_push($biens, array('id' => $b_pr->id, 'propriete_dite_bien' => $b_pr->tranche->nom . '-' . $b_pr->bloc->nom . '-' . $b_pr->immeuble->nom . '-' . $b_pr->propriete_dite_bien, 'etat' => $b_pr->etat, 'prix' => $b_pr->prix, 'avance_minimale' => $b_pr->avance_minimale, 'prix_unitaire' => $b_pr->prix_unitaire, 'superficie_terrasse_calculer' => $b_pr->superficie_terrasse_calculer, 'superficie_jardin_calculer' => $b_pr->superficie_jardin_calculer, 'superficie_balcon_calculer' => $b_pr->superficie_balcon_calculer, 'superficie_habitable' => $b_pr->superficie_habitable, 'prix_box' => $b_pr->prix_box, 'prix_parking' => $b_pr->prix_parking, 'is_proposed' => $b_pr->is_proposed));
                }

                //tranche bloc
                elseif ($b_pr->tranche_id != null && $b_pr->bloc_id != null && $b_pr->immeuble_id == null) {

                    array_push($biens, array('id' => $b_pr->id, 'propriete_dite_bien' => $b_pr->tranche->nom . '-' . $b_pr->bloc->nom . '-' . $b_pr->propriete_dite_bien, 'etat' => $b_pr->etat, 'prix' => $b_pr->prix, 'avance_minimale' => $b_pr->avance_minimale, 'prix_unitaire' => $b_pr->prix_unitaire, 'superficie_terrasse_calculer' => $b_pr->superficie_terrasse_calculer, 'superficie_jardin_calculer' => $b_pr->superficie_jardin_calculer, 'superficie_balcon_calculer' => $b_pr->superficie_balcon_calculer, 'superficie_habitable' => $b_pr->superficie_habitable, 'prix_box' => $b_pr->prix_box, 'prix_parking' => $b_pr->prix_parking, 'is_proposed' => $b_pr->is_proposed));
                }

                //tranche immeuble
                elseif ($b_pr->tranche_id != null && $b_pr->bloc_id == null && $b_pr->immeuble_id != null) {

                    array_push($biens, array('id' => $b_pr->id, 'propriete_dite_bien' => $b_pr->tranche->nom . '-' . $b_pr->immeuble->nom . '-' . $b_pr->propriete_dite_bien, 'etat' => $b_pr->etat, 'prix' => $b_pr->prix, 'avance_minimale' => $b_pr->avance_minimale, 'prix_unitaire' => $b_pr->prix_unitaire, 'superficie_terrasse_calculer' => $b_pr->superficie_terrasse_calculer, 'superficie_jardin_calculer' => $b_pr->superficie_jardin_calculer, 'superficie_balcon_calculer' => $b_pr->superficie_balcon_calculer, 'superficie_habitable' => $b_pr->superficie_habitable, 'prix_box' => $b_pr->prix_box, 'prix_parking' => $b_pr->prix_parking, 'is_proposed' => $b_pr->is_proposed));
                }
                //bloc immeuble
                elseif ($b_pr->tranche_id == null && $b_pr->bloc_id != null && $b_pr->immeuble_id != null) {
                    array_push($biens, array('id' => $b_pr->id, 'propriete_dite_bien' => $b_pr->bloc->nom . '-' . $b_pr->immeuble->nom . '-' . $b_pr->propriete_dite_bien, 'etat' => $b_pr->etat, 'prix' => $b_pr->prix, 'avance_minimale' => $b_pr->avance_minimale, 'prix_unitaire' => $b_pr->prix_unitaire, 'superficie_terrasse_calculer' => $b_pr->superficie_terrasse_calculer, 'superficie_jardin_calculer' => $b_pr->superficie_jardin_calculer, 'superficie_balcon_calculer' => $b_pr->superficie_balcon_calculer, 'superficie_habitable' => $b_pr->superficie_habitable, 'prix_box' => $b_pr->prix_box, 'prix_parking' => $b_pr->prix_parking, 'is_proposed' => $b_pr->is_proposed));
                }
                //bloc
                elseif ($b_pr->tranche_id == null && $b_pr->bloc_id != null && $b_pr->immeuble_id == null) {
                    array_push($biens, array('id' => $b_pr->id, 'propriete_dite_bien' => $b_pr->bloc->nom . '-' . $b_pr->propriete_dite_bien, 'etat' => $b_pr->etat, 'prix' => $b_pr->prix, 'avance_minimale' => $b_pr->avance_minimale, 'prix_unitaire' => $b_pr->prix_unitaire, 'superficie_terrasse_calculer' => $b_pr->superficie_terrasse_calculer, 'superficie_jardin_calculer' => $b_pr->superficie_jardin_calculer, 'superficie_balcon_calculer' => $b_pr->superficie_balcon_calculer, 'superficie_habitable' => $b_pr->superficie_habitable, 'prix_box' => $b_pr->prix_box, 'prix_parking' => $b_pr->prix_parking, 'is_proposed' => $b_pr->is_proposed));
                }
                //immeuble
                elseif ($b_pr->tranche_id == null && $b_pr->bloc_id == null && $b_pr->immeuble_id != null) {
                    array_push($biens, array('id' => $b_pr->id, 'propriete_dite_bien' => $b_pr->immeuble->nom . '-' . $b_pr->propriete_dite_bien, 'etat' => $b_pr->etat, 'prix' => $b_pr->prix, 'avance_minimale' => $b_pr->avance_minimale, 'prix_unitaire' => $b_pr->prix_unitaire, 'superficie_terrasse_calculer' => $b_pr->superficie_terrasse_calculer, 'superficie_jardin_calculer' => $b_pr->superficie_jardin_calculer, 'superficie_balcon_calculer' => $b_pr->superficie_balcon_calculer, 'superficie_habitable' => $b_pr->superficie_habitable, 'prix_box' => $b_pr->prix_box, 'prix_parking' => $b_pr->prix_parking, 'is_proposed' => $b_pr->is_proposed));
                }
                //tranche
                elseif ($b_pr->tranche_id != null && $b_pr->bloc_id == null && $b_pr->immeuble_id == null) {
                    array_push($biens, array('id' => $b_pr->id, 'propriete_dite_bien' => $b_pr->tranche->nom . '-' . $b_pr->propriete_dite_bien, 'etat' => $b_pr->etat, 'prix' => $b_pr->prix, 'avance_minimale' => $b_pr->avance_minimale, 'prix_unitaire' => $b_pr->prix_unitaire, 'superficie_terrasse_calculer' => $b_pr->superficie_terrasse_calculer, 'superficie_jardin_calculer' => $b_pr->superficie_jardin_calculer, 'superficie_balcon_calculer' => $b_pr->superficie_balcon_calculer, 'superficie_habitable' => $b_pr->superficie_habitable, 'prix_box' => $b_pr->prix_box, 'prix_parking' => $b_pr->prix_parking, 'is_proposed' => $b_pr->is_proposed));
                }

            }

            return response()->json(['biens' => $biens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function getBiens_Vendu_ByProjet_Concat($projet_id, $text)
    {

        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            if ($text == 'BiensNonRemise') {
                //biens vendu sans Remise
                $biens_pr = Bien::on('temp')->with('tranche', 'bloc', 'immeuble', 'reservation')->withSum('encaissements', 'montant')
                    ->where('etat', 'RESERVATION')
                    ->doesntHave('remiseCle')
                    ->where('projet_id', $projet_id)->get();
            } else {
                //biens vendu
                $biens_pr = Bien::on('temp')->with('tranche', 'bloc', 'immeuble', 'reservation')->withSum('encaissements', 'montant')
                    ->where('etat', 'RESERVATION')
                    ->where('projet_id', $projet_id)->get();
            }

            $biens = array();
            foreach ($biens_pr as $b_pr) {
                if ($b_pr->prix <= $b_pr->encaissements_sum_montant) {
                    //tranches bloc w immeuble
                    if ($b_pr->tranche_id != null && $b_pr->bloc_id != null && $b_pr->immeuble_id != null) {
                        array_push($biens, array('id' => $b_pr->id, 'clients' => $b_pr->reservation->aquereurs, 'prix' => $b_pr->prix, 'encaissement' => $b_pr->encaissements_sum_montant, 'propriete_dite_bien' => $b_pr->tranche->nom . '-' . $b_pr->bloc->nom . '-' . $b_pr->immeuble->nom . '-' . $b_pr->propriete_dite_bien));
                    }
                    //tranche bloc
                    elseif ($b_pr->tranche_id != null && $b_pr->bloc_id != null && $b_pr->immeuble_id == null) {
                        array_push($biens, array('id' => $b_pr->id, 'prix' => $b_pr->prix, 'clients' => $b_pr->reservation->aquereurs, 'encaissement' => $b_pr->encaissements_sum_montant, 'propriete_dite_bien' => $b_pr->tranche->nom . '-' . $b_pr->bloc->nom . '-' . $b_pr->propriete_dite_bien));
                    }

                    //tranche immeuble
                    elseif ($b_pr->tranche_id != null && $b_pr->bloc_id == null && $b_pr->immeuble_id != null) {
                        array_push($biens, array('id' => $b_pr->id, 'prix' => $b_pr->prix, 'clients' => $b_pr->reservation->aquereurs, 'encaissement' => $b_pr->encaissements_sum_montant, 'propriete_dite_bien' => $b_pr->tranche->nom . '-' . $b_pr->immeuble->nom . '-' . $b_pr->propriete_dite_bien));
                    }
                    //bloc immeuble
                    elseif ($b_pr->tranche_id == null && $b_pr->bloc_id != null && $b_pr->immeuble_id != null) {
                        array_push($biens, array('id' => $b_pr->id, 'prix' => $b_pr->prix, 'clients' => $b_pr->reservation->aquereurs, 'encaissement' => $b_pr->encaissements_sum_montant, 'propriete_dite_bien' => $b_pr->bloc->nom . '-' . $b_pr->immeuble->nom . '-' . $b_pr->propriete_dite_bien));
                    }
                    //bloc
                    elseif ($b_pr->tranche_id == null && $b_pr->bloc_id != null && $b_pr->immeuble_id == null) {
                        array_push($biens, array('id' => $b_pr->id, 'prix' => $b_pr->prix, 'clients' => $b_pr->reservation->aquereurs, 'encaissement' => $b_pr->encaissements_sum_montant, 'propriete_dite_bien' => $b_pr->bloc->nom . '-' . $b_pr->propriete_dite_bien));
                    }
                    //immeuble
                    elseif ($b_pr->tranche_id == null && $b_pr->bloc_id == null && $b_pr->immeuble_id != null) {
                        array_push($biens, array('id' => $b_pr->id, 'prix' => $b_pr->prix, 'clients' => $b_pr->reservation->aquereurs, 'encaissement' => $b_pr->encaissements_sum_montant, 'propriete_dite_bien' => $b_pr->immeuble->nom . '-' . $b_pr->propriete_dite_bien));
                    }
                    //tranche
                    elseif ($b_pr->tranche_id != null && $b_pr->bloc_id == null && $b_pr->immeuble_id == null) {
                        array_push($biens, array('id' => $b_pr->id, 'prix' => $b_pr->prix, 'clients' => $b_pr->reservation->aquereurs, 'encaissement' => $b_pr->encaissements_sum_montant, 'propriete_dite_bien' => $b_pr->tranche->nom . '-' . $b_pr->propriete_dite_bien));
                    }

                }

            }
            return response()->json(['biens' => $biens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function getEtatByType_projet($projet_id, $type_id)
    {
        if (Auth::guard('api')->check() && RoleHelper::ACSup()) {
            DatabaseHelper::Config(); // Assurez-vous que la connexion est bien configurée

            // Exécuter la requête SQL avec le constructeur de requêtes
            $counts = DB::connection('temp')
                ->table('biens')
                ->selectRaw("etat, COUNT(*) as total")
                ->where('projet_id', $projet_id)
                ->where('type_id', $type_id)
                ->groupBy('etat')
                ->get();

            // Retourner les données au format JSON
            return response()->json(['data' => $counts], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }


}
