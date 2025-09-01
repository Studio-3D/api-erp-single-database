<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreDecompteRequest;
use App\Models\Decompte;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Carbon\Carbon;
use App\Models\Facture;


class DecompteController extends Controller
{
    /**
     * Display a listing of the resource.
     */



    public function indexByProjet(Request $request, $projet_id)
    {
        if (Auth::guard('api')->check()) {
            // Default values for pagination null si non pas envoyer avec la raquete
            $size = $request->input('size', null);
            $page = $request->input('page', null);

            DatabaseHelper::Config();

            $query = Decompte::on('temp')->with('factures')->withSum('factures', 'montant')->where('projet_id', $projet_id);
            if ($request->filled('numero')) {
                $query->where('numero', 'like', '%' . $request->input('numero') . '%');
            }
            if ($request->filled('montant')) {
                $query->where('montant', 'like', '%' . $request->input('montant') . '%');
            }
            if ($request->filled('date')) {
                $start = Carbon::parse($request->input('date'));
                $query->whereDate('date','>=', $start);
            }
            // Check if pagination parameters are provided and valid
            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                // Paginate the query results
                $decomptes = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                $pagination = [
                    'currentPage' => $decomptes->currentPage(),
                    'totalItems' => $decomptes->total(),
                    'totalPages' => $decomptes->lastPage(),
                ];

                $dItems = $decomptes->items();

                // Return the response with pagination
                return response()->json([
                    'data' => $dItems,
                    'pagination' => $pagination,
                ], 200);
            }
            else{
                  // Return all results if pagination parameters are not provided or invalid
                  $decomptes = $query->orderBy('created_at', 'desc')
                  ->get();
              return response()->json(['decomptes' => $decomptes], 200);
            }
        }

        // Return unauthorized error if user is not authenticated
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function store(StoreDecompteRequest $request)
    {
        if (RoleHelper::AdminSup()) {
            $user = Auth::user();
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $dec = new Decompte();
            $dec->setConnection('temp');
            $dec->numero = $request->numero;
            $dec->date = $request->date;
            $dec->montant = $request->montant;
            $dec->projet_id = $request->projet_id;
            $dec->user_id=$userAuth->value('id');
            $dec->save();

            return response()->json(['message' => $dec], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function decomptes_in_facture($projet_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $decomptes = Decompte::on('temp')->withSum('factures', 'montant')->where('projet_id',$projet_id)->get();
            $dec_facture = array();
            foreach($decomptes as $dc){
                if($dc->factures_sum_montant<$dc->montant||$dc->factures_sum_montant==null){
                    array_push($dec_facture,array('id' => $dc->id,
                              'projet_id' => $dc->projet_id,
                              'numero' => $dc->numero,
                              'date' => $dc->date,
                              'montant' => $dc->montant,
                              'factures_sum_montant'=>$dc->factures_sum_montant));
                }

            }

            return response()->json(['decomptes' => $dec_facture], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function show($id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $decompte = Decompte::on('temp')->withSum('factures','montant')->findOrfail($id);
            return response()->json(['decompte' => $decompte], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function update(StoreDecompteRequest $request, $id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $decompte = Decompte::on('temp')->findOrFail($id);
            $update = $request->all();
            foreach ($update as $key => $value) {
                $decompte->$key = $value;
            }
            $decompte->save();
            return response()->json(['decompte' => $decompte], 200);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Store a newly created resource in storage.
     */


    /**
     * Display the specified resource.
     */


    /**
     * Show the form for editing the specified resource.
     */


    /**
     * Update the specified resource in storage.
     */


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if (RoleHelper::SuperAdmin() || RoleHelper::Comptable() || RoleHelper::AdminComptable()) {
            DatabaseHelper::Config();
            $dec = Decompte::on('temp')->findOrFail($id);
                $factures=Facture::on('temp')->where('decompte_id',$id)->get();
                if(count($factures)>0){
                foreach($factures as $fact){
                    $fact->delete();
                }
                }
            if ($dec->delete()) {
                return response()->json(['message' => 'Décompte supprimé avec succès'], 200);
            } else {
                return response()->json(['message' => 'Décompte non supprimé'], 400);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }


    public function get_info_numero_decompte_unique($id,$num)
    {
            if(RoleHelper::ACSup()){
                $user = Auth::user();
                DatabaseHelper::Config();

                if($id!=0){
                   $info_count=Decompte::on('temp')->where('numero',$num)->where('id','!=',$id)->count();
                }else{
                   $info_count=Decompte::on('temp')->where('numero',$num)->count();
                }
                return response()->json(['info_count' => $info_count]);


            } else {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
    }


}
