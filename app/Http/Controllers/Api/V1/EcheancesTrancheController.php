<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreBanqueRequest;
use App\Http\Requests\UpdateBanqueRequest;
use App\Models\EcheancesTranche;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Tranche;
use Carbon\Carbon;
use App\Models\Societe;
use Illuminate\Support\Facades\File;
use DB;
class EcheancesTrancheController extends Controller
{
    /**
     * Display a listing of the resource.
     */


    public function get_tranches_without_echeances(Request $request, $projet_id)
    {
         if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $query = Tranche::on('temp')->where('projet_id', $projet_id)->doesntHave('EcheancesTranches');
            $tranches = $query->orderBy('created_at', 'desc')
                    ->get();
            return response()->json(['get_tranches_without_echeances' => $tranches], 200);

        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function indexByProjet(Request $request, $projet_id)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', null);
            $page = $request->input('page', null);
            DatabaseHelper::Config();

            // Démarrer la requête directement sur le modèle

            $query = Tranche::on('temp')->select('id','nom')
            ->where('projet_id', $projet_id)
            ->has('echeance_tranches')
            ->with('echeance_tranches')
            ->without('projet');

             if ($request->filled('tranche')) {
                 $query->where('nom', 'like', '%' . $request->input('tranche') . '%');
             }

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                $ech = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Extraire les propriétés du paginateur
                $pagination = [
                    'currentPage' => $ech->currentPage(),
                    'totalItems' => $ech->total(),
                    'totalPages' => $ech->lastPage(),
                ];

                // Extraire les éléments d'utilisateur du paginateur
                $ech = $ech->items();

                // Retourner la réponse simplifiée
                return response()->json([
                    'data' => $ech,
                    'pagination' => $pagination,
                ], 200);
            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }


    public function list_echeances_byTrancheId($tranche_id)
{
     if (Auth::guard('api')->check()) {
        DatabaseHelper::Config();
        $query = EcheancesTranche::on('temp')
            ->with('tranche:id,nom') // Charger seulement id et nom de la tranche
            ->where('tranche_id', $tranche_id);

        $echeances = $query->orderBy('created_at', 'asc')
                ->get();
        return response()->json(['echeances' => $echeances], 200);
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

    /**Store a newly created resource in storage.

    public function store(Request $request)
    {
         if (RoleHelper::AdminSup()) {
            $user = Auth::user();
            // Check if user is superadmin
            $isSuperAdmin = RoleHelper::SuperAdmin();

            // Switch to tenant database
            DatabaseHelper::Config();

            // Set user_id_add based on user type
            if ($isSuperAdmin) {
                // For superadmin, set user_id_add to 0
                $userId = 0;
            } else {
                // For regular users, get the tenant user
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();
                $userId = $userAuth->id;
            }
              $dataArray_inputs = json_decode($request->input('inputs_date_montant'), true);
            if ($dataArray_inputs) {
                foreach ($dataArray_inputs as $inputs) {
                    $ech = new EcheancesTranche();
                    $ech->setConnection('temp');
                    $ech->tranche_id = $request->tranche_id;
                    $ech->date = $inputs['date'];
                    $ech->montant =$inputs['montant'];
                    $ech->user_id= $userId;
                    $ech->save();
                }
            }
            return response()->json('is Done');
        }

        return response()->json(['error' => 'Unauthorized'], 401);

    }*/
          public function store(Request $request)
    {
        if (RoleHelper::AdminSup()) {
            $user = Auth::user();
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $dataArray_inputs = json_decode($request->input('inputs_date_montant'), true);
            if ($dataArray_inputs) {
                foreach ($dataArray_inputs as $inputs) {
                    $ech = new EcheancesTranche();
                    $ech->setConnection('temp');
                    $ech->tranche_id = $request->tranche_id;
                    $ech->date = $inputs['date'];
                    $ech->montant =$inputs['montant'];
                    $ech->user_id=$userAuth->value('id');
                    $ech->save();
                }
            }
            return response()->json('is Done');
        }

        return response()->json(['error' => 'Unauthorized'], 401);

    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $ech = EcheancesTranche::on('temp')->findOrFail($id);
            return response()->json(['ech' => $ech], 200);
        }
        return response()->json(['error', 'Unauthorized'], 401);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /* public function update(Request $request, $id)
    {
       if (RoleHelper::AdminSup()) {
            $user = Auth::user();
            // Check if user is superadmin
            $isSuperAdmin = RoleHelper::SuperAdmin();

            // Switch to tenant database
            DatabaseHelper::Config();

            // Set user_id_add based on user type
            if ($isSuperAdmin) {
                // For superadmin, set user_id_add to 0
                $userId = 0;
            } else {
                // For regular users, get the tenant user
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();
                $userId = $userAuth->id;
            }
             $ech_s=EcheancesTranche::on('temp')->where('tranche_id',$request->tranche_id)->get();
            if(count($ech_s)>0){
                foreach($ech_s as $ech){
                    $ech->delete();
                }
            }
            $dataArray_inputs = json_decode($request->input('inputs_date_montant'), true);
            if ($dataArray_inputs) {
                foreach ($dataArray_inputs as $inputs) {
                    $ech = new EcheancesTranche();
                    $ech->setConnection('temp');
                    $ech->tranche_id = $request->tranche_id;
                    $ech->date = $inputs['date'];
                    $ech->montant =$inputs['montant'];
                    $ech->user_id=$userId;
                    $ech->save();
                }
            }
            return response()->json('is Done');

        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }**/
    public function update(Request $request, $id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $user = Auth::user();
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $ech_s=EcheancesTranche::on('temp')->where('tranche_id',$request->tranche_id)->get();
            if(count($ech_s)>0){
                foreach($ech_s as $ech){
                    $ech->delete();
                }
            }
            $dataArray_inputs = json_decode($request->input('inputs_date_montant'), true);
            if ($dataArray_inputs) {
                foreach ($dataArray_inputs as $inputs) {
                    $ech = new EcheancesTranche();
                    $ech->setConnection('temp');
                    $ech->tranche_id = $request->tranche_id;
                    $ech->date = $inputs['date'];
                    $ech->montant =$inputs['montant'];
                    $ech->user_id=$userAuth->value('id');
                    $ech->save();
                }
            }
            return response()->json('is Done');

        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if (RoleHelper::AdminSup() ) {
            DatabaseHelper::Config();
            $ech_s=EcheancesTranche::on('temp')->where('tranche_id',$id)->get();
            if(count($ech_s)>0){
                foreach($ech_s as $ech){
                    $ech->delete();
                }
                return response()->json(['message' => 'Echéances Tranche Supprimé avec succés'], 200);
            }

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}
