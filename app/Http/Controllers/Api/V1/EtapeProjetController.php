<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreBanqueRequest;
use App\Http\Requests\UpdateBanqueRequest;
use App\Models\EcheanceProjet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use App\Models\Societe;
use Illuminate\Support\Facades\File;
class EtapeProjetController extends Controller
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
            $query = EcheanceProjet::on('temp')->where('projet_id', $projet_id);


            if ($request->filled('etat')) {
                $query->where('etat',  $request->input('etat') );
            }

            if ($request->filled('date_debut') && $request->filled('date_fin')) {

                $dt = Carbon ::parse($request->input('date_debut'))->format('Y-m-d');
                $a_dt = Carbon::parse($request->input('date_fin'))->format('Y-m-d');
                $query->whereDate('date_debut','>=',$dt);
                $query->whereDate('date_fin','<=',$a_dt);

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
            $ech = new EcheanceProjet();
            $ech->setConnection('temp');
            $ech->description = $request->description;
            $ech->date_debut_prevu = $request->date_debut_prevu;
            $ech->date_fin_prevu = $request->date_fin_prevu;
            $ech->projet_id = $request->projet_id;
            $ech->etat='0';//non commencé
            $ech->user_id=$userAuth->value('id');
            if ($ech->save()) {
                return response()->json(['ech' => $ech], 200);
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
            $ech = EcheanceProjet::on('temp')->findOrFail($id);
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

    /**
     * Update the specified resource in storage.
     */
   public function update(Request $request, $id)
{
    if (RoleHelper::AdminSup()) {
        DatabaseHelper::Config();

        $user = Auth::user();
        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();

        $ech = EcheanceProjet::on('temp')->findOrFail($id);

        // Only update fields that are provided in the request
        if ($request->has('description')) {
            $ech->description = $request->description;
        }
        if ($request->has('date_debut')) {
            $ech->date_debut = $request->date_debut;
        }
        if ($request->has('date_fin')) {
            $ech->date_fin = $request->date_fin;
        }
        if ($request->has('date_fin_prevu')) {
            $ech->date_fin_prevu = $request->date_fin_prevu;
        }
        if ($request->has('date_debut_prevu')) {
            $ech->date_debut_prevu = $request->date_debut_prevu; // Fixed typo: date_debut_preu -> date_debut_prevu
        }
        if ($request->has('projet_id')) {
            $ech->projet_id = $request->projet_id;
        }
        if ($request->has('etat')) {
            $ech->etat = $request->etat;
        }

        $ech->user_id = $userAuth->id;

        if ($ech->save()) {
            return response()->json(['ech' => $ech], 200);
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
            $ech = EcheanceProjet::on('temp')->findOrFail($id);
            if ($ech->delete()) {
                return response()->json(['message' => 'Etape Supprimé avec succés'], 200);
            } else {
                return response()->json(['message' => 'Etape Non Suprimé'], 400);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}

