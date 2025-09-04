<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreCreditRequest;
use App\Http\Requests\UpdateCreditRequest;
use App\Models\Credit;
use App\Models\Societe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class CreditsController extends Controller
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
            $query = Credit::on('temp')->where('projet_id', $projet_id);

            if ($request->filled('num_contrat')) {
                $query->where('num_contrat', 'like', '%' . $request->input('num_contrat') . '%');
            }

            if ($request->filled('taux_interet')) {
                $query->where('taux_interet',  $request->input('taux_interet') );
            }
            if ($request->filled('date')) {
                $start = Carbon::parse($request->input('date'));
                $query->whereDate('date', $start);
            }
            if ($request->filled('de')) {
                $start = Carbon::parse($request->input('de'));
                $query->whereDate('de', '>=',$start);
            }
            if ($request->filled('a')) {
                $end = Carbon::parse($request->input('a'));
                $query->whereDate('a','<=', $end);
            }

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                $credits = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Extraire les propriétés du paginateur
                $pagination = [
                    'currentPage' => $credits->currentPage(),
                    'totalItems' => $credits->total(),
                    'totalPages' => $credits->lastPage(),
                ];

                // Extraire les éléments d'utilisateur du paginateur
                $credits = $credits->items();

                // Retourner la réponse simplifiée
                return response()->json([
                    'data' => $credits,
                    'pagination' => $pagination,
                ], 200);
            } else {
                // Return all results if pagination parameters are not provided or invalid
                $credits = $query->orderBy('created_at', 'desc')
                    ->get();

                return response()->json(['credits' => $credits], 200);
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
            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
            $societe = Societe::findOrfail($user_societes->societe_id);
            $cr = new Credit();
            $cr->setConnection('temp');
            $cr->banque_id = $request->banque_id;
            $cr->num_contrat = $request->num_contrat;

           if ($request->hasFile('piece_jointe')) {
                $cr->piece_jointe = $request->file('piece_jointe')->getClientOriginalName();;
                $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/credits');
                File::makeDirectory($directory, 0755, true, true);
                $request->file('piece_jointe')->move($directory,$request->file('piece_jointe')->getClientOriginalName());
            }

            $cr->date = $request->date;
            $cr->montant_capital = $request->montant_capital;
            $cr->frais_dossier = $request->frais_dossier;
            $cr->de = $request->de;
            $cr->a = $request->a;
            $cr->nb_mois = $request->nb_mois;
            $cr->taux_interet = $request->taux_interet;
            $cr->montant_interet = $request->montant_interet;
            $cr->projet_id = $request->projet_id;
            $cr->user_id=$userAuth->value('id');
            if ($cr->save()) {
                return response()->json(['cr' => $cr], 200);
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
            $cr = Credit::on('temp')->findOrFail($id);
            return response()->json(['credit' => $cr], 200);
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
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
            $societe = Societe::findOrfail($user_societes->societe_id);
            $cr = Credit::on('temp')->findOrFail($id);
            $cr->setConnection('temp');
            $cr->banque_id = $request->banque_id;
            $cr->num_contrat = $request->num_contrat;

           if ($request->hasFile('piece_jointe')) {
                $cr->piece_jointe = $request->file('piece_jointe')->getClientOriginalName();;
                $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/credits');
                File::makeDirectory($directory, 0755, true, true);
                $request->file('piece_jointe')->move($directory,$request->file('piece_jointe')->getClientOriginalName());
            }

            $cr->date = $request->date;
            $cr->montant_capital = $request->montant_capital;
            $cr->frais_dossier = $request->frais_dossier;
            $cr->de = $request->de;
            $cr->a = $request->a;
            $cr->nb_mois = $request->nb_mois;
            $cr->taux_interet = $request->taux_interet;
            $cr->montant_interet = $request->montant_interet;

            if ($cr->save()) {
                return response()->json(['cr' => $cr], 200);
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
            $cr = Credit::on('temp')->findOrFail($id);
            if ($cr->delete()) {
                return response()->json(['message' => 'Credit Supprimé avec succés'], 200);
            } else {
                return response()->json(['message' => 'Credit Non Suprimé'], 400);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function get_info_numero_credit_unique($id,$num)
    {
            if(RoleHelper::ACSup()){
                $user = Auth::user();
                DatabaseHelper::Config();

                if($id!=0){
                   $info_count=Credit::on('temp')->where('num_contrat',$num)->where('id','!=',$id)->count();
                }else{
                   $info_count=Credit::on('temp')->where('num_contrat',$num)->count();
                }
                return response()->json(['info_count' => $info_count]);


            } else {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
    }
}

