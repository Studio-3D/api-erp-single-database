<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreBanqueRequest;
use App\Http\Requests\UpdateBanqueRequest;
use App\Models\Cps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use App\Models\Societe;
use Illuminate\Support\Facades\File;
class CpsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function indexByProjet(Request $request, $projet_id)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', null);
            $page = $request->input('page', null);
            DatabaseHelper::Config();

            // Démarrer la requête directement sur le modèle
            $query = Cps::on('temp')->where('projet_id', $projet_id);

            if ($request->filled('nature_travaux')) {
                $query->where('nature_travaux', 'like', '%' . $request->input('nature_travaux') . '%');
            }

            if ($request->filled('cout')) {
                $query->where('cout',  $request->input('cout') );
            }
            if ($request->filled('date_validation')) {
                $start = Carbon::parse($request->input('date_validation'));
                $query->whereDate('date_validation', $start);
            }

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                $cps = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Extraire les propriétés du paginateur
                $pagination = [
                    'currentPage' => $cps->currentPage(),
                    'totalItems' => $cps->total(),
                    'totalPages' => $cps->lastPage(),
                ];

                // Extraire les éléments d'utilisateur du paginateur
                $cps = $cps->items();

                // Retourner la réponse simplifiée
                return response()->json([
                    'data' => $cps,
                    'pagination' => $pagination,
                ], 200);
            } else {
                // Return all results if pagination parameters are not provided or invalid
                $cps = $query->orderBy('created_at', 'desc')
                    ->get();

                return response()->json(['cps' => $cps], 200);
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
    public function store(Request $request)
    {

        if (RoleHelper::AdminSup()) {
            $user = Auth::user();
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
            $societe = Societe::findOrfail($user_societes->societe_id);
            $cps = new Cps();
            $cps->setConnection('temp');
            $cps->nature_travaux = $request->nature_travaux;
            $cps->cout = $request->cout;
            $cps->date_validation = $request->date_validation;
            $cps->projet_id = $request->projet_id;
            $cps->user_id=$userAuth->value('id');
            if ($request->hasFile('piece_jointe')) {
                $cps->piece_jointe = $request->file('piece_jointe')->getClientOriginalName();;
                $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/cps');
                File::makeDirectory($directory, 0755, true, true);
                $request->file('piece_jointe')->move($directory,$request->file('piece_jointe')->getClientOriginalName());
            }
            if ($cps->save()) {
                return response()->json(['cps' => $cps], 200);
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
            $cps = Cps::on('temp')->findOrFail($id);
            return response()->json(['cps' => $cps], 200);
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
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();

            $user = Auth::user();
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
            $societe = Societe::findOrfail($user_societes->societe_id);
            $cps = Cps::on('temp')->findOrFail($id);
            $cps->nature_travaux = $request->nature_travaux;
            $cps->cout = $request->cout;
            $cps->date_validation = $request->date_validation;
            $cps->projet_id = $request->projet_id;
            $cps->user_id=$userAuth->value('id');
            if ($request->hasFile('piece_jointe')) {
                $cps->piece_jointe = $request->file('piece_jointe')->getClientOriginalName();;
                $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/cps');
                File::makeDirectory($directory, 0755, true, true);
                $request->file('piece_jointe')->move($directory,$request->file('piece_jointe')->getClientOriginalName());
            }
            if ($cps->save()) {
                return response()->json(['cps' => $cps], 200);
            }
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
            $cps = Cps::on('temp')->findOrFail($id);
            if ($cps->delete()) {
                return response()->json(['message' => 'Cps Supprimé avec succés'], 200);
            } else {
                return response()->json(['message' => 'Cps Non Suprimé'], 400);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}

