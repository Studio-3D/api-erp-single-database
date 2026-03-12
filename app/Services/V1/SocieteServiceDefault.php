<?php
namespace App\Services\V1;

use App\Events\NewSocieteEvent;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\FichierHelper;
use App\Repositories\V1\Contracts\SocieteRepository;
use App\Services\V1\Contracts\SocieteService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SocieteServiceDefault implements SocieteService
{
    private $societeRepository;
    private $databaseHelper;

    public function __construct(SocieteRepository $societeRepository)
    {
        $this->societeRepository = $societeRepository;
        $this->databaseHelper = new DatabaseHelper();
    }

    public function createSociete($request)
{
    // Make sure we're using the correct DB connection for transaction
    DB::beginTransaction();

    try {
        Log::info('Starting societe creation process', [
            'request_data' => $request->except(['logo'])
        ]);

        $raison_sociale_concatene = str_replace(' ', '', $request->input('raison_sociale'));
        $data = $request->all();
        $data['raison_sociale_concatene'] = $raison_sociale_concatene;

        Log::info('Prepared societe data', [
            'raison_sociale_concatene' => $raison_sociale_concatene
        ]);

        // Création de la société
        $societe = $this->societeRepository->create($data);

        if (!$societe) {
            throw new \Exception('Failed to create societe in repository');
        }

        Log::info('Societe created in repository', ['societe_id' => $societe->id]);

        // Gestion du fichier logo
        if ($request->hasFile('logo')) {
            try {
                $file = $request->file('logo');
                $logo = time() . '.' . $raison_sociale_concatene . '.' . $file->extension();

                Log::info('Processing logo upload', [
                    'societe_id' => $societe->id,
                    'logo_name' => $logo
                ]);

                FichierHelper::ajouter_fichier($file, $raison_sociale_concatene, $societe->id, 'logos', $logo);
                $this->societeRepository->update($societe->id, ['logo' => $logo]);

                Log::info('Logo uploaded successfully', ['societe_id' => $societe->id]);
            } catch (\Exception $e) {
                Log::error('Error uploading logo', [
                    'societe_id' => $societe->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        }

        // Commit the transaction before database creation
        DB::commit();
        Log::info('Societe saved to database, transaction committed', ['societe_id' => $societe->id]);

        // Création de la base de données client (outside transaction)
        Log::info('Starting client database creation', [
            'raison_sociale' => $raison_sociale_concatene,
            'societe_id' => $societe->id
        ]);

        // FIX: Get the response from database helper
        $response = $this->databaseHelper->createNewClientDatabase($raison_sociale_concatene, $societe->id);

        // FIX: Check if response is JsonResponse and extract data
        $responseData = null;
        $statusCode = null;

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            // Get the content as array
            $responseData = $response->getData(true);
            $statusCode = $response->getStatusCode();

            Log::info('Database creation response', [
                'status_code' => $statusCode,
                'message' => $responseData['message'] ?? 'No message'
            ]);

            // Check if database creation was successful
            if ($statusCode !== 200) {
                Log::error('Database creation failed but societe was saved', [
                    'societe_id' => $societe->id,
                    'status_code' => $statusCode,
                    'message' => $responseData['message'] ?? 'Unknown error'
                ]);
                // We don't throw here because societe is already saved
            } else {
                Log::info('Client database created successfully', ['societe_id' => $societe->id]);
            }
        } else {
            // If it's not a JsonResponse, handle as array or string
            Log::info('Database creation response (non-JsonResponse)', [
                'response' => $response
            ]);
        }

        // Émettre un événement
        try {
            Config::set('broadcasting.default', 'pusher_1');
            broadcast(new NewSocieteEvent($societe->id));
            Log::info('Broadcast event emitted', ['societe_id' => $societe->id]);
        } catch (\Exception $e) {
            Log::error('Failed to broadcast event', [
                'societe_id' => $societe->id,
                'error' => $e->getMessage()
            ]);
            // Don't throw, just log
        }

        Log::info('Societe creation completed successfully', ['societe_id' => $societe->id]);

        // Return the societe object directly
        return $societe;

    } catch (\Exception $e) {
        // Rollback only if transaction is still active
        try {
            DB::rollBack();
        } catch (\Exception $rollbackError) {
            Log::error('Error during rollback', [
                'error' => $rollbackError->getMessage()
            ]);
        }

        Log::error('Societe creation failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'request_data' => $request->except(['logo'])
        ]);

        // Re-throw the exception to be handled by the controller
        throw $e;
    }
}

    public function getSocieteById(int $id)
    {
        Log::info('Fetching societe by ID', ['societe_id' => $id]);
        return $this->societeRepository->find($id);
    }

    public function getSocietes(array $filters, int $size, int $page)
    {
        Log::info('Fetching societes list', [
            'filters' => $filters,
            'size' => $size,
            'page' => $page
        ]);
        return $this->societeRepository->all($filters, $size, $page);
    }
}
