<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreFournisseurRequest;
use App\Models\Fournisseur;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use App\Models\Societe;
use App\Models\Facture;


class FournisseurController extends Controller
{
    /**
     * Display a listing of the resource.
     */



    public function indexByProjet(Request $request, $projet_id)
    {
        if (Auth::guard('api')->check()) {
            // Default values for pagination null si non pas envoyer avec la raquete
            $size = $request->input('size', null);
            $page = $request->input('page', null);

            DatabaseHelper::Config();

            $query = Fournisseur::on('temp')->with('factures')->where('projet_id', $projet_id);
            if ($request->filled('ice')) {
                $query->where('ice', 'like', '%' . $request->input('ice') . '%');
            }
            if ($request->filled('nom')) {
                $query->where('nom', 'like', '%' . $request->input('nom') . '%');
            }
            if ($request->filled('rc')) {
                $query->where('rc', 'like', '%' . $request->input('rc') . '%');
            }
            if ($request->filled('code')) {
                $query->where('code', 'like', '%' . $request->input('code') . '%');
            }
            // Check if pagination parameters are provided and valid
            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                // Paginate the query results
                $fournisseurs = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                $pagination = [
                    'currentPage' => $fournisseurs->currentPage(),
                    'totalItems' => $fournisseurs->total(),
                    'totalPages' => $fournisseurs->lastPage(),
                ];

                $fourItems = $fournisseurs->items();

                // Return the response with pagination
                return response()->json([
                    'data' => $fourItems,
                    'pagination' => $pagination,
                ], 200);
            } else {
                // Return all results if pagination parameters are not provided or invalid
                $fournisseurs = $query->orderBy('created_at', 'desc')
                    ->get();

                return response()->json(['fournisseurs' => $fournisseurs], 200);
            }
        }

        // Return unauthorized error if user is not authenticated
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function store(StoreFournisseurRequest $request)
    {
        if (RoleHelper::AdminSup()) {
            $user = Auth::user();
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
            $societe = Societe::findOrfail($user_societes->societe_id);
            $four = new Fournisseur();
            $four->setConnection('temp');
            $four->ice = $request->ice;
            $four->code = $request->code;
            $four->nom = $request->nom;
            $four->rc = $request->rc;
            $four->projet_id = $request->projet_id;
            $four->user_id=$userAuth->value('id');
            if ($request->hasFile('fichier_rc')) {
                $four->fichier_rc = $request->file('fichier_rc')->getClientOriginalName();;
                $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/Fournisseurs');
                File::makeDirectory($directory, 0755, true, true);
                $request->file('fichier_rc')->move($directory,$request->file('fichier_rc')->getClientOriginalName());
            }
            $four->adresse = $request->adresse;
            $four->save();

            return response()->json(['message' => $four], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function show($id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $fournisseur = Fournisseur::on('temp')->findOrfail($id);
            return response()->json(['fournisseur' => $fournisseur], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function update(StoreFournisseurRequest $request, $id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $user = Auth::user();
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
            $societe = Societe::findOrfail($user_societes->societe_id);
            $four = Fournisseur::on('temp')->findOrFail($id);
            $four->ice = $request->ice;
            $four->code = $request->code;
            $four->nom = $request->nom;
            $four->rc = $request->rc;
            if ($request->hasFile('fichier_rc')) {
                $four->fichier_rc = $request->file('fichier_rc')->getClientOriginalName();;
                $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/fournisseurs');
                File::makeDirectory($directory, 0755, true, true);
                $request->file('fichier_rc')->move($directory,$request->file('fichier_rc')->getClientOriginalName());
            }
            $four->adresse = $request->adresse;
            $four->projet_id = $request->projet_id;
            $four->user_id=$userAuth->value('id');
            $four->save();

            return response()->json(['fournisseur' => $four], 200);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Store a newly created resource in storage.
     */


    /**
     * Display the specified resource.
     */


    /**
     * Show the form for editing the specified resource.
     */


    /**
     * Update the specified resource in storage.
     */


    /**
     * Remove the specified resource from storage.
     */



     public function destroy(string $id)
     {
         if (RoleHelper::SuperAdmin() || RoleHelper::Comptable() || RoleHelper::AdminComptable()) {
             DatabaseHelper::Config();
             $fourn = Fournisseur::on('temp')->findOrFail($id);
             $factures=Facture::on('temp')->where('fournisseur_id',$id)->get();
             if(count($factures)>0){
                foreach($factures as $fact){
                    $fact->delete();
                }
             }
             if ($fourn->delete()) {
                 return response()->json(['message' => 'Fournisseur supprimé avec succès'], 200);
             } else {
                 return response()->json(['message' => 'Fournisseur  non Supprimé'], 400);
             }
         } else {
             return response()->json(['error' => 'Unauthorized'], 401);
         }
     }

     public function get_info_ice_unique($id,$ice)
     {
             if(RoleHelper::ACSup()){
                 $user = Auth::user();
                 DatabaseHelper::Config();
                 //cin unique
                 if($id!=0){
                    $info_count=Fournisseur::on('temp')->where('ice',$ice)->where('id','!=',$id)->count();
                 }else{
                    $info_count=Fournisseur::on('temp')->where('ice',$ice)->count();
                 }
                 return response()->json(['info_count' => $info_count]);


             } else {
                 return response()->json(['error' => 'Unauthorized'], 401);
             }


     }


}
