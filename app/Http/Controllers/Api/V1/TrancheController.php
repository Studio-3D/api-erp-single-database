<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreTrancheRequest;
use App\Http\Requests\UpdateTrancheRequest;
use App\Models\Tranche;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\Societe;

use App\Models\TraitementAppel;

use App\Models\FreinTranche;


class TrancheController extends Controller
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

             $query = Tranche::on('temp');

             if ($projet_id) {
                 $query->where('projet_id', $projet_id);
             }

             if ($request->filled('nom')) {
                 $query->where('nom', 'like', '%' . $request->input('nom') . '%');
             }
             if ($request->filled('niveau_etages')) {
                $query->where('niveau_etages', 'like', '%' . $request->input('niveau_etages') . '%');
             }


             $tranches = $query->orderBy('created_at', 'desc')
                 ->paginate($size, ['*'], 'page', $page);

             $pagination = [
                 'currentPage' => $tranches->currentPage(),
                 'totalItems' => $tranches->total(),
                 'totalPages' => $tranches->lastPage(),
             ];

             $tranches = $tranches->items();

             return response()->json([
                 'data' => $tranches,
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

             $query = Tranche::on('temp')->withSum('bien', 'superficie_total')->with('Coefficient_tranche')->where('projet_id', $projet_id);

             if ($request->filled('nom')) {
                  $query->where('nom', 'like', '%' . $request->input('nom') . '%');
             }
             if ($request->filled('niveau_etages')) {
                 $query->where('niveau_etages', 'like', '%' . $request->input('niveau_etages') . '%');
             }
             if ($request->filled('qp_bati')) {
                $query->where('qp_bati', 'like', '%' . $request->input('qp_bati') . '%');
            }

            if ($request->filled('coefficient')) {
                $query->whereHas('Coefficient_tranche', function ($q) use ($request) {
                    $q->where('coefficient', $request->input('coefficient'));
                });
            }
             if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {

                 $tranches = $query->orderBy('created_at', 'desc')
                     ->paginate($size, ['*'], 'page', $page);

                 $pagination = [
                     'currentPage' => $tranches->currentPage(),
                     'totalItems' => $tranches->total(),
                     'totalPages' => $tranches->lastPage(),
                 ];

                 $tranches = $tranches->items();

                 return response()->json([
                     'data' => $tranches,
                     'pagination' => $pagination,
                 ], 200);
             } else {
                 // Return all results if pagination parameters are not provided or invalid
                 $tranches = $query->orderBy('created_at', 'desc')
                     ->get();

                 return response()->json(['tranches' => $tranches], 200);
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
    public function store(StoreTrancheRequest $request)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $tranche = new Tranche();
            $tranche->setConnection('temp');
            $tranche->nom = $request->nom;
            $tranche->projet_id = $request->projet_id;
            $tranche->date_lancement = $request->date_lancement;
            $tranche->date_livraison = $request->date_livraison;
            $tranche->niveau_etages = $request->niveau_etages;
            $tranche->nbre_blocs = $request->nbre_blocs ? $request->nbre_blocs : 0;
            $tranche->nbre_immeubles = $request->nbre_immeubles ? $request->nbre_immeubles : 0;
            $tranche->nbre_biens = $request->nbre_biens ? $request->nbre_biens : 0;
            $tranche->save();

            return response()->json(['tranche' => $tranche], 200);

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
            $tranche = Tranche::on('temp')->with('projet','bloc','immeuble','bien')->withCount(['bloc', 'immeuble', 'bien'])->findOrfail($id);
            return response()->json(['tranche' => $tranche], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $tranche = Tranche::on('temp')->findOrfail($id);
            return response()->json(['message' => $tranche], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTrancheRequest $request, $id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $tranche = Tranche::on('temp')->findOrfail($id);
            if ($request->has('nom')) {


                                $societe_id = Auth::guard('api')->user()->societe_id;
                                $societe=Societe::findOrfail( $societe_id);
                                $DatabaseName='Erp_'.$societe->raison_sociale_concatene.'_'.$societe_id;
                                $request->validate([
                                            'nom' => [
                                                Rule::unique('temp.'.$DatabaseName.'.tranches')
                                                                            ->ignore($tranche->id)->whereNull('deleted_at'),
                                            ],
                                        ]);

                $request->validate([
                    'nom' => [
                        Rule::unique('tranches')->ignore($tranche->id)->whereNull('deleted_at'),
                    ],
                ]);
            }
            $update = $request->all();
            foreach ($update as $key => $value) {
                $tranche->$key = $value;
            }
            $tranche->save();

            return response()->json(['message' => $tranche], 200);
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
            $tranche = Tranche::on('temp')->findOrfail($id);
            $bloc=new BlocController();

            if(count($tranche->bloc)>0){
                foreach($tranche->bloc as $blc){
                    $bloc->destroy($blc->id);
                }
            }

            if(count($tranche->immeuble)>0){
                $imme=new ImmeubleController();
                foreach($tranche->immeuble as $imm){
                    $imme->destroy($imm->id);
                }
            }
            if(count($tranche->bien)>0){
                $bien=new BienController();
                foreach($tranche->bien as $b){
                    $bien->destroy($b->id);
                }
            }

             //traitement_appel
             $traitement_appels=TraitementAppel::on('temp')->where('tranche_id',$id)->get();
             if(count($traitement_appels)>0){
                $appel=new AppelController();
                 foreach($traitement_appels as $tr){
                     $appel->destroy_t_appel($tr->id,1);
                 }
             }
             //freinTranche
             $freinTranche=FreinTranche::on('temp')->where('tranche_id',$id)->get();
             if(count($freinTranche)>0){
                 foreach($freinTranche as $tr){
                     $tr->delete();
                 }
             }

             if(count($tranche->all_coefficients)>0){
                foreach($tranche->all_coefficients as $c ){
                    $c->delete();
                }
             }
             if(count($tranche->bien_tva)>0){
                foreach($tranche->bien_tva as $b){
                    $b->delete();
                }
             }
             if(count($tranche->echeancesTranches)>0){
                foreach($tranche->echeancesTranches as $ech){
                    $ech->delete();
                }
             }

            if ($tranche->delete()) {
                return response()->json(['message' => 'tranche supprimé avec succés'], 200);
            } else {
                return response()->json(['message' => 'tranche non supprimé'], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function restoreTranche($tranche_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $tranche = Tranche::on('temp')->where('id', $tranche_id)->withTrashed()->restore();

            return response()->json(['message' => 'Tranche restored succesfully'], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedTranches()
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $tranches = Tranche::on('temp')::onlyTrashed()->get();
            return response()->json(['message' => $tranches], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }



}
