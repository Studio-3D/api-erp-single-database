<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\Projet;
use App\Models\Import;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Societe;
use Illuminate\Support\Facades\File;


use App\Http\Helpers\ImportExcelHelper;
class UploadBienController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function upload(Request $request)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $projet_id=$request->projet_id;
            $projet = Projet::on('temp')->findOrFail($request->projet_id);
            set_time_limit(0);
            ini_set('memory_limit', '-1');
            $data = json_decode($request->input('jsonData', '[]'), true) ;

            $keys = array_keys($data[0]);
           //$hasTranche = in_array('Tranche', $keys);
           $hasBloc = in_array('Bloc', $keys);
           $hasImmeuble = in_array('Immeuble', $keys);
           //if excel containe column bloc or immeuble or tranche
            $console=0;
          /* if ($hasTranche && $hasBloc && $hasImmeuble) {
               return ImportExcelHelper::ImportStockByProjet($request,$data,$projet_id,$console);

           } elseif ($hasTranche && $hasBloc && !$hasImmeuble) {
            return ImportExcelHelper::ImportStockByProjetWithoutImmeuble($request,$data,$projet_id,$console);

           } elseif ($hasTranche && !$hasBloc && $hasImmeuble) {
               return ImportExcelHelper::ImportStockByProjetWithoutBloc($request,$data,$projet_id,$console);
           } elseif ($hasTranche && !$hasBloc && !$hasImmeuble) {
            return ImportExcelHelper::ImportStockByProjetWithoutBlocAndImmeuble($request,$data,$projet_id,$console);

           } elseif (!$hasTranche && $hasBloc && $hasImmeuble) {
            return ImportExcelHelper::ImportStockByProjetWithoutTranche($request,$data,$projet_id,$console);

           } elseif (!$hasTranche && $hasBloc && !$hasImmeuble) {
            return ImportExcelHelper::ImportStockByProjetWithoutTrancheAndImmeuble($request,$data,$projet_id,$console);

           } elseif (!$hasTranche && !$hasBloc && $hasImmeuble) {
               return ImportExcelHelper::ImportStockByProjetWithoutTrancheAndBloc($request,$data,$projet_id,$console);
           } else {
               return ImportExcelHelper::ImportStockByProjetWithoutTrancheAndBlocAndImmeuble($request,$data,$projet_id,$console);
           }*/
            if ($hasBloc && $hasImmeuble) {
            return ImportExcelHelper::ImportStockByProjetWithoutTranche($request,$data,$projet_id,$console);

           } elseif ($hasBloc && !$hasImmeuble) {
            return ImportExcelHelper::ImportStockByProjetWithoutTrancheAndImmeuble($request,$data,$projet_id,$console);

           } elseif (!$hasBloc && $hasImmeuble) {
               return ImportExcelHelper::ImportStockByProjetWithoutTrancheAndBloc($request,$data,$projet_id,$console);
           } else {
               return ImportExcelHelper::ImportStockByProjetWithoutTrancheAndBlocAndImmeuble($request,$data,$projet_id,$console);
           }
            return response()->json('done stock fichier');
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }


    public function upload_excel_bien_modif_en_masse(Request $request)
    {
        if (RoleHelper::ACSup()) {
            $user = Auth::user();
            // Now switch to tenant database
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();
            $useAuth_id = $userAuth->id;
          // Get society information from main database BEFORE switching
            $mainUser = User::where('id', $user->getAuthIdentifier())->first();
            $societe = Societe::findOrFail($mainUser->societe_id);

            // Check if user is superadmin
            $isSuperAdmin = RoleHelper::SuperAdmin();

            // Now switch to tenant database
            DatabaseHelper::Config();

            $projet_id = $request->projet_id;
            set_time_limit(0);
            ini_set('memory_limit', '-1');
            $data = json_decode($request->input('jsonData', '[]'), true);

            /* // Set user_id based on user type
            if ($isSuperAdmin) {
                // For superadmin, set user_id to -1 or null
                $useAuth_id = 0; // or null if you changed the column to allow NULL
            } else {
                // For regular users, get the tenant user
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();
                $useAuth_id = $userAuth->id;
            }*/

            $imp = new Import();
            $imp->setConnection('temp');
            $imp->projet_id = $projet_id;
            $imp->statut = '0';
            $imp->user_id = $useAuth_id;
            $imp->data = $data;
            $imp->type = '1';

            // Handle file upload only if file exists
            if ($request->hasFile('piece_jointe')) {
                $client_origin_name = $request->file('piece_jointe')->getClientOriginalName();
                $date = str_replace(str_split('\\/:*?"<>|+-\s+'), '_', date("Y-m-d H:i:s"));
                $filename = pathinfo($client_origin_name, PATHINFO_FILENAME) . '_' . $date;
                $extension = pathinfo($client_origin_name, PATHINFO_EXTENSION);
                $imp->fichier = $filename . '.' . $extension;
                $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/Edit_fichier_en_masse');

                if (!File::exists($directory)) {
                    File::makeDirectory($directory, 0755, true, true);
                }

                $request->file('piece_jointe')->move($directory, $filename . '.' . $extension);
            }

            $imp->save();
            return response()->json('done stock fichier');
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function upload_excel_titre_foncier_en_masse(Request $request)
{
    if (RoleHelper::AdminSup() || RoleHelper::RespoLivraison()) {
        $user = Auth::user();

        // Get society information from main database BEFORE switching
        $mainUser = User::where('id', $user->getAuthIdentifier())->first();
        $societe = Societe::findOrFail($mainUser->societe_id);

        // Check if user is superadmin
        $isSuperAdmin = RoleHelper::SuperAdmin();

        // Now switch to tenant database
        DatabaseHelper::Config();

        $projet_id = $request->projet_id;
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        $data = json_decode($request->input('jsonData', '[]'), true);
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();
            $useAuth_id = $userAuth->id;
        /* Set user_id based on user type
        if ($isSuperAdmin) {
            // For superadmin, set user_id to NULL or -1
            $useAuth_id = 0; // or -1 if you haven't modified the column
        } else {
            // For regular users, get the tenant user
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();
            $useAuth_id = $userAuth->id;
        }*/

        $imp = new Import();
        $imp->setConnection('temp');
        $imp->projet_id = $projet_id;
        $imp->statut = '0';
        $imp->user_id = $useAuth_id;
        $imp->data = $data;
        $imp->type = '2'; // titre foncier

        // Handle file upload only if file exists
        if ($request->hasFile('piece_jointe')) {
            $client_origin_name = $request->file('piece_jointe')->getClientOriginalName();
            $date = str_replace(str_split('\\/:*?"<>|+-\s+'), '_', date("Y-m-d H:i:s"));
            $filename = pathinfo($client_origin_name, PATHINFO_FILENAME) . '_' . $date;
            $extension = pathinfo($client_origin_name, PATHINFO_EXTENSION);
            $imp->fichier = $filename . '.' . $extension;
            $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/Edit_titre_foncier');

            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true, true);
            }

            $request->file('piece_jointe')->move($directory, $filename . '.' . $extension);
        }

        $imp->save();
        return response()->json('done stock fichier');
    } else {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
}
    public function histo_importation(Request $request, $projet_id)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', null);
            $page = $request->input('page', null);
            DatabaseHelper::Config();
            // Démarrer la requête directement sur le modèle
            $query = Import::on('temp')->where('projet_id', $projet_id);
            if(RoleHelper::RespoLivraison()){
                $user = Auth::user();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();
                $query->where('user_id', $userAuth->id );
            }
            if ($request->filled('date')) {
                $start = Carbon::parse($request->input('date'));
                $query->whereDate('created_at', $start);
            }

            if ($request->filled('statut')) {
                $query->where('statut', 'like', '%' . $request->input('statut') . '%');
            }
            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                $imp = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Extraire les propriétés du paginateur
                $pagination = [
                    'currentPage' => $imp->currentPage(),
                    'totalItems' => $imp->total(),
                    'totalPages' => $imp->lastPage(),
                ];

                // Extraire les éléments d'utilisateur du paginateur
                $imp = $imp->items();

                // Retourner la réponse simplifiée
                return response()->json([
                    'data' => $imp,
                    'pagination' => $pagination,
                ], 200);
            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function delete_fichier_import($id){
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $import=Import::on('temp')->findOrFail($id);
            if ($import->delete()) {
                return response()->json(['message' => 'fichier Supprimé avec succès '], 200);
            } else {
                return response()->json(['message' => 'fichier  non supprimé'], 404);
            }

        }

        return response()->json(['error'=>'Unauthorized'],401);
    }

    /**
     * Store a newly created resource in storage.
     */


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    public function show($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $import = Import::on('temp')->findOrfail($id);
            return response()->json(['import' => $import], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     */


    /**
     * Remove the specified resource from storage.
     */

}
