<?php
namespace App\Http\Helpers;

use App\Http\Helpers\Bien_Helper;
use App\Http\Helpers\DatabaseHelper;
use App\Models\Bloc;
use App\Models\Immeuble;
use App\Models\Import;
use App\Models\Projet;
use App\Models\Societe;
use App\Models\Tranche;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use App\Models\Prospect;
use App\Models\Source;
use App\Models\StatutProspect;
use App\Models\Partenaire;
use stdClass;

class ImportExcelHelper
{

    public static function store_fichier_import(Request $req)
    {

    $user = Auth::user();

    // Check if user is superadmin BEFORE switching database
   // $isSuperAdmin = RoleHelper::SuperAdmin();

    // Now switch to tenant database
    DatabaseHelper::Config();
        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();
        $useAuth_id = $userAuth->id;
        // Get society from main database using the main user
        $mainUser = User::where('id', $user->getAuthIdentifier())->first();
        $societe = Societe::findOrFail($mainUser->societe_id);
    /*if ($isSuperAdmin) {
        // For superadmin, we need to get the society from the main database
        // Since we're already in tenant database, we need to use main connection
        $mainUser = User::where('id', $user->getAuthIdentifier())->first();
        $societe = Societe::findOrFail($mainUser->societe_id);

        // For superadmin, we need to get the tenant user or create a default one
        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();
        if (!$userAuth) {
            // If no tenant user exists, use a default or create one
            // You might need to handle this case based on your business logic
            $useAuth_id = 0; // Or you might want to use the main user ID
        } else {
            $useAuth_id = $userAuth->id;
        }
    } else {
        // For regular users
        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();
        $useAuth_id = $userAuth->id;

        // Get society from main database using the main user
        $mainUser = User::where('id', $user->getAuthIdentifier())->first();
        $societe = Societe::findOrFail($mainUser->societe_id);
    }*/

        $imp           = new Import();
        $imp->setConnection('temp');
        $imp->projet_id = $req->projet_id;
        $imp->statut    = '0';
        $imp->user_id   = $useAuth_id;
        $imp->data      = $req->data;
        if ($req->file->hasFile('piece_jointe')) {
            $client_origin_name = $req->file->file('piece_jointe')->getClientOriginalName();
            $date               = str_replace(str_split('\\/:*?"<>|+-\s+'), '_', date("Y-m-d H:i:s"));
            $filename           = pathinfo($client_origin_name, PATHINFO_FILENAME) . '_' . $date;
            $extension          = pathinfo($client_origin_name, PATHINFO_EXTENSION);
            $imp->fichier       = $filename . '.' . $extension;
            $directory          = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/Import_fichier');
            File::makeDirectory($directory, 0755, true, true);
            $req->file->file('piece_jointe')->move($directory, $filename . '.' . $extension);
        }
        $imp->save();
    }

   /* public static function importerDonnees($data, $projet_id, callable $callback, $manageStatus = true)
    {
        $import = null;

        if ($manageStatus) {
            // Only find and manage import status when called from web (not from cron)
            $import = Import::on('temp')->where('projet_id', $projet_id)
                                        ->whereIn('statut', ['0', '1'])
                                        ->orderBy('created_at', 'desc')
                                        ->first();

            if (!$import) {
                throw new \Exception("Import introuvable ou déjà traité.");
            }

            // Set status to "en_cours" (1) when starting the import (if not already)
            if ($import->statut == '0') {
                $import->statut = '1';
                $import->save();
            }
        }

        $errors = [];
        $successCount = 0;
        $totalLines = count($data);
        \Log::info("Starting import of {$totalLines} lines for project {$projet_id}");

        foreach ($data as $index => $row) {
            try {
                // Traitement
                $callback($row, $projet_id);
                $successCount++;
            } catch (\Exception $e) {
                // Collect error information but continue processing
                $errors[] = [
                    'ligne' => $index + 2,
                    'message' => $e->getMessage(),
                    'data' => $row
                ];

                \Log::warning("Erreur ligne " . ($index + 1) . " lors de l'import: " . $e->getMessage());
            }
        }

        if ($manageStatus && $import) {
            $import = Import::on('temp')->find($import->id);

            if (count($errors) > 0) {
                // Import completed with errors - set status to "partiellement_importe" (4) or "avec_erreurs" (3)
                $import->statut = '3';
                $import->message_echou = json_encode([
                    'total_lignes' => $totalLines,
                    'lignes_reussies' => $successCount,
                    'lignes_echouees' => count($errors),
                    'erreurs' => $errors
                ]);
                $import->ligne_echou = count($errors); // Store number of failed lines
                $import->date_echou = now();
            } else {
                // Import completed successfully
                $import->statut = '2';
            }

            $import->save();
        }

        // Only throw exception if ALL lines failed
        if (count($errors) === $totalLines) {
            throw new \Exception("Toutes les lignes ont échoué lors de l'import.");
        }
    }*/


/**
 * Formate les erreurs en JSON avec les métadonnées
 */
public static function importerDonnees($data, $projet_id, $callback, $updateExisting = false, $importId = null)
{
    $errors = [];
    $successCount = 0;
    $errorCount = 0;
    $duplicateCount = 0;
    $totalLines = count($data);

    // Récupérer l'import si l'ID est fourni
    $import = null;
    if ($importId) {
        $import = Import::on('temp')->find($importId);
    }

    foreach ($data as $index => $row) {
        try {
            // Si updateExisting est true, on met à jour au lieu de créer
            if ($updateExisting && isset($row['ID']) && !empty($row['ID'])) {
                Bien_Helper::updateBienByExcel($projet_id, $row);
            } else {
                $callback($row, $projet_id);
            }
            $successCount++;

            \Log::info("Line " . ($index + 2) . " imported successfully: Bien " . ($row['Numero'] ?? 'N/A'));

        } catch (\Exception $e) {
            $errorCount++;
            $errorMessage = $e->getMessage();

            // Vérifier si c'est une erreur de bien déjà existant
            if (str_contains($errorMessage, 'existe déjà')) {
                $duplicateCount++;
            }

            // Stocker l'erreur avec le numéro de ligne et les détails
            $errors[] = [
                'ligne' => $index + 2,
                'message' => $errorMessage,
                'data' => $row
            ];

            \Log::warning("Import error at line " . ($index + 2) . ": " . $errorMessage);

            // Mettre à jour le message d'erreur en temps réel si l'import existe
            if ($import) {
                $errorData = self::formatErrorsAsJson($totalLines, $successCount, $errorCount, $errors);
                $import->message_echou = $errorData;
                $import->save();
            }
        }
    }

    // Déterminer le statut final
    $finalStatus = '2'; // Par défaut succès

    if ($errorCount > 0 && $successCount == 0) {
        // Toutes les lignes ont échoué
        $finalStatus = '3';
    } elseif ($errorCount > 0 && $successCount > 0) {
        // Succès partiel - reste en statut 2
        $finalStatus = '2';
    } elseif ($errorCount == 0 && $successCount > 0) {
        // Succès complet - statut 2
        $finalStatus = '2';
    }

    // Formater les erreurs en JSON (même si pas d'erreurs)
    $formattedErrors = self::formatErrorsAsJson($totalLines, $successCount, $errorCount, $errors);

    // Mettre à jour l'import avec les résultats finaux
    if ($import) {
        $import->statut = $finalStatus;
        $import->message_echou = $formattedErrors;
        $import->date_echou = $errorCount > 0 ? now() : null;
        $import->save();

        \Log::info("Import {$importId} completed: {$successCount} success, {$errorCount} errors, {$duplicateCount} duplicates");
        \Log::info("Import status: {$finalStatus}");
        \Log::info("Total lines: {$totalLines}, Success: {$successCount}, Failed: {$errorCount}");
    }

    return [
        'success' => $successCount,
        'errors' => $errorCount,
        'duplicates' => $duplicateCount,
        'error_details' => $formattedErrors,
        'error_array' => $errors,
        'status' => $finalStatus,
        'total_lines' => $totalLines,
        'success_lines' => $successCount,
        'failed_lines' => $errorCount
    ];
}

/**
 * Formate les erreurs en JSON avec les métadonnées
 */
private static function formatErrorsAsJson($totalLines, $successCount, $errorCount, $errors)
{
    return json_encode([
        'total_lignes' => $totalLines,
        'lignes_reussies' => $successCount,
        'lignes_echouees' => $errorCount,
        'erreurs' => $errors
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
 public static function ImportStockByProjetWithoutTrancheAndBloc($request, $data, $projet_id, $console, $importId = null)
{
    if ($console == 0) {
        DatabaseHelper::Config();
        $req = new \Illuminate\Http\Request();
        $projet = Projet::on('temp')->findOrFail($projet_id);

        if ($projet->nbre_blocs == 0 && $projet->nbre_immeubles > 0) {
            $dataToStore = [
                'file' => $request,
                'projet_id' => $projet_id,
                'data' => $data,
            ];

            $foobar = new ImportExcelHelper();
            $foobar->store_fichier_import($req->merge($dataToStore));

            return response()->json('Le fichier est en cours d\'importation');
        } else {
            return response()->json(['error' => 'Les Colonnes Tranche Bloc ne sont pas requises pour Ce Projet.'], 400);
        }
    } else {
        // Traitement des données importées avec gestion d'erreurs par ligne
        $result = self::importerDonnees($data, $projet_id, function ($row, $projet_id) {
            $immeuble = Immeuble::on('temp')
                ->where('nom', $row['Immeuble'])
                ->where('projet_id', $projet_id)
                ->first();

            if (!$immeuble) {
                $immeuble = new Immeuble();
                $immeuble->setConnection('temp');
                $immeuble->nom = $row['Immeuble'];
                $immeuble->projet_id = $projet_id;
                $immeuble->tranche_id = $row['tranche_id'] ?? null;
                $immeuble->save();
            }

            Bien_Helper::checkAndCreateBienByExcel($projet_id, null, null, $immeuble->id, $row);
        }, true, $importId);

        // Mettre à jour l'import avec les résultats
        if ($importId) {
            $import = Import::on('temp')->find($importId);
            if ($import) {
                $import->statut = $result['status'];
                $import->message_echou = $result['error_details'];
                $import->ligne_echou = $result['failed_lines'] ?? 0;
                $import->date_echou = $result['errors'] > 0 ? now() : null;
                $import->save();

                \Log::info("Import {$importId} completed: {$result['success']} success, {$result['errors']} errors, {$result['duplicates']} duplicates");
                \Log::info("Import status: {$result['status']}");
                \Log::info("Total lines: {$result['total_lines']}, Success: {$result['success_lines']}, Failed: {$result['failed_lines']}");
            }
        }

        return $result;
    }
}

public static function ImportStockByProjetWithoutTrancheAndBlocAndImmeuble($request, $data, $projet_id, $console, $importId = null)
{
    if ($console == 0) {
        DatabaseHelper::Config();

        $projet = Projet::on('temp')->findOrFail($projet_id);
        $req    = new \Illuminate\Http\Request();

        if ($projet->nbre_blocs == 0 && $projet->nbre_immeubles == 0) {
            // Stockage fichier import
            $dataToStore = [
                'file'      => $request,
                'projet_id' => $projet_id,
                'data'      => $data,
            ];

            $foobar = new ImportExcelHelper();
            $foobar->store_fichier_import($req->merge($dataToStore));

            return response()->json('Le fichier est en cours d\'importation');
        } else {
            return response()->json(['error' => 'Aucune Colonne Tranche Bloc Immeuble n\'est requise pour ce Projet.'], 400);
        }
    } else {
        // Traitement des données importées avec gestion d'erreurs centralisée
        $result = self::importerDonnees($data, $projet_id, function ($row, $projet_id) {
            // Pas de tranche, bloc, immeuble dans ce cas
            Bien_Helper::checkAndCreateBienByExcel($projet_id, null, null, null, $row);
        }, true, $importId);

        // Mettre à jour l'import avec les résultats
        if ($importId) {
            $import = Import::on('temp')->find($importId);
            if ($import) {
                $import->statut = $result['status'];
                $import->message_echou = $result['error_details'];
                $import->ligne_echou = $result['failed_lines'] ?? 0;
                $import->date_echou = $result['errors'] > 0 ? now() : null;
                $import->save();

                \Log::info("Import {$importId} completed: {$result['success']} success, {$result['errors']} errors, {$result['duplicates']} duplicates");
                \Log::info("Import status: {$result['status']}");
                \Log::info("Total lines: {$result['total_lines']}, Success: {$result['success_lines']}, Failed: {$result['failed_lines']}");
            }
        }

        return $result;
    }
}

public static function ImportStockByProjetWithoutTrancheAndImmeuble($request, $data, $projet_id, $console, $importId = null)
{
    if ($console == 0) {
        DatabaseHelper::Config();
        $projet = Projet::on('temp')->findOrFail($projet_id);
        $req    = new \Illuminate\Http\Request();

        if ($projet->nbre_immeubles == 0 && $projet->nbre_blocs > 0) {
            $dataToStore = [
                'file'      => $request,
                'projet_id' => $projet_id,
                'data'      => $data,
            ];

            $foobar = new ImportExcelHelper();
            $foobar->store_fichier_import($req->merge($dataToStore));

            return response()->json('Le fichier est en cours d\'importation');
        } else {
            return response()->json(['error' => 'Les Colonnes Tranche Immeuble ne sont pas requises pour Ce Projet.'], 400);
        }
    } else {
        $result = self::importerDonnees($data, $projet_id, function ($row, $projet_id) {
            $bloc = Bloc::on('temp')
                ->where('nom', $row['Bloc'])
                ->where('projet_id', $projet_id)
                ->first();

            if (! $bloc) {
                $bloc = new Bloc();
                $bloc->setConnection('temp');
                $bloc->nom       = $row['Bloc'];
                $bloc->tranche_id = $row['tranche_id'] ?? null;
                $bloc->projet_id = $projet_id;
                $bloc->save();
            }

            Bien_Helper::checkAndCreateBienByExcel($projet_id, null, $bloc->id, null, $row);
        }, true, $importId);

        // Mettre à jour l'import avec les résultats
        if ($importId) {
            $import = Import::on('temp')->find($importId);
            if ($import) {
                $import->statut = $result['status'];
                $import->message_echou = $result['error_details'];
                $import->ligne_echou = $result['failed_lines'] ?? 0;
                $import->date_echou = $result['errors'] > 0 ? now() : null;
                $import->save();

                \Log::info("Import {$importId} completed: {$result['success']} success, {$result['errors']} errors, {$result['duplicates']} duplicates");
                \Log::info("Import status: {$result['status']}");
                \Log::info("Total lines: {$result['total_lines']}, Success: {$result['success_lines']}, Failed: {$result['failed_lines']}");
            }
        }

        return $result;
    }
}

public static function ImportStockByProjetWithoutTranche($request, $data, $projet_id, $console, $importId = null)
{
    if ($console == 0) {
        DatabaseHelper::Config();

        $projet = Projet::on('temp')->findOrFail($projet_id);
        $req    = new \Illuminate\Http\Request();

        if ($projet->nbre_blocs > 0 && $projet->nbre_immeubles > 0) {
            $dataToStore = [
                'file'      => $request,
                'projet_id' => $projet_id,
                'data'      => $data,
            ];

            $foobar = new ImportExcelHelper();
            $foobar->store_fichier_import($req->merge($dataToStore));

            return response()->json('Le fichier est en cours d\'importation');
        } else {
            return response()->json(['error' => 'La Colonne Tranche n\'est pas requise pour le fichier.'], 400);
        }
    } else {
        $result = self::importerDonnees($data, $projet_id, function ($row, $projet_id) {
            $bloc = Bloc::on('temp')
                ->where('nom', $row['Bloc'])
                ->where('projet_id', $projet_id)
                ->first();

            if (! $bloc) {
                $bloc = new Bloc();
                $bloc->setConnection('temp');
                $bloc->nom       = $row['Bloc'];
                $bloc->projet_id = $projet_id;
                $bloc->tranche_id = $row['tranche_id'] ?? null;
                $bloc->save();
            }

            $immeuble = Immeuble::on('temp')
                ->where('nom', $row['Immeuble'])
                ->where('projet_id', $projet_id)
                ->where('bloc_id', $bloc->id)
                ->first();

            if (! $immeuble) {
                $immeuble = new Immeuble();
                $immeuble->setConnection('temp');
                $immeuble->nom       = $row['Immeuble'];
                $immeuble->projet_id = $projet_id;
                $immeuble->bloc_id   = $bloc->id;
                $immeuble->tranche_id = $row['tranche_id'] ?? null;
                $immeuble->save();
            }

            Bien_Helper::checkAndCreateBienByExcel($projet_id, null, $bloc->id, $immeuble->id, $row);
        }, true, $importId);

        // Mettre à jour l'import avec les résultats
        if ($importId) {
            $import = Import::on('temp')->find($importId);
            if ($import) {
                $import->statut = $result['status'];
                $import->message_echou = $result['error_details'];
                $import->ligne_echou = $result['failed_lines'] ?? 0;
                $import->date_echou = $result['errors'] > 0 ? now() : null;
                $import->save();

                \Log::info("Import {$importId} completed: {$result['success']} success, {$result['errors']} errors, {$result['duplicates']} duplicates");
                \Log::info("Import status: {$result['status']}");
                \Log::info("Total lines: {$result['total_lines']}, Success: {$result['success_lines']}, Failed: {$result['failed_lines']}");
            }
        }

        return $result;
    }
}

public static function ImportStockByProjetWithoutBlocAndImmeuble($request, $data, $projet_id, $console, $importId = null)
{
    if ($console == 0) {
        DatabaseHelper::Config();

        $projet = Projet::on('temp')->findOrFail($projet_id);
        $req    = new \Illuminate\Http\Request();

        if ($projet->nbre_blocs == 0 && $projet->nbre_immeubles == 0 && $projet->nbre_tranches > 0) {
            $dataToStore = [
                'file'      => $request,
                'projet_id' => $projet_id,
                'data'      => $data,
            ];

            $foobar = new ImportExcelHelper();
            $foobar->store_fichier_import($req->merge($dataToStore));

            return response()->json('Le fichier est en cours d\'importation');
        } else {
            return response()->json(['error' => 'Les Colonnes Immeuble Bloc ne sont pas requises pour le fichier.'], 400);
        }
    } else {
        $result = self::importerDonnees($data, $projet_id, function ($row, $projet_id) {
            $tranche = Tranche::on('temp')
                ->where('nom', $row['Tranche'])
                ->where('projet_id', $projet_id)
                ->first();

            if (! $tranche) {
                $tranche = new Tranche();
                $tranche->setConnection('temp');
                $tranche->nom            = $row['Tranche'];
                $tranche->projet_id      = $projet_id;
                $tranche->date_lancement = Carbon::now();
                $tranche->date_livraison = Carbon::now();
                $tranche->niveau_etages  = 0;
                $tranche->save();
            }

            Bien_Helper::checkAndCreateBienByExcel($projet_id, $tranche->id, null, null, $row);
        }, true, $importId);

        // Mettre à jour l'import avec les résultats
        if ($importId) {
            $import = Import::on('temp')->find($importId);
            if ($import) {
                $import->statut = $result['status'];
                $import->message_echou = $result['error_details'];
                $import->ligne_echou = $result['failed_lines'] ?? 0;
                $import->date_echou = $result['errors'] > 0 ? now() : null;
                $import->save();

                \Log::info("Import {$importId} completed: {$result['success']} success, {$result['errors']} errors, {$result['duplicates']} duplicates");
                \Log::info("Import status: {$result['status']}");
                \Log::info("Total lines: {$result['total_lines']}, Success: {$result['success_lines']}, Failed: {$result['failed_lines']}");
            }
        }

        return $result;
    }
}

public static function ImportStockByProjetWithoutBloc($request, $data, $projet_id, $console, $importId = null)
{
    if ($console == 0) {
        DatabaseHelper::Config();
        $req    = new \Illuminate\Http\Request();
        $projet = Projet::on('temp')->findOrFail($projet_id);

        if ($projet->nbre_blocs == 0 && $projet->nbre_tranches > 0 && $projet->nbre_immeubles > 0) {
            $dataToStore = [
                'file'      => $request,
                'projet_id' => $projet_id,
                'data'      => $data,
            ];

            $foobar = new ImportExcelHelper();
            $foobar->store_fichier_import($req->merge($dataToStore));

            return response()->json('Le fichier est en cours d\'importation');
        } else {
            return response()->json(['error' => 'La Colonne Bloc n\'est pas requise pour le fichier.'], 400);
        }
    } else {
        $result = self::importerDonnees($data, $projet_id, function ($row, $projet_id) {
            $tranche = Tranche::on('temp')
                ->where('nom', $row['Tranche'])
                ->where('projet_id', $projet_id)
                ->first();

            if (! $tranche) {
                $tranche = new Tranche();
                $tranche->setConnection('temp');
                $tranche->nom            = $row['Tranche'];
                $tranche->projet_id      = $projet_id;
                $tranche->date_lancement = Carbon::now();
                $tranche->date_livraison = Carbon::now();
                $tranche->niveau_etages  = 0;
                $tranche->save();
            }

            $immeuble = Immeuble::on('temp')
                ->where('nom', $row['Immeuble'])
                ->where('tranche_id', $tranche->id)
                ->where('projet_id', $projet_id)
                ->first();

            if (! $immeuble) {
                $immeuble = new Immeuble();
                $immeuble->setConnection('temp');
                $immeuble->nom        = $row['Immeuble'];
                $immeuble->projet_id  = $projet_id;
                $immeuble->tranche_id = $tranche->id;
                $immeuble->save();
            }

            Bien_Helper::checkAndCreateBienByExcel($projet_id, $tranche->id, null, $immeuble->id, $row);
        }, true, $importId);

        // Mettre à jour l'import avec les résultats
        if ($importId) {
            $import = Import::on('temp')->find($importId);
            if ($import) {
                $import->statut = $result['status'];
                $import->message_echou = $result['error_details'];
                $import->ligne_echou = $result['failed_lines'] ?? 0;
                $import->date_echou = $result['errors'] > 0 ? now() : null;
                $import->save();

                \Log::info("Import {$importId} completed: {$result['success']} success, {$result['errors']} errors, {$result['duplicates']} duplicates");
                \Log::info("Import status: {$result['status']}");
                \Log::info("Total lines: {$result['total_lines']}, Success: {$result['success_lines']}, Failed: {$result['failed_lines']}");
            }
        }

        return $result;
    }
}

public static function ImportStockByProjetWithoutImmeuble($request, $data, $projet_id, $console, $importId = null)
{
    if ($console == 0) {
        DatabaseHelper::Config();
        $req    = new \Illuminate\Http\Request();
        $projet = Projet::on('temp')->findOrFail($projet_id);

        if ($projet->nbre_immeubles == 0 && $projet->nbre_tranches > 0 && $projet->nbre_blocs > 0) {
            $dataToStore = [
                'file'      => $request,
                'projet_id' => $projet_id,
                'data'      => $data,
            ];

            $foobar = new ImportExcelHelper();
            $foobar->store_fichier_import($req->merge($dataToStore));

            return response()->json('Le fichier est en cours d\'importation');
        } else {
            return response()->json(['error' => 'La Colonne Immeuble n\'est pas requise pour le fichier.'], 400);
        }
    } else {
        $result = self::importerDonnees($data, $projet_id, function ($row, $projet_id) {
            $tranche = Tranche::on('temp')
                ->where('nom', $row['Tranche'])
                ->where('projet_id', $projet_id)
                ->first();

            if (! $tranche) {
                $tranche = new Tranche();
                $tranche->setConnection('temp');
                $tranche->nom            = $row['Tranche'];
                $tranche->projet_id      = $projet_id;
                $tranche->date_lancement = Carbon::now();
                $tranche->date_livraison = Carbon::now();
                $tranche->niveau_etages  = 0;
                $tranche->save();
            }

            $bloc = Bloc::on('temp')
                ->where('nom', $row['Bloc'])
                ->where('tranche_id', $tranche->id)
                ->where('projet_id', $projet_id)
                ->first();

            if (! $bloc) {
                $bloc = new Bloc();
                $bloc->setConnection('temp');
                $bloc->nom        = $row['Bloc'];
                $bloc->projet_id  = $projet_id;
                $bloc->tranche_id = $tranche->id;
                $bloc->save();
            }

            Bien_Helper::checkAndCreateBienByExcel($projet_id, $tranche->id, $bloc->id, null, $row);
        }, true, $importId);

        // Mettre à jour l'import avec les résultats
        if ($importId) {
            $import = Import::on('temp')->find($importId);
            if ($import) {
                $import->statut = $result['status'];
                $import->message_echou = $result['error_details'];
                $import->ligne_echou = $result['failed_lines'] ?? 0;
                $import->date_echou = $result['errors'] > 0 ? now() : null;
                $import->save();

                \Log::info("Import {$importId} completed: {$result['success']} success, {$result['errors']} errors, {$result['duplicates']} duplicates");
                \Log::info("Import status: {$result['status']}");
                \Log::info("Total lines: {$result['total_lines']}, Success: {$result['success_lines']}, Failed: {$result['failed_lines']}");
            }
        }

        return $result;
    }
}

public static function ImportStockByProjet($request, $data, $projet_id, $console, $importId = null)
{
    if ($console == 0) {
        DatabaseHelper::Config();
        $req    = new \Illuminate\Http\Request();
        $projet = Projet::on('temp')->findOrFail($projet_id);

        if ($projet) {
            $dataToStore = [
                'file'      => $request,
                'projet_id' => $projet_id,
                'data'      => $data,
            ];

            $foobar = new ImportExcelHelper();
            $foobar->store_fichier_import($req->merge($dataToStore));

            return response()->json('Le fichier est en cours d\'importation');
        } else {
            return response()->json(['error' => 'Le fichier nécessite les Colonnes tranche Bloc Immeuble.'], 400);
        }
    } else {
        // Traitement des données importées
        $result = self::importerDonnees($data, $projet_id, function ($row, $projet_id) {
            $tranche = Tranche::on('temp')
                ->where('nom', $row['Tranche'])
                ->where('projet_id', $projet_id)
                ->first();

            if (! $tranche) {
                $tranche = new Tranche();
                $tranche->setConnection('temp');
                $tranche->nom            = $row['Tranche'];
                $tranche->projet_id      = $projet_id;
                $tranche->date_lancement = Carbon::now();
                $tranche->date_livraison = Carbon::now();
                $tranche->niveau_etages  = 0;
                $tranche->save();
            }

            $bloc = Bloc::on('temp')
                ->where('nom', $row['Bloc'])
                ->where('tranche_id', $tranche->id)
                ->where('projet_id', $projet_id)
                ->first();

            if (! $bloc) {
                $bloc = new Bloc();
                $bloc->setConnection('temp');
                $bloc->nom        = $row['Bloc'];
                $bloc->projet_id  = $projet_id;
                $bloc->tranche_id = $tranche->id;
                $bloc->save();
            }

            $immeuble = Immeuble::on('temp')
                ->where('nom', $row['Immeuble'])
                ->where('tranche_id', $tranche->id)
                ->where('bloc_id', $bloc->id)
                ->where('projet_id', $projet_id)
                ->first();

            if (! $immeuble) {
                $immeuble = new Immeuble();
                $immeuble->setConnection('temp');
                $immeuble->nom        = $row['Immeuble'];
                $immeuble->projet_id  = $projet_id;
                $immeuble->tranche_id = $tranche->id;
                $immeuble->bloc_id    = $bloc->id;
                $immeuble->save();
            }

            Bien_Helper::checkAndCreateBienByExcel($projet_id, $tranche->id, $bloc->id, $immeuble->id, $row);
        }, true, $importId);

        // Mettre à jour l'import avec les résultats
        if ($importId) {
            $import = Import::on('temp')->find($importId);
            if ($import) {
                $import->statut = $result['status'];
                $import->message_echou = $result['error_details'];
                $import->ligne_echou = $result['failed_lines'] ?? 0;
                $import->date_echou = $result['errors'] > 0 ? now() : null;
                $import->save();

                \Log::info("Import {$importId} completed: {$result['success']} success, {$result['errors']} errors, {$result['duplicates']} duplicates");
                \Log::info("Import status: {$result['status']}");
                \Log::info("Total lines: {$result['total_lines']}, Success: {$result['success_lines']}, Failed: {$result['failed_lines']}");
            }
        }

        return $result;
    }
}
    /*********************************Masssssssssssssssssssssssssssse */

         public static function importerDonnees_masse($data, $projet_id, callable $callback, $manageStatus = true)
        {
            $import = null;

            if ($manageStatus) {
                // Only find and manage import status when called from web (not from cron)
                $import = Import::on('temp')->where('projet_id', $projet_id)
                                            ->where('statut',0)
                                            ->orderBy('created_at', 'desc')
                                            ->first();

                if (!$import) {
                    throw new \Exception("Import introuvable ou déjà traité.");
                }

                // Set status to "en_cours" (1) when starting the import (if not already)
                if ($import->statut == '0') {
                    $import->statut = '1';
                    $import->save();
                }
            }

            $errors = [];
            $successCount = 0;
            $totalLines = count($data);
            \Log::info("Starting import of {$totalLines} lines for project {$projet_id}");

            foreach ($data as $index => $row) {
                try {
                    // Traitement
                    $callback($row, $projet_id);
                    $successCount++;
                } catch (\Exception $e) {
                    // Collect error information but continue processing
                    $errors[] = [
                        'ligne' => $index + 2,
                        'message' => $e->getMessage(),
                        'data' => $row
                    ];

                    \Log::warning("Erreur ligne " . ($index + 1) . " lors de l'import: " . $e->getMessage());
                }
            }

            // Only throw exception if ALL lines failed
            if (count($errors) === $totalLines) {
                throw new \Exception("Toutes les lignes ont échoué lors de l'import.");
            }

            // Return the results for the cron job to handle
            return [
                'success' => true,
                'total' => $totalLines,
                'success_count' => $successCount,
                'error_count' => count($errors),
                'errors' => $errors
            ];
        }


            public static function ImportEdit_biens_edit_titre_foncier_EnMasse($data, $projet_id, $type)
            {
                \Log::info($type==1?"=== STARTING ImportStock_Bien_EnMasse ===":"=== STARTING Import_titre_foncier_EnMasse ===");
                \Log::info("Projet ID: {$projet_id}");
                \Log::info("Data type: " . $type);
                \Log::info("Data count: " . count($data));
                \Log::info("First row sample: " . json_encode($data[0] ?? 'No data'));

                if (empty($data)) {
                    \Log::error("Data is empty!");
                    throw new \Exception("No data provided for import");
                }

                try {
                    // Pass false for manageStatus since cron job handles status management
                    $result = self::importerDonnees_masse($data, $projet_id, function ($row, $projet_id) use ($type) {
                        \Log::info("--- Processing Row ---");
                        \Log::info("Row ID: " . ($row['ID'] ?? 'Unknown'));
                        \Log::info("Row Numéro: " . ($row['Numero'] ?? 'Not provided'));

                        // Log the entire row for debugging
                        \Log::info("Full row data: " . json_encode($row));

                        if($type == 1){
                            \Log::info("Row Type field: " . ($row['Type'] ?? 'Not provided'));
                        }

                        try {
                            if($type == 1){
                                \Log::info("Calling updateBienByExcel for ID: " . ($row['ID'] ?? 'Unknown'));
                                $bienResult = Bien_Helper::updateBienByExcel($projet_id, $row);
                                \Log::info("updateBienByExcel result: " . json_encode($bienResult));
                            } else {
                                //type 2
                                \Log::info("Calling updateBien_titre_foncie_ByExcel for ID: " . ($row['ID'] ?? 'Unknown'));
                                $bienResult = Bien_Helper::updateBien_titre_foncie_ByExcel($projet_id, $row);
                                \Log::info("updateBien_titre_foncie_ByExcel result: " . json_encode($bienResult));
                            }
                            \Log::info("✅ Successfully processed ID: " . ($row['ID'] ?? 'Unknown'));
                            return $bienResult;
                        } catch (\Exception $e) {
                            \Log::error("❌ Error processing ID " . ($row['ID'] ?? 'Unknown') . ": " . $e->getMessage());
                            \Log::error("Error trace: " . $e->getTraceAsString());
                            throw $e;
                        }
                    }, false);

                    \Log::info("importerDonnees final result: " . json_encode($result));

                    if (!$result || !isset($result['success']) || $result['success'] === false) {
                        throw new \Exception("Import failed in importerDonnees");
                    }

                    \Log::info("=== IMPORT COMPLETED SUCCESSFULLY ===");
                    return $result;
                } catch (\Exception $e) {
                    \Log::error("=== IMPORT FAILED ===");
                    \Log::error("Error in ImportStock_Bien_EnMasse: " . $e->getMessage());
                    \Log::error("Stack trace: " . $e->getTraceAsString());
                    throw $e;
                }
            }

    /*****************************************Import Prospect */

        /**
     * Store a prospect from a row of data
     *
     * @param array $row Les données du prospect
     * @param int $projet_id L'ID du projet
     * @throws \Exception Si le prospect existe déjà ou si des validations échouent
     */
        public static function storeProspectFromRow($row, $projet_id)
        {
            $errors = [];

            // Vérifier les champs requis
            $requiredFields = [
                'cin' => 'CIN manquant',
                'nom' => 'Nom manquant',
                'prenom' => 'Prénom manquant',
                'telephone' => 'Téléphone manquant',
            ];

            foreach ($requiredFields as $field => $message) {
                if (empty($row[$field])) {
                    $errors[] = $message;
                }
            }

            // Si des champs requis sont manquants, lever une exception
            if (!empty($errors)) {
                throw new \Exception(implode(' | ', $errors));
            }

            // Vérifier l'unicité du CIN
            if (!empty($row['cin'])) {
                $existingCin = Prospect::on('temp')
                    ->where('cin', $row['cin'])
                    ->where('projet_id', $projet_id)
                    ->count();

                if ($existingCin > 0) {
                    throw new \Exception("Le prospect avec le CIN '{$row['cin']}' existe déjà dans le projet");
                }
            }

            // Vérifier l'unicité de l'email
            if (!empty($row['email'])) {
                $existingEmail = Prospect::on('temp')
                    ->where('email', $row['email'])
                    ->where('projet_id', $projet_id)
                    ->count();

                if ($existingEmail > 0) {
                    throw new \Exception("Le prospect avec l'email '{$row['email']}' existe déjà dans le projet");
                }
            }

            // Vérifier l'unicité du téléphone (dans telephone et telephone_num2)
            if (!empty($row['telephone'])) {
                $existingTel = Prospect::on('temp')
                    ->where(function ($query) use ($row) {
                        $query->where('telephone', $row['telephone'])
                            ->orWhere('telephone_num2', $row['telephone']);
                    })
                    ->where('projet_id', $projet_id)
                    ->count();

                if ($existingTel > 0) {
                    throw new \Exception("Le prospect avec le téléphone '{$row['telephone']}' existe déjà dans le projet");
                }
            }

            // Vérifier l'unicité du deuxième téléphone
            if (!empty($row['telephone_num2'])) {
                $existingTel2 = Prospect::on('temp')
                    ->where(function ($query) use ($row) {
                        $query->where('telephone', $row['telephone_num2'])
                            ->orWhere('telephone_num2', $row['telephone_num2']);
                    })
                    ->where('projet_id', $projet_id)
                    ->count();

                if ($existingTel2 > 0) {
                    throw new \Exception("Le prospect avec le téléphone '{$row['telephone_num2']}' existe déjà dans le projet");
                }
            }

           // Récupérer la source - NOW ACCEPTING BOTH ID AND NAME
    $source_id = null;
    if (!empty($row['source'])) {
        $sourceValue = $row['source'];

        // Check if it's a numeric ID
        if (is_numeric($sourceValue)) {
            // Try to find by ID
            $source = Source::on('temp')
                ->where('id', $sourceValue)
                ->first();

            if ($source) {
                $source_id = $source->id;
            } else {
                \Log::warning("Source with ID '{$sourceValue}' not found for projet {$projet_id}");
            }
        } else {
            // Try to find by name/description
            $source = Source::on('temp')
                ->where('source', $sourceValue)
                ->first();

            if ($source) {
                $source_id = $source->id;
            } else {
                \Log::warning("Source '{$sourceValue}' not found for projet {$projet_id}");
            }
        }
    }

    // Récupérer le partenaire - NOW ACCEPTING BOTH ID AND NAME
    $partenaire_id = null;
    if (!empty($row['partenaire'])) {
        $partenaireValue = $row['partenaire'];

        // Check if it's a numeric ID
        if (is_numeric($partenaireValue)) {
            // Try to find by ID
            $partenaire = Partenaire::on('temp')
                ->where('id', $partenaireValue)
                ->where('projet_id', $projet_id)
                ->first();

            if ($partenaire) {
                $partenaire_id = $partenaire->id;
            } else {
                \Log::warning("Partenaire with ID '{$partenaireValue}' not found for projet {$projet_id}");
            }
        } else {
            // Try to find by description
            $partenaire = Partenaire::on('temp')
                ->where('description', $partenaireValue)
                ->where('projet_id', $projet_id)
                ->first();

            if ($partenaire) {
                $partenaire_id = $partenaire->id;
            } else {
                \Log::warning("Partenaire '{$partenaireValue}' not found for projet {$projet_id}");
            }
        }
    }


            // Créer le prospect
            $prospect = new Prospect();
            $prospect->setConnection("temp");
            $prospect->cin = $row['cin'];
            $prospect->nom = $row['nom'];
            $prospect->prenom = $row['prenom'];
            $prospect->telephone = $row['telephone'];
            $prospect->telephone_num2 = empty($row['telephone_num2']) ? null : $row['telephone_num2'];
            $prospect->email = empty($row['email']) ? null : $row['email'];
            $prospect->origin = 'import';
            $prospect->notifie = 0;
            $prospect->source = $source_id;
            $prospect->partenaire_id = $partenaire_id;
            $prospect->message = null;
            $prospect->projet_id = $projet_id;
            $prospect->ville = empty($row['ville']) ? null : $row['ville'];
            $prospect->save();

            // Créer le statut par défaut "en_attente"
            $statutProspect = new StatutProspect();
            $statutProspect->setConnection('temp');
            $statutProspect->prospect_id = $prospect->id;
            $statutProspect->statut = '0';
            $statutProspect->date_traitement = Carbon::now()->toDateString();
            $statutProspect->user_id_traite = null;
            $statutProspect->commentaire = 'Prospect créé par importation';
            $statutProspect->save();

            \Log::info("Prospect created successfully: ID {$prospect->id}, CIN: {$row['cin']}");

            return $prospect;
        }
        public static function Import_Prospect($data, $projet_id, $importId = null)
        {
            $result = self::importerDonneesProspect($data, $projet_id, function ($row, $projet_id) {
                self::storeProspectFromRow($row, $projet_id);
            }, true, $importId);

            // Mettre à jour l'import avec les résultats
            if ($importId) {
                $import = Import::on('temp')->find($importId);
                if ($import) {
                    $import->statut = $result['status'];
                    $import->message_echou = $result['error_details'];
                    $import->ligne_echou = $result['failed_lines'] ?? 0;
                    $import->date_echou = $result['errors'] > 0 ? now() : null;
                    $import->save();

                    \Log::info("Import Prospect {$importId} completed: {$result['success']} success, {$result['errors']} errors, {$result['duplicates']} duplicates");
                    \Log::info("Import status: {$result['status']}");
                    \Log::info("Total lines: {$result['total_lines']}, Success: {$result['success_lines']}, Failed: {$result['failed_lines']}");
                }
            }

            return $result;
        }
        public static function importerDonneesProspect($data, $projet_id, $callback, $updateExisting = false, $importId = null)
            {
                $errors = [];
                $successCount = 0;
                $errorCount = 0;
                $duplicateCount = 0;
                $totalLines = count($data);

                // Récupérer l'import si l'ID est fourni
                $import = null;
                if ($importId) {
                    $import = Import::on('temp')->find($importId);
                }

                foreach ($data as $index => $row) {
                    try {
                        $callback($row, $projet_id);
                        $successCount++;

                        \Log::info("Line " . ($index + 2) . " imported successfully: Prospect " . ($row['cin'] ?? 'N/A'));

                    } catch (\Exception $e) {
                        $errorCount++;
                        $errorMessage = $e->getMessage();

                        // Vérifier si c'est une erreur de prospect déjà existant
                        if (str_contains($errorMessage, 'existe déjà')) {
                            $duplicateCount++;
                        }

                        // Stocker l'erreur avec le numéro de ligne et les détails
                        $errors[] = [
                            'ligne' => $index + 2,
                            'message' => $errorMessage,
                            'data' => $row
                        ];

                        \Log::warning("Import Prospect error at line " . ($index + 2) . ": " . $errorMessage);

                        // Mettre à jour le message d'erreur en temps réel si l'import existe
                        if ($import) {
                            $errorData = self::formatErrorsAsJson($totalLines, $successCount, $errorCount, $errors);
                            $import->message_echou = $errorData;
                            $import->save();
                        }
                    }
                }

                // Déterminer le statut final
                $finalStatus = '2'; // Par défaut succès

                if ($errorCount > 0 && $successCount == 0) {
                    // Toutes les lignes ont échoué
                    $finalStatus = '3';
                } elseif ($errorCount > 0 && $successCount > 0) {
                    // Succès partiel - reste en statut 2
                    $finalStatus = '2';
                } elseif ($errorCount == 0 && $successCount > 0) {
                    // Succès complet - statut 2
                    $finalStatus = '2';
                }

                // Formater les erreurs en JSON
                $formattedErrors = self::formatErrorsAsJson($totalLines, $successCount, $errorCount, $errors);

                // Mettre à jour l'import avec les résultats finaux
                if ($import) {
                    $import->statut = $finalStatus;
                    $import->message_echou = $formattedErrors;
                    $import->date_echou = $errorCount > 0 ? now() : null;
                    $import->save();

                    \Log::info("Import Prospect {$importId} completed: {$successCount} success, {$errorCount} errors, {$duplicateCount} duplicates");
                    \Log::info("Import status: {$finalStatus}");
                    \Log::info("Total lines: {$totalLines}, Success: {$successCount}, Failed: {$errorCount}");
                }

                return [
                    'success' => $successCount,
                    'errors' => $errorCount,
                    'duplicates' => $duplicateCount,
                    'error_details' => $formattedErrors,
                    'error_array' => $errors,
                    'status' => $finalStatus,
                    'total_lines' => $totalLines,
                    'success_lines' => $successCount,
                    'failed_lines' => $errorCount
                ];
            }
        /**
 * Formate les erreurs en JSON avec les métadonnées
 */


}
