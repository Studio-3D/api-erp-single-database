<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreVueRequest;
use App\Http\Requests\UpdateVueRequest;
use App\Models\Vue;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
      public function store_multiple_vues (Request $request)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $dataArray_donnees = json_decode($request->input('donneesVue', '[]'), true);
            if ($dataArray_donnees) {
                    foreach ($dataArray_donnees as $vueData) {
                        $vue = new Vue();
                        $vue->setConnection('temp');
                        $vue->vue = $vueData['vue'];
                        $vue->projet_id = $request->projet_id;
                        $vue->save();
                    }
                }
            //get all type biens created
            $vues=Vue::on('temp')->where('projet_id',$request->projet_id)->get();
            return response()->json(['vues' => $vues], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function index(Request $request)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', config('app.default_item_number_perpage'));
            $page = $request->input('page', 1);
            $projet_id = $request->input('projet_id');
            DatabaseHelper::Config();

            $query = Vue::on('temp');

            if ($projet_id) {
                $query->where('projet_id', $projet_id);
            }

            if ($request->filled('vue')) {
                $query->where('vue', 'like', '%' . $request->input('vue') . '%');
            }

            $vues = $query->orderBy('created_at', 'desc')
                ->paginate($size, ['*'], 'page', $page);

            $pagination = [
                'currentPage' => $vues->currentPage(),
                'totalItems' => $vues->total(),
                'totalPages' => $vues->lastPage(),
            ];

            $vues = $vues->items();

            return response()->json([
                'vues' => $vues,
                'pagination' => $pagination,
            ], 200);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }


    public function indexByProjet(Request $request, $projet_id)
    {
        if (Auth::guard('api')->check()) {
            // Default values for pagination null si non pas envoyer avec la raquete
            $size = $request->input('size', null);
            $page = $request->input('page', null);

            DatabaseHelper::Config();

            $query = Vue::on('temp')->where('projet_id', $projet_id);

            if ($request->filled('vue')) {
                $query->where('vue', 'like', '%' . $request->input('vue') . '%');
            }

            // Check if pagination parameters are provided and valid
            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                // Paginate the query results
                $vues = $query->with('bien','frein_vue')->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                $pagination = [
                    'currentPage' => $vues->currentPage(),
                    'totalItems' => $vues->total(),
                    'totalPages' => $vues->lastPage(),
                ];

                $vuesItems = $vues->items();

                // Return the response with pagination
                return response()->json([
                    'data' => $vuesItems,
                    'pagination' => $pagination,
                ], 200);
            } else {
                // Return all results if pagination parameters are not provided or invalid
                $vues = $query->orderBy('created_at', 'desc')
                    ->get();

                return response()->json(['vues' => $vues], 200);
            }
        }

        // Return unauthorized error if user is not authenticated
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    public function get_vuesByProjet($projet_id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();

            $vues = Vue::on('temp')
            ->orderBy('created_at', 'desc')
            ->where('projet_id', $projet_id)
            ->get();
            return response()->json(['vues' => $vues]);
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
    public function store(StoreVueRequest $request)
    {

        if(RoleHelper::AdminSup()){
            DatabaseHelper::Config();
            $vue=new Vue();
            $vue->setConnection('temp');
            $vue->vue=$request->vue;
            $vue->projet_id=$request->projet_id;
            $vue->save();
            return response()->json(['vue'=>$vue],200);
        }
        else  return response()->json(['error' => 'Unauthorized'], 401);

    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $vue = Vue::on('temp')->findOrfail($id);
            return response()->json(['vue' => $vue], 200);
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
    public function update(UpdateVueRequest $request,$id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $vue=Vue::on('temp')->findOrFail($id);
            $update=$request->all();
            foreach($update as $key => $value){
                $vue->$key= $value;
            }
            $vue->save();
            return response()->json(['vue'=>$vue],200);
        }
        else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if(RoleHelper::AdminSup()){
            DatabaseHelper::Config();
            $vue=Vue::on('temp')->findOrFail($id);
            if($vue->delete())
            {
                return response()->json(['message'=>'Vue supprimée avec succès.'],200);
            }
            else{
                return response()->json(['error'=>"La vue n'a pas été supprimée."],404);
            }
        }
        else{
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public static function AjouterVue($vues, $projet_id)
    {
        if (is_array($vues)) {
                $typeValue = $vues['type'] ?? null;
            } else {
                $typeValue = $vues;
            }

            if (empty($typeValue)) {
                return; // Skip empty values
            }
            $vueController = new VueController();
            $vueRequest = new StoreVueRequest();

                $datavue = [
                'vue' => $typeValue,
                'projet_id' => $projet_id,
                ];
            $vueRequest->merge($datavue);
            $vueController->store($vueRequest);



    }




}
