<?php

namespace App\Http\Controllers;

use App\Enum\EtatBien;
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
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Helpers\NotificationHelper;
use App\Models\Notification;
use App\Models\Visite;
use Carbon\Carbon;
use App\Events\PropositionUpdated;
use Illuminate\Support\Facades\Config;

class BienController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, $projet_id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);
            $biens = Bien::on('temp')
                ->orderBy('created_at', 'desc')
                ->where('projet_id', $projet_id)
                ->paginate($perPage, ['*'], 'page', $page);
            return response()->json(['biens' => $biens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }

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
                    ];});
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
                    ];});
                $data = PaginationHelper::paginate_array($biens->toArray(), $perPage, $page, $request->url());

            }
            return response()->json(['biens' => $data], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
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
                    Bien_Helper::store_bien_frein($bien->id);

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
            $bien = bien::on('temp')->findOrfail($id);
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
            Bien_Helper::libererBien($id, null,null);
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
                if ($bien->etat == 1) {
                    Bien_Helper::libererBien($bien->id, null,null);
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
            DatabaseHelper::Config();
            $bien = Bien::on('temp')->findOrFail($bien_id);
            $bien->etat = EtatBien::RESERVATION->value;
            if ($bien->save()) {
                $this->libere_bien_frein($bien->id);
            }
            HistoriqueBienHelper::createHistoriqueBien(3, "reserver", $bien_id, Auth::guard('api')->user()->id, $visite_id, $reservation_id);
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

    public function getBiensByProjet($projet_id)
    {

        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $biens = Bien::on('temp')->where('projet_id', $projet_id)->get();
            return response()->json(['biens' => $biens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }
    public function getBiensByTranche($tranche_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $biens = Bien::on('temp')->where('tranche_id', $tranche_id)->get();
            return response()->json(['biens' => $biens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }
    public function getBiensByTranchepaginate(Request $request, $tranche_id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);

            $biens = Bien::on('temp')
                ->orderBy('created_at', 'desc')
                ->where('tranche_id', $tranche_id)
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json(['biens' => $biens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function getBiensByBloc($bloc_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $biens = Bien::on('temp')->where('bloc_id', $bloc_id)->get();
            return response()->json(['biens' => $biens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }
    public function getBiensByBlocpaginate(Request $request, $bloc_id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);

            $biens = Bien::on('temp')
                ->orderBy('created_at', 'desc')
                ->where('bloc_id', $bloc_id)
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json(['biens' => $biens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function getBiensByImmeuble($immeuble_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $biens = Bien::on('temp')->where('immeuble_id', $immeuble_id)->get();
            return response()->json(['biens' => $biens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }
    public function getBiensByImmeublepaginate(Request $request, $immeuble_id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);

            $biens = Bien::on('temp')
                ->orderBy('created_at', 'desc')
                ->where('immeuble_id', $immeuble_id)
                ->paginate($perPage, ['*'], 'page', $page);
            return response()->json(['biens' => $biens], 200);
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
            if($old_id!=0){
                Bien_Helper::libererBien($old_id,null,null);
            }
            $bien = Bien::on('temp')->findOrFail($bien_id);
            $bien->etat=EtatBien::ENCOURS_DE_PROPOSITION->value;
                if($bien->save()){
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

            $biens_pr = Bien::on('temp')->with('is_proposed')
                ->select('propriete_dite_bien AS propriete_dite_bien', 'id', 'etat', 'tranche_id', 'bloc_id', 'immeuble_id', 'prix', 'avance_minimale', 'prix_unitaire', 'superficie_terrasse_calculer', 'superficie_jardin_calculer', 'superficie_balcon_calculer', 'superficie_habitable','prix_box','prix_parking')
                ->where(function ($query) {
                    $query->where('etat', 'DISPONIBLE')->orwhere('etat', 'ENCOURS_DE_PROPOSITION');
                })
                ->where('projet_id', $projet_id)->get();
            $biens = [];
            foreach ($biens_pr as $b_pr) {
                //tranches bloc w immeuble
                if ($b_pr->tranche_id != null && $b_pr->bloc_id != null && $b_pr->immeuble_id != null) {
                    $biens = Bien::on('temp')->with('is_proposed')->join('tranches', 'biens.tranche_id', '=', 'tranches.id')
                        ->join('blocs', 'blocs.id', '=', 'biens.bloc_id')
                        ->join('immeubles', 'immeubles.id', '=', 'biens.immeuble_id')
                        ->where(function ($query) {
                            $query->where('biens.etat', 'DISPONIBLE')->orwhere('biens.etat', 'ENCOURS_DE_PROPOSITION');
                        })
                        ->where('biens.projet_id', $projet_id)
                        ->select(DB::raw("CONCAT(tranches.nom,'-',blocs.nom,'-',immeubles.nom,'-',biens.propriete_dite_bien) AS propriete_dite_bien"), 'biens.id', 'biens.etat', 'biens.prix', 'biens.avance_minimale', 'prix_unitaire', 'superficie_terrasse_calculer', 'superficie_jardin_calculer', 'superficie_balcon_calculer', 'superficie_habitable','prix_box','prix_parking')->get();
                }
                //tranche bloc
                elseif ($b_pr->tranche_id != null && $b_pr->bloc_id != null && $b_pr->immeuble_id == null) {
                    $biens = Bien::on('temp')->with('is_proposed')->join('tranches', 'biens.tranche_id', '=', 'tranches.id')
                        ->join('blocs', 'blocs.id', '=', 'biens.bloc_id')
                        ->where(function ($query) {
                            $query->where('biens.etat', 'DISPONIBLE')->orwhere('biens.etat', 'ENCOURS_DE_PROPOSITION');
                        })
                        ->where('biens.projet_id', $projet_id)
                        ->select(DB::raw("CONCAT(tranches.nom,'-',blocs.nom,'-',biens.propriete_dite_bien) AS propriete_dite_bien"), 'biens.id', 'biens.etat', 'biens.prix', 'biens.avance_minimale', 'prix_unitaire', 'superficie_terrasse_calculer', 'superficie_jardin_calculer', 'superficie_balcon_calculer', 'superficie_habitable','prix_box','prix_parking')->get();
                }
                //tranche immeuble
                elseif ($b_pr->tranche_id != null && $b_pr->bloc_id == null && $b_pr->immeuble_id != null) {
                    $biens = Bien::on('temp')->with('is_proposed')->join('tranches', 'biens.tranche_id', '=', 'tranches.id')
                        ->join('immeubles', 'immeubles.id', '=', 'biens.immeuble_id')
                        ->where(function ($query) {
                            $query->where('biens.etat', 'DISPONIBLE')->orwhere('biens.etat', 'ENCOURS_DE_PROPOSITION');
                        })
                        ->where('biens.projet_id', $projet_id)
                        ->select(DB::raw("CONCAT(tranches.nom,'-',immeubles.nom,'-',biens.propriete_dite_bien) AS propriete_dite_bien"), 'biens.id', 'biens.etat', 'biens.prix', 'biens.avance_minimale', 'prix_unitaire', 'superficie_terrasse_calculer', 'superficie_jardin_calculer', 'superficie_balcon_calculer', 'superficie_habitable','prix_box','prix_parking')->get();
                }
                //bloc immeuble
                elseif ($b_pr->tranche_id == null && $b_pr->bloc_id != null && $b_pr->immeuble_id != null) {
                    $biens = Bien::on('temp')->with('is_proposed')
                        ->join('blocs', 'blocs.id', '=', 'biens.bloc_id')
                        ->join('immeubles', 'immeubles.id', '=', 'biens.immeuble_id')
                        ->where(function ($query) {
                            $query->where('biens.etat', 'DISPONIBLE')->orwhere('biens.etat', 'ENCOURS_DE_PROPOSITION');
                        })
                        ->where('biens.projet_id', $projet_id)
                        ->select(DB::raw("CONCAT(blocs.nom,'-',immeubles.nom,'-',biens.propriete_dite_bien) AS propriete_dite_bien"), 'biens.id', 'biens.etat', 'biens.prix', 'biens.avance_minimale', 'prix_unitaire', 'superficie_terrasse_calculer', 'superficie_jardin_calculer', 'superficie_balcon_calculer', 'superficie_habitable','prix_box','prix_parking')->get();
                }
                //bloc
                elseif ($b_pr->tranche_id == null && $b_pr->bloc_id != null && $b_pr->immeuble_id == null) {
                    $biens = Bien::on('temp')->with('is_proposed')
                        ->join('blocs', 'blocs.id', '=', 'biens.bloc_id')
                        ->where(function ($query) {
                            $query->where('biens.etat', 'DISPONIBLE')->orwhere('biens.etat', 'ENCOURS_DE_PROPOSITION');
                        })
                        ->where('biens.projet_id', $projet_id)
                        ->select(DB::raw("CONCAT(blocs.nom,'-',biens.propriete_dite_bien) AS propriete_dite_bien"), 'biens.id', 'biens.etat', 'biens.prix', 'biens.avance_minimale', 'prix_unitaire', 'superficie_terrasse_calculer', 'superficie_jardin_calculer', 'superficie_balcon_calculer', 'superficie_habitable','prix_box','prix_parking')->get();
                }
                //immeuble
                elseif ($b_pr->tranche_id == null && $b_pr->bloc_id == null && $b_pr->immeuble_id != null) {
                    $biens = Bien::on('temp')->with('is_proposed')->join('immeubles', 'immeubles.id', '=', 'biens.immeuble_id')
                        ->where(function ($query) {
                            $query->where('biens.etat', 'DISPONIBLE')->orwhere('biens.etat', 'ENCOURS_DE_PROPOSITION');
                        })
                        ->where('biens.projet_id', $projet_id)
                        ->select(DB::raw("CONCAT(immeubles.nom,'-',biens.propriete_dite_bien) AS propriete_dite_bien"), 'biens.id', 'biens.etat', 'biens.prix', 'biens.avance_minimale', 'prix_unitaire', 'superficie_terrasse_calculer', 'superficie_jardin_calculer', 'superficie_balcon_calculer', 'superficie_habitable','prix_box','prix_parking')->get();
                }
                //tranche
                elseif ($b_pr->tranche_id != null && $b_pr->bloc_id == null && $b_pr->immeuble_id == null) {
                    $biens = Bien::on('temp')->with('is_proposed')->join('tranches', 'biens.tranche_id', '=', 'tranches.id')
                        ->where(function ($query) {
                            $query->where('biens.etat', 'DISPONIBLE')->orwhere('biens.etat', 'ENCOURS_DE_PROPOSITION');
                        })
                        ->where('biens.projet_id', $projet_id)
                        ->select(DB::raw("CONCAT(tranches.nom,'-',biens.propriete_dite_bien) AS propriete_dite_bien"), 'biens.id', 'biens.etat', 'biens.prix', 'biens.avance_minimale', 'prix_unitaire', 'superficie_terrasse_calculer', 'superficie_jardin_calculer', 'superficie_balcon_calculer', 'superficie_habitable','prix_box','prix_parking')->get();
                }

            }
            if (count($biens) == 0) {
                $biens = $biens_pr;
            }
            return response()->json(['biens' => $biens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

}
