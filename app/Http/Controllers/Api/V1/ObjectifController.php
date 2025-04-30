<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Models\Objectif;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ObjectifController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function indexByProjet(Request $request)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', null);
            $page = $request->input('page', null);

            DatabaseHelper::Config();

            // Démarrer la requête directement sur le modèle
            $query = Objectif::on('temp')->with('user');

            if ($request->filled('date')) {
                $date = Carbon::parse($request->input('date'));
                $query->whereDate('created_at', $date);
            }

            if ($request->filled('commercial')) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->input('commercial') . '%')
                            ->orWhere('prenom', 'like', '%' . $request->input('commercial') . '%');
                    });
                });
            }

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {

                $obj = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Extraire les propriétés du paginateur
                $pagination = [
                    'currentPage' => $obj->currentPage(),
                    'totalItems'  => $obj->total(),
                    'totalPages'  => $obj->lastPage(),
                ];

                // Extraire les éléments d'utilisateur du paginateur
                $obj = $obj->items();

                // Retourner la réponse simplifiée
                return response()->json([
                    'data'       => $obj,
                    'pagination' => $pagination,
                ], 200);
            } else {
                // Return all results if pagination parameters are not provided or invalid
                $obj = $query->orderBy('created_at', 'desc')
                    ->get();

                return response()->json(['obj' => $obj], 200);
            }

        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Show the form for creating a new resource.
     */

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        // return response()->json(['app' =>json_decode($request->input('appels', '[]'), true)]);
        if (RoleHelper::AdminSup()) {

            DatabaseHelper::Config();
            $user     = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $obj      = new Objectif();
            $obj->setConnection('temp');
            $obj->projet_id   = $request->projet_id;
            $obj->user_id     = $request->user_id;
            $obj->user_id_add = $userAuth->value('id');
            $obj->visites = $request->visites;
            $obj->appels = $request->appels;
            $obj->reservations = $request->reservations;

            $obj->save();

            return response()->json(['objectif' => $obj], 200);

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
            $obj = Objectif::on('temp')->findOrfail($id);
            return response()->json(['objectif' => $obj], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    /*public function edit($id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $typeprojet = typeprojet::on('temp')->findOrfail($id);
            return response()->json(['typeProjet' => $typeprojet], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Update the specified resource in storage.*/

    public function update(Request $request, $id)
    {
        //return response()->json(json_decode($request->input('reservations', '[]'), true));

        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $obj            = Objectif::on('temp')->findOrfail($id);
            $obj->projet_id = $request->projet_id;
            $obj->user_id   = $request->user_id;
            
            $obj->visites = $request->visites;
            $obj->appels = $request->appels;
            $obj->reservations = $request->reservations;

            $obj->save();

            return response()->json(['objectif' => $obj], 200);
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
            $obj = Objectif::on('temp')->findOrfail($id);
            if ($obj->delete()) {
                return response()->json(['message' => 'objectif supprimé succesfully'], 200);
            } else {
                return response()->json(['message' => 'objectif non supprimé'], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

}
