<?php

namespace App\Http\Controllers;

use App\Enum\EtatBien;
use App\Http\Helpers\HistoriqueBienHelper;
use App\Http\Requests\StoreBienRequest;
use App\Http\Requests\UpdateBienRequest;
use App\Models\Bien;
use App\Models\HistoriqueBien;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use Illuminate\Http\Request;


class BienController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $perPage = 20; // Number of items per page
            $page = $request->input('page', 1);
            $biens = Bien::on('temp')->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();
            return response()->json(['bien' => $biens]);
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
            $bien->nbre_facades = $request->nbre_facades;
            $bien->superficie_parking = $request->superficie_parking;
            $bien->superficie_box = $request->superficie_box;
            $bien->superficie_terrasse = $request->superficie_terrasse;
            $bien->superficie_jardin = $request->superficie_jardin;
            $bien->titre_foncier = $request->titre_foncier;
            $bien->etat = $request->etat;
            $bien->type_id = $request->type_id;
            $bien->projet_id = $request->projet_id;
            $bien->tranche_id = $request->tranche_id;
            $bien->bloc_id = $request->bloc_id;
            $bien->immeuble_id = $request->immeuble_id;
            $bien->vue_id=$request->vue_id;
            $bien->typologie_id=$request->typologie_id;
            $bien->save();

            return response()->json(['message' => $bien], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show( $id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $bien = bien::on('temp')->findOrfail($id);
            return response()->json(['bien' => $bien], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit( $id)
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
    public function update(UpdateBienRequest $request,  $id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $bien = bien::on('temp')->findOrfail($id);
            $update = $request->all();
            foreach($update as $key => $value) {
                $bien->$key = $value;
            }
            $bien->save();

            return response()->json(['message' => $bien], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy( $id)
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

            return response()->json(['message' => 'Bien est bien restaurer'], 200);

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
            $bien->etat=EtatBien::BLOQUE->value;
            $bien->save();
            HistoriqueBienHelper::createHistoriqueBien(4, "bloquer", $bien_id, Auth::guard('api')->user()->id);

            return response()->json(['message' => $bien], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function reserverBien($bien_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $bien = Bien::on('temp')->findOrFail($bien_id);
            $bien->etat=EtatBien::RESERVATION->value;;
            $bien->save();
            HistoriqueBienHelper::createHistoriqueBien(3, "reserver", $bien_id, Auth::guard('api')->user()->id);
            return response()->json(['message' => $bien], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function prereserverBien($bien_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $bien = Bien::on('temp')->findOrFail($bien_id);
            $bien->etat=EtatBien::PRE_RESERVATION->value;;
            $bien->save();
            HistoriqueBienHelper::createHistoriqueBien(2, "pre_reserver", $bien_id, Auth::guard('api')->user()->id);
            return response()->json(['message' => $bien], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function libererBien($bien_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $bien = Bien::on('temp')->findOrFail($bien_id);
            $bien->etat=EtatBien::DISPONIBLE->value;;
            $bien->save();
            HistoriqueBienHelper::createHistoriqueBien(1, "liberer", $bien_id, Auth::guard('api')->user()->id);

            return response()->json(['message' => $bien], 200);

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

    public function getBiensByProjet($projet_id){

        if (RoleHelper::AC()) {
            DatabaseHelper::Config();            
            $biens = Bien::on('temp')->where('projet_id', $projet_id)->get();
            return response()->json(['biens' => $biens], 200);
            

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function getBiensByTranche($tranche_id){
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $biens = Bien::on('temp')->where('tranche_id', $tranche_id)->get();
            return response()->json(['message' => $biens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function getBiensByBloc($bloc_id){
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $biens = Bien::on('temp')->where('bloc_id', $bloc_id)->get();
            return response()->json(['message' => $biens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function getBiensByImmeuble($immeuble_id){
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $biens = Bien::on('temp')->where('immeuble_id', $immeuble_id)->get();
            return response()->json(['message' => $biens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function getBiensDispoByProjet($projet_id){
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $biens = Bien::on('temp')->where('projet_id', $projet_id)->where('etat', EtatBien::DISPONIBLE->name)->get();
            return response()->json(['message' => $biens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function getBiensDispoByTranche($tranche_id){
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $biens = Bien::on('temp')->where('tranche_id', $tranche_id)->where('etat', EtatBien::DISPONIBLE->name)->get();
            return response()->json(['message' => $biens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }
    public function getBiensDispoByBloc($bloc_id){
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $biens = Bien::on('temp')->where('bloc_id', $bloc_id)->where('etat',  EtatBien::DISPONIBLE->name)->get();
            return response()->json(['message' => $biens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }
    public function getBiensDispoByImmeuble($immeuble_id){
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $biens = Bien::on('temp')->where('immeuble_id', $immeuble_id)->where('etat',  EtatBien::DISPONIBLE->name)->get();
            return response()->json(['message' => $biens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }

    }
    public function setPropostionBien($bien_id){
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $bien = Bien::on('temp')->findOrFail($bien_id);
            $bien->etat=EtatBien::ENCOURS_DE_PROPOSITION->value;;
            $bien->save();
            HistoriqueBienHelper::createHistoriqueBien(6, "encours de proposition", $bien_id, Auth::guard('api')->user()->id);
            return response()->json(['message' => $bien], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}
