<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreFactureRequest;
use App\Http\Requests\updateFactureRequest;
use App\Models\Facture;
use App\Models\Decompte;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Carbon\Carbon;
use App\Models\Societe;
use Illuminate\Support\Facades\File;

class FactureController extends Controller
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

            $query = Facture::on('temp')->with('fournisseur','decompte')->where('projet_id', $projet_id);
            if ($request->filled('code_fourn')) {
                $query->whereHas('fournisseur', function ($q) use ($request) {
                    $q->where('code', 'like', '%' . $request->input('code_fourn') . '%');
                });
            }
            if ($request->filled('nom_fourn')) {
                $query->whereHas('fournisseur', function ($q) use ($request) {
                    $q->where('nom', 'like', '%' . $request->input('nom_fourn') . '%');
                });
            }
            if ($request->filled('num_decompte')) {
                $query->whereHas('decompte', function ($q) use ($request) {
                    $q->where('numero', 'like', '%' . $request->input('num_decompte') . '%');
                });
            }

            if ($request->filled('decompteId')) {
                $query->where('decompte_id', $request->input('decompteId'));
            }
            if ($request->filled('num_facture')) {
                $query->where('num_facture', 'like', '%' . $request->input('num_facture') . '%');
            }
            if ($request->filled('montant')) {
                $query->where('montant', 'like', '%' . $request->input('montant') . '%');
            }
            if ($request->filled('date')) {
                $start = Carbon::parse($request->input('date'));
                $query->whereDate('date_facture', $start);
            }
            // Check if pagination parameters are provided and valid
            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                // Paginate the query results
                $factures = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                $pagination = [
                    'currentPage' => $factures->currentPage(),
                    'totalItems' => $factures->total(),
                    'totalPages' => $factures->lastPage(),
                ];

                $dItems = $factures->items();

                // Return the response with pagination
                return response()->json([
                    'data' => $dItems,
                    'pagination' => $pagination,
                ], 200);
            } else {
                // Return all results if pagination parameters are not provided or invalid
                $factures = $query->where('projet_id', $projet_id)->orderBy('created_at', 'desc')
                    ->get();

                return response()->json(['factures' => $factures], 200);
            }
        }

        // Return unauthorized error if user is not authenticated
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function store(StoreFactureRequest $request)
    {
        if (RoleHelper::AdminSup()||RoleHelper::Comptable()) {
            $user = Auth::user();
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
            $societe = Societe::findOrfail($user_societes->societe_id);
            $fact = new Facture();
            $fact->setConnection('temp');
            $fact->projet_id = $request->projet_id;
            $fact->fournisseur_id = $request->fournisseur_id;
            $fact->decompte_id = $request->decompte_id;
            $fact->date_facture = $request->date_facture;
            $fact->num_facture = $request->num_facture;
            $fact->montant = $request->montant;
            $fact->ht = $request->ht;
            $fact->taux_tva = $request->taux_tva;
            $fact->tva = $request->tva;
            $fact->retenue_garantie = $request->retenue_garantie;
            $fact->ttc = $request->ttc;
            $fact->projet_id = $request->projet_id;
            $fact->date_paiement = $request->date_paiement;
            $fact->mode_paiement =$request->mode_paiement;
            if ($request->hasFile('piece_jointe')) {
                $fact->piece_jointe = $request->file('piece_jointe')->getClientOriginalName();;
                $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/factures');
                File::makeDirectory($directory, 0755, true, true);
                $request->file('piece_jointe')->move($directory,$request->file('piece_jointe')->getClientOriginalName());
            }
            //cheque cheque-banque cheque cetifice
            if ($request->mode_paiement == 2 || $request->mode_paiement == 3 || $request->mode_paiement == 4) {
                $fact->numero_paiement = $request->numero_paiement;
                $fact->banque_id = $request->banque_id;
                $fact->echeance = $request->date_echeance;
                if ($request->hasFile('pj_paiement')) {
                    $fact->pj_paiement = $request->file('pj_paiement')->getClientOriginalName();;
                    $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/factures/paiements');
                    File::makeDirectory($directory, 0755, true, true);
                    $request->file('pj_paiement')->move($directory,$request->file('pj_paiement')->getClientOriginalName());
                }

            }
            //virement versement
            elseif ($request->mode_paiement == 5 || $request->mode_paiement == 6) {
                $fact->numero_paiement = $request->numero_paiement;
                $fact->banque_id = $request->banque_id;
                if ($request->hasFile('pj_paiement')) {
                    $fact->pj_paiement = $request->file('pj_paiement')->getClientOriginalName();;
                    $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/factures/paiements');
                    File::makeDirectory($directory, 0755, true, true);
                    $request->file('pj_paiement')->move($directory,$request->file('pj_paiement')->getClientOriginalName());
                }
            }
            $fact->user_id=$userAuth->value('id');
            $fact->save();

            return response()->json(['message' => $fact], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function show($id)
    {
        if (RoleHelper::AdminSup()||RoleHelper::Comptable()) {
            DatabaseHelper::Config();
            $facture = Facture::on('temp')->findOrfail($id);
            $decompte=Decompte::on('temp')->withSum('factures','montant')->findorfail($facture->decompte_id);
            return response()->json(['facture' => $facture,'decompte'=>$decompte], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function update(UpdateFactureRequest $request, $id)
    {
        if (RoleHelper::ACSup()||RoleHelper::Comptable()) {
            $user = Auth::user();
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
            $societe = Societe::findOrfail($user_societes->societe_id);
            $fact = Facture::on('temp')->findOrFail($id);
            $fact->fournisseur_id = $request->fournisseur_id;
            $fact->decompte_id = $request->decompte_id;
            $fact->date_facture = $request->date_facture;
            $fact->num_facture = $request->num_facture;
            if ($request->hasFile('piece_jointe')) {
                $fact->piece_jointe = $request->file('piece_jointe')->getClientOriginalName();;
                $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/factures');
                File::makeDirectory($directory, 0755, true, true);
                $request->file('piece_jointe')->move($directory,$request->file('piece_jointe')->getClientOriginalName());
            }            $fact->montant = $request->montant;
            $fact->ht = $request->ht;
            $fact->taux_tva = $request->taux_tva;
            $fact->tva = $request->tva;
            $fact->retenue_garantie = $request->retenue_garantie;
            $fact->ttc = $request->ttc;
            $fact->projet_id = $request->projet_id;
            $fact->date_paiement = $request->date_paiement;
            $fact->mode_paiement =$request->mode_paiement;
            //cheque cheque-banque cheque cetifice
            if ($request->mode_paiement == 2 || $request->mode_paiement == 3 || $request->mode_paiement == 4) {
                $fact->numero_paiement = $request->numero_paiement;
                $fact->banque_id = $request->banque_id;
                $fact->echeance = $request->date_echeance;
                if ($request->hasFile('pj_paiement')) {
                    $fact->pj_paiement = $request->file('pj_paiement')->getClientOriginalName();;
                    $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/factures/paiements');
                    File::makeDirectory($directory, 0755, true, true);
                    $request->file('pj_paiement')->move($directory,$request->file('pj_paiement')->getClientOriginalName());
                }

            }
            //virement versement
            elseif ($request->mode_paiement == 5 || $request->mode_paiement == 6) {
                $fact->numero_paiement = $request->numero_paiement;
                $fact->banque_id = $request->banque_id;
                if ($request->hasFile('pj_paiement')) {
                    $fact->pj_paiement = $request->file('pj_paiement')->getClientOriginalName();;
                    $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/factures/paiements');
                    File::makeDirectory($directory, 0755, true, true);
                    $request->file('pj_paiement')->move($directory,$request->file('pj_paiement')->getClientOriginalName());
                }
            }
            else{
                $fact->pj_paiement =null;
                $fact->numero_paiement = null;
                $fact->banque_id = null;
                $fact->echeance = null;
            }
            $fact->save();
            return response()->json(['facture' => $fact], 200);
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
            $fac = Facture::on('temp')->findOrFail($id);
            if ($fac->delete()) {
                return response()->json(['message' => 'Facture supprimé avec succès'], 200);
            } else {
                return response()->json(['message' => 'Facture non Supprimé'], 400);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }


    public function get_info_numero_facture_unique($id,$num)
    {
            if(RoleHelper::ACSup()||RoleHelper::Comptable()){
                $user = Auth::user();
                DatabaseHelper::Config();

                if($id!=0){
                   $info_count=Facture::on('temp')->where('num_facture',$num)->where('id','!=',$id)->count();
                }else{
                   $info_count=Facture::on('temp')->where('num_facture',$num)->count();
                }
                return response()->json(['info_count' => $info_count]);


            } else {
                return response()->json(['error' => 'Unauthorized'], 401);


            }
    }


}
