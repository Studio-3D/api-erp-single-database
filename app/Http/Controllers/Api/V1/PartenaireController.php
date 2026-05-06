<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Partenaire;
use App\Http\Requests\StorePartenaireRequest;
use App\Http\Requests\UpdatePartenaireRequest;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use Illuminate\Http\Request;



class PartenaireController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function get_partenaires($projet_id)
    {

        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $partenaires = Partenaire::on('temp')->orderBy('created_at', 'desc')->where('projet_id',$projet_id)
                ->get();
            return response()->json(['partenaires' => $partenaires],200);
        }
        else{
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

            $query = Partenaire::on('temp');

            if ($projet_id) {
                $query->where('projet_id', $projet_id);
            }

            if ($request->filled('description')) {
                $query->where('description', 'like', '%' . $request->input('description') . '%');
            }
            if ($request->filled('remise')) {
                $query->where('remise', 'like', '%' . $request->input('remise') . '%');
            }
            $partenaires = $query->orderBy('created_at', 'desc')
                ->paginate($size, ['*'], 'page', $page);

            $pagination = [
                'currentPage' => $partenaires->currentPage(),
                'totalItems' => $partenaires->total(),
                'totalPages' => $partenaires->lastPage(),
            ];

            $partenaires = $partenaires->items();

            return response()->json([
                'partenaires' => $partenaires,
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

            $query = Partenaire::on('temp')->where('projet_id', $projet_id);

            if ($request->filled('description')) {
                $query->where('description', 'like', '%' . $request->input('description') . '%');
            }
            if ($request->filled('remise')) {
                $query->where('remise', 'like', '%' . $request->input('remise') . '%');
            }

            // Check if pagination parameters are provided and valid
            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                $partenaires = $query->with('client','prospect')->orderBy('created_at', 'desc')
                ->paginate($size, ['*'], 'page', $page);

                $pagination = [
                    'currentPage' => $partenaires->currentPage(),
                    'totalItems' => $partenaires->total(),
                    'totalPages' => $partenaires->lastPage(),
                ];

                $partenaires = $partenaires->items();

                return response()->json([
                    'data' => $partenaires,
                    'pagination' => $pagination,
                ], 200);
            } else {
                // Return all results if pagination parameters are not provided or invalid
                $partenaires = $query->orderBy('created_at', 'desc')
                    ->get();

                return response()->json(['partenaires' => $partenaires], 200);
            }
        }

        // Return unauthorized error if user is not authenticated
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Show the form for creating a new resource.
     */


    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePartenaireRequest $request)
    {

        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            //partenaire unique in this projects

                $partenaire = new Partenaire();
                $partenaire->setConnection('temp');
                $partenaire->description = $request->description;
                $partenaire->remise = $request->remise;
                $partenaire->projet_id = $request->projet_id;
                $partenaire->save();
                return response()->json(['message' => $partenaire], 200);



        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function store_multiple_partenaires (Request $request)
        {
            if (RoleHelper::AdminSup()) {
                DatabaseHelper::Config();
                $dataArray_donnees = json_decode($request->input('donneesPartenaire', '[]'), true);

                if ($dataArray_donnees) {
                    foreach ($dataArray_donnees as $Data) {  // Changed variable name
                        $s = new Partenaire();  // Keep this as $typologie
                        $s->setConnection('temp');
                        $s->description = $Data['description'];  // Use $typologieData here
                        $s->projet_id = $request->projet_id;
                        $s->save();
                    }
                }

                // Get all type biens created
                $part = Partenaire::on('temp')->get();
                return response()->json(['partenaires' => $part], 200);
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
            $partenaire = Partenaire::on('temp')->findOrfail($id);

            return response()->json(['partenaire' => $partenaire], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePartenaireRequest $request,  $id)
    {
        //unique partenaire
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $partenaire = Partenaire::on('temp')->findOrfail($id);
            $update = $request->all();
            foreach($update as $key => $value) {
                $partenaire->$key = $value;
            }
            $partenaire->save();

            return response()->json(['message' => $partenaire], 200);


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
            $partenaire = Partenaire::on('temp')->findOrfail($id);

            if ($partenaire->delete()) {
                return response()->json(['message' => 'ce Partenaire est supprimé avec Succés'], 200);
            } else {
                return response()->json(['message' => 'ce Partenaire non supprimé'], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function restorePartenaire($partenaire_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            Partenaire::on('temp')->where('id', $partenaire_id)->withTrashed()->restore();
            return response()->json(['message' => 'Partenaire bien restaurer'], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
   public static function AjouterPartenaire($partenaire, $projet_id)
{
    // Handle different input formats
    $description = null;
    $remise = null;

    if (is_array($partenaire)) {
        // If it's an array, try to get description and remise
        $description = $partenaire['description'] ?? $partenaire['name'] ?? $partenaire['nom'] ?? null;
        $remise = $partenaire['remise'] ?? $partenaire['discount'] ?? 0;
    } elseif (is_string($partenaire)) {
        // If it's just a string, use it as description
        $description = $partenaire;
        $remise = 0;
    }

    // Skip if no description
    if (empty($description)) {
        return;
    }

    $partenaireController = new PartenaireController();
    $partenaireRequest = new StorePartenaireRequest();

    $dataPartenaire = [
        'description' => $description,
        'remise' => $remise,
        'projet_id' => $projet_id,
    ];

    $partenaireRequest->merge($dataPartenaire);
    $partenaireController->store($partenaireRequest);
}


}
