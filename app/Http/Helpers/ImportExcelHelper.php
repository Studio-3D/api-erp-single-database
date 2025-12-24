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

class ImportExcelHelper
{

    public static function store_fichier_import(Request $req)
    {

        $user = Auth::user();
        DatabaseHelper::Config();
        $userAuth      = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
        $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
        $societe       = Societe::findOrfail($user_societes->societe_id);
        $imp           = new Import();
        $imp->setConnection('temp');
        $imp->projet_id = $req->projet_id;
        $imp->statut    = '0';
        $imp->user_id   = $userAuth->value('id');
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

    public static function importerDonnees($data, $projet_id, callable $callback, $manageStatus = true)
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
    }


    public static function ImportStockByProjetWithoutTrancheAndBlocAndImmeuble($request, $data, $projet_id, $console)
    {
        if ($console == 0) {
            DatabaseHelper::Config();

            $projet = Projet::on('temp')->findOrFail($projet_id);
            $req    = new \Illuminate\Http\Request();
            //$projet->nbre_tranches == 0 &&
            if ( $projet->nbre_blocs == 0 && $projet->nbre_immeubles == 0) {
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
            // Traitement des données importées avec gestion d’erreurs centralisée
            return self::importerDonnees($data, $projet_id, function ($row, $projet_id) {
                // Pas de tranche, bloc, immeuble dans ce cas
                Bien_Helper::checkAndCreateBienByExcel($projet_id, null, null, null, $row);
            }, true);
        }
    }

    public static function ImportStockByProjetWithoutTrancheAndBloc($request, $data, $projet_id, $console)
    {
        if ($console == 0) {
            DatabaseHelper::Config();
            $req    = new \Illuminate\Http\Request();
            $projet = Projet::on('temp')->findOrFail($projet_id);

            if ($projet->nbre_blocs == 0 && $projet->nbre_tranches == 0 && $projet->nbre_immeubles > 0) {
                $dataToStore = [
                    'file'      => $request,
                    'projet_id' => $projet_id,
                    'data'      => $data,
                ];

                $foobar = new ImportExcelHelper();
                $foobar->store_fichier_import($req->merge($dataToStore));

                return response()->json('Le fichier est en cours d\'importation');
            } else {
                return response()->json(['error' => 'Les Colonnes Tranche Bloc ne sont pas requises pour Ce Projet.'], 400);
            }
        } else {
            // Traitement des données importées avec gestion d’erreurs centralisée
            return self::importerDonnees($data, $projet_id, function ($row, $projet_id) {
                $immeuble = Immeuble::on('temp')
                    ->where('nom', $row['Immeuble'])
                    ->where('projet_id', $projet_id)
                    ->first();

                if (! $immeuble) {
                    $immeuble = new Immeuble();
                    $immeuble->setConnection('temp');
                    $immeuble->nom       = $row['Immeuble'];
                    $immeuble->projet_id = $projet_id;
                      // return response()->json('done stock fic a supprimer si client remove tranche_id in row
                    $immeuble->tranche_id = $row['tranche_id']??null;
                    $immeuble->save();
                }

                Bien_Helper::checkAndCreateBienByExcel($projet_id, null, null, $immeuble->id, $row);
            }, true);
        }
    }

    public static function ImportStockByProjetWithoutTrancheAndImmeuble($request, $data, $projet_id, $console)
    {
        if ($console == 0) {
            DatabaseHelper::Config();
            $projet = Projet::on('temp')->findOrFail($projet_id);
            $req    = new \Illuminate\Http\Request();
            //$projet->nbre_tranches == 0 &&
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
            return self::importerDonnees($data, $projet_id, function ($row, $projet_id) {
                $bloc = Bloc::on('temp')
                    ->where('nom', $row['Bloc'])
                    ->where('projet_id', $projet_id)
                    ->first();

                if (! $bloc) {
                    $bloc = new Bloc();
                    $bloc->setConnection('temp');
                    $bloc->nom       = $row['Bloc'];
                      // return response()->json('done stock fic a supprimer si client remove tranche_id in row
                    $bloc->tranche_id = $row['tranche_id']??null;
                    $bloc->projet_id = $projet_id;
                    $bloc->save();
                }

                Bien_Helper::checkAndCreateBienByExcel($projet_id, null, $bloc->id, null, $row);
            }, true);
        }
    }

    public static function ImportStockByProjetWithoutTranche($request, $data, $projet_id, $console)
    {
        if ($console == 0) {
            DatabaseHelper::Config();

            $projet = Projet::on('temp')->findOrFail($projet_id);
            $req    = new \Illuminate\Http\Request();
            //$projet->nbre_tranches == 0 &&
            if ( $projet->nbre_blocs > 0 && $projet->nbre_immeubles > 0) {
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
            return self::importerDonnees($data, $projet_id, function ($row, $projet_id) {
                $bloc = Bloc::on('temp')
                    ->where('nom', $row['Bloc'])
                    ->where('projet_id', $projet_id)
                    ->first();

                if (! $bloc) {
                    $bloc = new Bloc();
                    $bloc->setConnection('temp');
                    $bloc->nom       = $row['Bloc'];
                    $bloc->projet_id = $projet_id;
                    // return response()->json('done stock fic a supprimer si client remove tranche_id in row
                    $bloc->tranche_id = $row['tranche_id']??null;
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
                    // return response()->json('done stock fic a supprimer si client remove tranche_id in row
                    $immeuble->tranche_id = $row['tranche_id']??null;
                    $immeuble->save();
                }

                Bien_Helper::checkAndCreateBienByExcel($projet_id, null, $bloc->id, $immeuble->id, $row);
            }, true);
        }
    }

    public static function ImportStockByProjetWithoutBlocAndImmeuble($request, $data, $projet_id, $console)
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
            return self::importerDonnees($data, $projet_id, function ($row, $projet_id) {
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
            }, true);
        }
    }

    public static function ImportStockByProjetWithoutBloc($request, $data, $projet_id, $console)
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
            return self::importerDonnees($data, $projet_id, function ($row, $projet_id) {
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
            }, true);
        }
    }

    public static function ImportStockByProjetWithoutImmeuble($request, $data, $projet_id, $console)
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
            return self::importerDonnees($data, $projet_id, function ($row, $projet_id) {
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
            }, true);
        }
    }

    public static function ImportStockByProjet($request, $data, $projet_id, $console)
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
            return self::importerDonnees($data, $projet_id, function ($row, $projet_id) {
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
            }, true);
        }
    }
    /*********************************Masssssssssssssssssssssssssssse */

   public static function importerDonnees_masse($data, $projet_id, callable $callback, $manageStatus = true)
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

        public static function ImportStock_Bien_EnMasse($data, $projet_id)
        {
            \Log::info("=== STARTING ImportStock_Bien_EnMasse ===");
            \Log::info("Projet ID: {$projet_id}");
            \Log::info("Data count: " . count($data));

            if (empty($data)) {
                \Log::error("Data is empty!");
                throw new \Exception("No data provided for import");
            }

            try {
                // Pass false for manageStatus since cron job handles status management
                $result = self::importerDonnees_masse($data, $projet_id, function ($row, $projet_id) {
                    \Log::info("--- Processing Row ---");
                    \Log::info("Row ID: " . ($row['ID'] ?? 'Unknown'));
                    \Log::info("Row Numéro: " . ($row['Numero'] ?? 'Not provided'));
                    \Log::info("Row Type: " . ($row['Type'] ?? 'Not provided'));

                    try {
                        $bienResult = Bien_Helper::updateBienByExcel($projet_id, $row);
                        \Log::info("✅ Successfully processed ID: " . ($row['ID'] ?? 'Unknown'));
                        return $bienResult;
                    } catch (\Exception $e) {
                        \Log::error("❌ Error processing ID " . ($row['ID'] ?? 'Unknown') . ": " . $e->getMessage());
                        throw $e;
                    }
                }, false); // Set to false since cron handles status

                \Log::info("importerDonnees final result: " . json_encode($result));

                // Check if result is valid and has successes
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

}
