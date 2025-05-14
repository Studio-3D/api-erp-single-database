<?php
namespace App\Http\Controllers\Api\V1;

use App\Events\NewSocieteEvent;
use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\FichierHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreSocieteRequest;
use App\Http\Requests\UpdateSocieteRequest;
use App\Models\User;
use App\Models\V1\Societe;
use App\Services\V1\Contracts\SocieteService;
use App\Utils\FilterUtils;
use App\Utils\PaginationUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class SocieteController extends Controller
{
    /**
     * GET    /       index
     * POST   /       store
     * GET    /{id}   show
     * PUT    /{id}   update
     * DELETE /{id}   destroy
     */
    private $societeService;
    public function __construct(SocieteService $societeService)
    {
        $this->societeService = $societeService;
    }
    public function store(StoreSocieteRequest $request)
    {
        if (! RoleHelper::Superadmin()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Passe directement l'objet $request
        $response = $this->societeService->createSociete($request);

        return response()->json([
            'societe' => $response->original['societe'], // Récupère la société créée
            'message' => 'Société ajoutée avec succès',
        ], 200);
    }

    public function show($id)
    {
        if (! RoleHelper::Superadmin()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $societe = $this->societeService->getSocieteById($id);
        return response()->json(['societe' => $societe], 200);
    }
    public function index(Request $request)
    {
        if (RoleHelper::Superadmin()) {
            $filters          = FilterUtils::fromRequest($request, ['raison_sociale', 'nom_contact', 'prenom_contact', 'email', 'tel', 'adresse']);
            $paginationParams = PaginationUtils::fromRequest($request);
            $data             = $this->societeService->getSocietes($filters, $paginationParams['size'], $paginationParams['page']);
            return response()->json([
                'societes'   => $data['items'],
                'pagination' => $data['pagination'],
            ], 200);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function update(UpdateSocieteRequest $request, $id)
    {
        if (RoleHelper::Superadmin()) {
            $societe                           = Societe::findOrfail($id);
            $originalRaisonSociale             = $societe->raison_sociale_concatene;
            $societe->raison_sociale           = $request->raison_sociale;
            $raison_sociale_concatene          = str_replace(' ', '', $request->raison_sociale);
            $societe->raison_sociale_concatene = $raison_sociale_concatene;
            $societe->adresse                  = $request->adresse;
            $societe->nom_contact              = $request->nom_contact;
            $societe->prenom_contact           = $request->prenom_contact;
            $societe->tel                      = $request->tel;
            $societe->email                    = $request->email;
            $societe->id_fiscal                = $request->id_fiscal;
            $societe->registre_commerce        = $request->registre_commerce;
            $societe->capital                  = $request->capital;
            if ($request->hasFile('logo')) {

                if ($societe->logo != null) {
                    $image_path = public_path('img/' . $societe->raison_sociale_concatene . '_' . $id . '/logos' . $societe->logo);
                    if (file_exists($image_path)) {
                        //unlink(C:\Users\HP\Desktop\20190513_174204.jpg);
                        File::delete('C:\Users\HP\Desktop\20190513_174204.jpg');
                    }
                }
                $logo = time() . '.' . $raison_sociale_concatene . '.' . $request->logo->extension();
                FichierHelper::ajouter_fichier($request->logo, $raison_sociale_concatene, $societe->id, 'logos', $logo);
                $societe->logo = $logo;
            }
            $societe->save();
            if ($request->has('raison_sociale')) {
                $newRaisonSociale = $societe->raison_sociale_concatene;
                if ($originalRaisonSociale !== $newRaisonSociale) {
                    $newDatabaseName = 'Erp_' . $newRaisonSociale . '_' . $id;
                    $oldDatabaseName = 'Erp_' . $originalRaisonSociale . '_' . $id;

                    $databaseHelper = new DatabaseHelper();
                    $databaseHelper->renameDatabase($oldDatabaseName, $newDatabaseName);
                }
            }

            Config::set('broadcasting.default', 'pusher_1');
            // $societes = Societe::all();
            broadcast(new NewSocieteEvent($societe->id));
            return response()->json(['societe' => $societe], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function destroy($id)
    {
        if (RoleHelper::Superadmin()) {
            $user    = Auth::guard('api')->user();
            $societe = Societe::findOrFail($id);
            $users   = User::where('societe_id', $societe->id)->get();
            foreach ($users as $user) {
                UserController::destroy($user->id);
            }
            if ($societe->delete()) {

                Config::set('broadcasting.default', 'pusher_1');
                $societes = Societe::all();

                broadcast(new NewSocieteEvent($societe->id));
                return response()->json(['message' => 'societe supprimé avec succes'], 200);

            } else {
                return response()->json(['message' => 'Societe non supprimée'], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}
