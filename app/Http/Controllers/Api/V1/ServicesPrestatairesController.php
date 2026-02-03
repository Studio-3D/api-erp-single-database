<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Models\Prestataire;
use App\Models\ServicesPrestataires;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ServicesPrestatairesController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index(Request $request, $projet_id)
    {
        if (RoleHelper::AdminSavSup()) {
            $size = $request->input('size', null);
            $page = $request->input('page', null);
            DatabaseHelper::Config();

            // Démarrer la requête directement sur le modèle
            $query = ServicesPrestataires::on('temp')->with('prestataires');

            if ($request->filled('nom')) {
                $query->where('nom', 'like', '%' . $request->input('nom') . '%');
            }

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                $ser = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Extraire les propriétés du paginateur
                $pagination = [
                    'currentPage' => $ser->currentPage(),
                    'totalItems'  => $ser->total(),
                    'totalPages'  => $ser->lastPage(),
                ];

                // Extraire les éléments d'utilisateur du paginateur
                $ser = $ser->items();

                // Retourner la réponse simplifiée
                return response()->json([
                    'data'   => $ser,
                    'pagination' => $pagination,
                ], 200);
            } else {
                // Return all results if pagination parameters are not provided or invalid
                $ser = $query->orderBy('created_at', 'desc')
                    ->get();

                return response()->json(['services' => $ser], 200);
            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }
    public function get_services(Request $request)
    {
        if (RoleHelper::AdminSavSup()) {
            DatabaseHelper::Config();
            $ser = ServicesPrestataires::on('temp')->with('prestataires')->orderBy('id', 'asc')->get();
            return response()->json(['services' => $ser], 200);
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
    public function store(Request $request)
    {

        if (RoleHelper::AdminSavSup()) {
            DatabaseHelper::Config();
            $ser = new ServicesPrestataires();
            $ser->setConnection('temp');
            $ser->nom = $request->nom;
            if ($ser->save()) {
                return response()->json(['service' => $ser], 200);
            }
        }
        return response()->json(['error' => 'Unauthorized'], 401);

    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (RoleHelper::AdminSavSup()) {
            DatabaseHelper::Config();
            $ser = ServicesPrestataires::on('temp')->findOrFail($id);
            return response()->json(['ser' => $ser], 200);
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

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        if (RoleHelper::AdminSavSup()) {
            DatabaseHelper::Config();
            $ser = ServicesPrestataires::on('temp')->findOrfail($id);
            $ser->setConnection('temp');
            $ser->nom = $request->nom;
            if ($ser->save()) {
                return response()->json(['service' => $ser], 200);
            }

        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if (RoleHelper::AdminSavSup()) {
            DatabaseHelper::Config();
            $ser          = ServicesPrestataires::on('temp')->findOrFail($id);
            $prestataires = Prestataire::on('temp')->where('service_id', $id)->get();
            if (count($prestataires) > 0) {
                foreach ($prestataires as $pre) {
                    $preController = new PrestatairesController();
                    $preController->destroy($pre->id);
                }
            }
            if ($ser->delete()) {
                return response()->json(['message' => 'Service Supprimé avec succés'], 200);
            } else {
                return response()->json(['message' => 'Service Non Suprimé'], 400);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}
