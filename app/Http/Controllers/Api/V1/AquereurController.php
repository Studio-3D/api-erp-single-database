<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreAquereurRequest;
use App\Http\Requests\UpdateAquereurRequest;
use App\Models\Aquereur;
use App\Models\AquereurDesistement;
use App\Models\NouvelAquereurDesistement;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;

class AquereurController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request,$projet_id)
    {
        if(Auth::guard('api')->check()){
            DatabaseHelper::Config();
            $perPage=$request->input('pageSize',config('app.default_item_number_perpage'));
            $page=$request->input('page',1);

            $aquereurs=Aquereur::on('temp')->join("reservations","aquereurs.reservation_id","=","reservations.id")
                ->join("projets","reservations.projet_id","=","projets.id")
                ->where("projets.id",$projet_id)
                ->select('aquereurs.*')
                ->orderBy('created_at','desc')
                ->paginate($perPage,['*'],'page',$page);

            return response()->json(['Aquereurs',$aquereurs],200);
        }
        return response()->json(['error' => 'Unauthorized'],401);
    }

   /* public function getAquereurByReservation(Request $request, $reservation_id)
{
    if (RoleHelper::ACSup()) {
        DatabaseHelper::Config();
        $size = $request->input('size', config('app.default_item_number_perpage'));
        $page = $request->input('page', 1);

        $reservation = Reservation::on('temp')->findOrFail($reservation_id);

        // Préparation de la requête principale
        $query = Aquereur::on('temp')
            ->orderBy('created_at', 'desc')
            ->where('reservation_id', $reservation_id);

        // Vérifier si le dossier est désisté
        if ($reservation->etat > 1) {
            $query->onlyTrashed();
        }

        // Application des filtres dynamiques
        if ($request->filled('nom')) {
            $query->whereHas('client', function ($q) use ($request) {
                $q->where('nom', 'like', '%' . $request->input('nom') . '%');
            });
        }

        if ($request->filled('cin')) {
            $query->whereHas('client', function ($q) use ($request) {
                $q->where('cin', 'like', '%' . $request->input('cin') . '%');
            });
        }

        if ($request->filled('prenom')) {
            $query->whereHas('client', function ($q) use ($request) {
                $q->where('prenom', 'like', '%' . $request->input('prenom') . '%');
            });
        }

        if ($request->filled('telephone')) {
            $query->whereHas('client', function ($q) use ($request) {
                $q->where('telephone_num1', 'like', '%' . $request->input('telephone') . '%')
                  ->orWhere('telephone_num2', 'like', '%' . $request->input('telephone') . '%');
            });
        }

        if ($request->filled('pourcentage')) {
            $query->where('pourcentage', 'like', '%' . $request->input('pourcentage') . '%');
        }

        // Récupération des résultats paginés
        $aquereurs = $query->paginate($size, ['*'], 'page', $page);

        // Construction de la pagination
        $pagination = [
            'currentPage' => $aquereurs->currentPage(),
            'totalItems' => $aquereurs->total(),
            'totalPages' => $aquereurs->lastPage(),
        ];

        // Envoi de la réponse
        return response()->json([
            'data' => $aquereurs->items(),
            'pagination' => $pagination,
            'etat_res' => $reservation->etat,
        ], 200);
    } else {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
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
    public function store(StoreAquereurRequest $request)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            if($request->client_id!=null){
                $aquereur=new Aquereur();
                $aquereur->setConnection('temp');
                $aquereur->pourcentage=$request->pourcentage;
                $aquereur->client_id=$request->client_id;
                $aquereur->reservation_id=$request->reservation_id;
                if($aquereur->save()){
                    return response()->json(['Aquérreur',$aquereur],200);
                }
            }

        }
        return  response()->json(['error','Unauthorized'],401);
    }

   public function store_aquereurs_desistement(Request $request)
{
    if(!RoleHelper::ACSup()) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    DatabaseHelper::Config();

    try {
        $cl_id = $request->client_id;

        if(empty($cl_id)) {
            $aq = Aquereur::on('temp')->find($request->aq_id);

            if(!$aq) {
                return response()->json([
                    'error' => 'Aquereur not found',
                    'aq_id' => $request->aq_id
                ], 404);
            }

            $cl_id = $aq->client_id;
        }

        $aquereur = new AquereurDesistement();
        $aquereur->setConnection('temp');
        $aquereur->desistement_id = $request->desistement_id;
        $aquereur->pourcentage = $request->pourcentage;
        $aquereur->client_id = $cl_id;
        $aquereur->aq_id = $request->aq_id;
        $aquereur->type = $request->type_desisteur;

        if($aquereur->save()) {
            return response()->json(['Aquérreur' => $aquereur], 200);
        }

        return response()->json(['error' => 'Failed to save'], 500);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Server error',
            'message' => $e->getMessage()
        ], 500);
    }
}
    public function store_new_aquereurs_desistement(Request $request)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $nv_aq=new NouvelAquereurDesistement();
            $nv_aq->setConnection('temp');
            $nv_aq->desistement_id=$request->desistement_id;
            $nv_aq->cin=$request->cin;
            $nv_aq->nom=$request->nom;
            $nv_aq->prenom=$request->prenom;
            $nv_aq->telephone=$request->telephone;
            $nv_aq->pourcentage=$request->pourcentage;
            if($nv_aq->save()){
                return response()->json(['Aquérreur',$nv_aq],200);
            }
        }
        return  response()->json(['error','Unauthorized'],401);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $aquereur=Aquereur::on('temp')->where('id',$id)->get();

            return response()->json(['Aquérreur'=>$aquereur],200);
        }
        return  response()->json(['error','Unauthorized'],401);
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
    public function update(UpdateAquereurRequest $request, $id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $aquereur=Aquereur::on('temp')->findOrFail($id);
            $update=$request->all();
            foreach ($update as $key => $value){
                $aquereur->$key = $value;
            }
            $aquereur->save();
            return response()->json(['Aquérreur'=>$aquereur],200);
        }
        return  response()->json(['error','Unauthorized'],401);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $aquereur=Aquereur::on('temp')->findOrFail($id);

            if($aquereur->forceDelete()){
                return response()->json(['message'=>'Aquérreur supprimé avec succès'],200);
            }
            else{
                return response()->json(['message'=>'Aquérreur non supprimé'],400);
            }
        }
        return response()->json(['error'=>'Unauthorized'],401);
    }

    public function destroyAquerreursByReservationId($reservation_id){
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $aquereurs=Aquereur::on('temp')->where('reservation_id',$reservation_id)->get();
            foreach ($aquereurs as $aquereur){
                if($aquereur->forceDelete()){
                    return response()->json(['message'=>'Aquérreurs supprimés avec succès'],200);
                }
                else{
                    return response()->json(['message'=>'Aquérreurs non supprimés'],400);
                }
            }

        }

        return response()->json(['error'=>'Unauthorized'],401);
    }

    public function soft_destroy_aqueureurs_by_reservationId($reservation_id){
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $aquereurs=Aquereur::on('temp')->where('reservation_id',$reservation_id)->get();
            foreach ($aquereurs as $aquereur){
                $aquereur->delete();
            }
            return response()->json(['message'=>'Aquereurs supprimés avec succès'],200);

        }
        return response()->json(['error'=>'Unauthorized'],401);
    }




}
