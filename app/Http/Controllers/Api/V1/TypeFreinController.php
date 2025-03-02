<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TypeFrein;
use App\Http\Requests\StoreTypeFreinRequest;
use App\Http\Requests\UpdateTypeFreinRequest;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use Illuminate\Http\Request;



class TypeFreinController extends Controller
{
    /**
     * Display a listing of the resource.
     */
        public function index(Request $request)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', null);
            $page = $request->input('page', null);
            DatabaseHelper::Config();

            // Démarrer la requête directement sur le modèle
            $query = TypeFrein::on('temp');
            if ($request->filled('description')) {
                $query->where('description', 'like', '%' . $request->input('description') . '%');
            }
            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                $typeFreins = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Extraire les propriétés du paginateur
                $pagination = [
                    'currentPage' => $typeFreins->currentPage(),
                    'totalItems' => $typeFreins->total(),
                    'totalPages' => $typeFreins->lastPage(),
                ];

                // Extraire les éléments d'utilisateur du paginateur
                $typeFreins = $typeFreins->items();

                // Retourner la réponse simplifiée
                return response()->json([
                    'data' => $typeFreins,
                    'pagination' => $pagination,
                ], 200);
            } else {
                // Return all results if pagination parameters are not provided or invalid
                $typeFreins = $query->orderBy('created_at', 'desc')
                    ->get();

                return response()->json(['typefreins' => $typeFreins], 200);
            }

        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function get_typeFreins()
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $typefreins = TypeFrein::on('temp')->orderBy('created_at', 'desc')->get();
            return response()->json(['typeFreins' => $typefreins]);
        }

        return response()->json(['error' => 'Unauthorized'], 401);

    }

    /**
     * Show the form for creating a new resource.
     */


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTypeFreinRequest $request)
    {

        if (RoleHelper::AdminSup()) {

            DatabaseHelper::Config();
            $typefrein = new TypeFrein();
            $typefrein->setConnection('temp');
            $typefrein->description = $request->description;
            $typefrein->save();
            return response()->json(['message' => $typefrein], 200);
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
            $typefrein = TypeFrein::on('temp')->findOrfail($id);

            return response()->json(['typefrein' => $typefrein], 200);
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
    public function update(UpdateTypeFreinRequest $request,  $id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $typefrein = TypeFrein::on('temp')->findOrfail($id);
            $update = $request->all();
            foreach($update as $key => $value) {
                $typefrein->$key = $value;
            }
            $typefrein->save();

            return response()->json(['message' => $typefrein], 200);
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
            $typefrein = TypeFrein::on('temp')->findOrfail($id);

            if ($typefrein->delete()) {
                return response()->json(['message' => 'ce frein est supprimé succesfully'], 200);
            } else {
                return response()->json(['message' => 'ce frein non supprimé'], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function restoreTypeFrein($typefrein_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            TypeFrein::on('temp')->where('id', $typefrein_id)->withTrashed()->restore();

            return response()->json(['message' => 'Frein bien restaurer'], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }


}
