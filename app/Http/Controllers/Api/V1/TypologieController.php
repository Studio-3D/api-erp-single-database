<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreTypologieRequest;
use App\Http\Requests\UpdateTypologieRequest;
use App\Models\Typologie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TypologieController extends Controller
{
    /**
     * Display a listing of the resource.
     */
  public function store_multiple_typologies (Request $request)
{
    if (RoleHelper::AdminSup()) {
        DatabaseHelper::Config();
        $dataArray_donnees = json_decode($request->input('donneesTypologie', '[]'), true);

        if ($dataArray_donnees) {
            foreach ($dataArray_donnees as $typologieData) {  // Changed variable name
                $typologie = new Typologie();  // Keep this as $typologie
                $typologie->setConnection('temp');
                $typologie->typologie = $typologieData['typologie'];  // Use $typologieData here
                $typologie->projet_id = $request->projet_id;
                $typologie->save();
            }
        }

        // Get all type biens created
        $typologies = Typologie::on('temp')->where('projet_id', $request->projet_id)->get();
        return response()->json(['typologies' => $typologies], 200);
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

            $query = Typologie::on('temp');

            if ($projet_id) {
                $query->where('projet_id', $projet_id);
            }

            if ($request->filled('typologie')) {
                $query->where('typologie', 'like', '%' . $request->input('typologie') . '%');
            }

            $typologies = $query->orderBy('created_at', 'desc')
                ->paginate($size, ['*'], 'page', $page);

            $pagination = [
                'currentPage' => $typologies->currentPage(),
                'totalItems' => $typologies->total(),
                'totalPages' => $typologies->lastPage(),
            ];

            $typologies = $typologies->items();

            return response()->json([
                'typologies' => $typologies,
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

            $query = Typologie::on('temp')->where('projet_id', $projet_id);



            if ($request->filled('typologie')) {
                $query->where('typologie', 'like', '%' . $request->input('typologie') . '%');
            }

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {

                $typologies = $query->with('frein_typologies','bien')->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                $pagination = [
                    'currentPage' => $typologies->currentPage(),
                    'totalItems' => $typologies->total(),
                    'totalPages' => $typologies->lastPage(),
                ];

                $typologies = $typologies->items();

                return response()->json([
                    'data' => $typologies,
                    'pagination' => $pagination,
                ], 200);
            } else {
            $typologies = $query->orderBy('created_at', 'desc')
            ->get();
            return response()->json(['typologies' => $typologies]);

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
    public function store(StoreTypologieRequest $request)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $typologie = new Typologie();
            $typologie->setConnection('temp');
            $typologie->typologie = $request->typologie;
            $typologie->projet_id = $request->projet_id;
            $typologie->save();
            return response()->json(['typologie' => $typologie], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }
    public function get_typologiesByProjet($projet_id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();

            $typologies = Typologie::on('temp')
                ->orderBy('created_at', 'desc')
                ->where('projet_id', $projet_id)
                ->get();
            return response()->json(['typologies' => $typologies]);
        }

        return response()->json(['error' => 'Unauthorized'], 401);

    }
    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $typologie = Typologie::on('temp')->findOrfail($id);
            return response()->json(['typologie' => $typologie], 200);
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
    public function update(UpdateTypologieRequest $request, $id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $typologie = Typologie::on('temp')->findOrFail($id);
            $update = $request->all();
            foreach ($update as $key => $value) {
                $typologie->$key = $value;
            }
            $typologie->save();
            return response()->json(['typologie' => $typologie], 200);
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
            $typologie = Typologie::on('temp')->findOrFail($id);
            if ($typologie->delete()) {
                return response()->json(['message' => 'Typologie supprimée avec succès.'], 200);
            } else {
                return response()->json(['error' => "La typologie n'a pas été supprimée."], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public static function AjouterTypologie($typologie, $projet_id)
    {
           // If $typeBien is an array, extract the type field
            if (is_array($typologie)) {
                $typeValue = $typologie['type'] ?? null;
            } else {
                $typeValue = $typologie;
            }

            if (empty($typeValue)) {
                return; // Skip empty values
            }

            $typologieController = new TypologieController();
            $typologieRequest = new StoreTypologieRequest();

                $dataTypologie = [
                'typologie' => $typeValue,
                'projet_id' => $projet_id,
                ];
            $typologieRequest->merge($dataTypologie);
            $typologieController->store($typologieRequest);



    }


}
