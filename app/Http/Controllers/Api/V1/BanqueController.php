<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreBanqueRequest;
use App\Http\Requests\UpdateBanqueRequest;
use App\Models\Banque;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;

class BanqueController extends Controller
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
            $query = Banque::on('temp');

            if ($request->filled('nom')) {
                $query->where('nom', 'like', '%' . $request->input('nom') . '%');
            }

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                $banques = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Extraire les propriétés du paginateur
                $pagination = [
                    'currentPage' => $banques->currentPage(),
                    'totalItems' => $banques->total(),
                    'totalPages' => $banques->lastPage(),
                ];

                // Extraire les éléments d'utilisateur du paginateur
                $banques = $banques->items();

                // Retourner la réponse simplifiée
                return response()->json([
                    'banques' => $banques,
                    'pagination' => $pagination,
                ], 200);
            } else {
                // Return all results if pagination parameters are not provided or invalid
                $banques = $query->orderBy('created_at', 'desc')
                    ->get();

                return response()->json(['banques' => $banques], 200);
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
    public function store(StoreBanqueRequest $request)
    {
        if (RoleHelper::SuperAdmin() || RoleHelper::Comptable() || RoleHelper::AdminComptable()) {
            DatabaseHelper::Config();
            $banque = new Banque();
            $banque->setConnection('temp');
            $banque->nom = $request->nom;
            if ($banque->save()) {
                return response()->json(['banque' => $banque], 200);
            }
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
            $banque = Banque::on('temp')->findOrFail($id);
            return response()->json(['banque' => $banque], 200);
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
    public function update(UpdateBanqueRequest $request, $id)
    {
        if (RoleHelper::SuperAdmin() || RoleHelper::Comptable() || RoleHelper::AdminComptable()) {
            DatabaseHelper::Config();
            $banque = Banque::on('temp')->findOrFail($id);
            $update = $request->all();
            foreach ($update as $key => $value) {
                $banque->$key = $value;
            }
            $banque->save();
            return response()->json(['banque' => $banque], 200);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if (RoleHelper::SuperAdmin() || RoleHelper::Comptable() || RoleHelper::AdminComptable()) {
            DatabaseHelper::Config();
            $banque = Banque::on('temp')->findOrFail($id);
            if ($banque->delete()) {
                return response()->json(['message' => 'Bank deleted successfully'], 200);
            } else {
                return response()->json(['message' => 'Bank non deleted'], 400);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}

