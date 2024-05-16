<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\Societe;
use Illuminate\Http\Request;
use App\Events\NewSocieteEvent;
use App\Http\Helpers\RoleHelper;
use App\Http\Helpers\FichierHelper;
use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;
use App\Http\Requests\StoreSocieteRequest;
use App\Http\Requests\UpdateSocieteRequest;

class SocieteController extends Controller
{
    /**
     * GET    /       index
     * POST   /       store
     * GET    /{id}   show
     * PUT    /{id}   update
     * DELETE /{id}   destroy
     */
    public function index(Request $request)
    {
        if (RoleHelper::Superadmin()) {
            $size = $request->input('size', config('app.default_item_number_perpage'));
            $page = $request->input('page', 1);

            $query = Societe::query();
            if ($request->filled('raison_sociale')) {
                $query->where('raison_sociale', 'like', '%' . $request->input('raison_sociale') . '%');
            }
            if ($request->filled('nom_contact')) {
                $query->where('nom_contact', 'like', '%' . $request->input('nom_contact') . '%');
            }
            if ($request->filled('prenom_contact')) {
                $query->where('prenom_contact', 'like', '%' . $request->input('prenom_contact') . '%');
            }
            if ($request->filled('email')) {
                $query->where('email', 'like', '%' . $request->input('email') . '%');
            }
            if ($request->filled('tel')) {
                $query->where('tel', 'like', '%' . $request->input('tel') . '%');
            }

            $societes = $query->orderBy('created_at', 'desc')
            ->paginate($size, ['*'], 'page', $page);

            // Extraire les propriétés du paginateur
            $pagination = [
                'currentPage' => $societes->currentPage(),
                'totalItems' => $societes->total(),
                'totalPages' => $societes->lastPage(),
            ];

            // Extraire les éléments d'utilisateur du paginateur
            $societes = $societes->items();

            // Retourner la réponse simplifiée
            return response()->json([
                'societes' => $societes,
                'pagination' => $pagination
            ], 200);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }
    public function store(StoreSocieteRequest $request)
    {
        if (RoleHelper::Superadmin()) {
            $societe = new Societe();
            $raison_sociale_concatene = str_replace(' ', '', $request->raison_sociale);
            $societe->raison_sociale_concatene = $raison_sociale_concatene;
            $societe->raison_sociale = $request->raison_sociale;
            $societe->adresse = $request->adresse;
            $societe->nom_contact = $request->nom_contact;
            $societe->prenom_contact = $request->prenom_contact;
            $societe->tel = $request->tel;
            $societe->email = $request->email;
            if ($request->hasFile('logo')) {
                $logo = time() . '.' . $raison_sociale_concatene . '.' . $request->logo->extension();

            }
            $societe->save();
            if ($request->hasFile('logo')) {
                FichierHelper::ajouter_fichier($request->logo,$raison_sociale_concatene,$societe->id,'logos',$logo);
                //$request->logo->move(public_path('img/' . $raison_sociale_concatene . '_' . $societe->id . '/logos'), $logo);
                $societe->logo = $logo;
                $societe->save();
            }
            

            // $societes = Societe::whereNull('adresse')->get();
            // $societes=Societe::all();
            // broadcast(new NewSocieteEvent($societes));

            $databaseSociete = new DatabaseHelper();
            $response = $databaseSociete->createNewClientDatabase($raison_sociale_concatene, $societe->id);
            Config::set('broadcasting.default', 'pusher_1');
            // $societes = Societe::all();
            broadcast(new NewSocieteEvent($societe->id));
            if ($response->getStatusCode() == 200) {
                return response()->json(['message' => $response->getOriginalContent()['message']]);
            } else {
                return response()->json(['message' => $response->getOriginalContent()['message']]);
            }



        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function show($id)
    {
        if (RoleHelper::Superadmin()) {
            $societe = Societe::findOrfail($id);

            return response()->json(['societe' => $societe], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function edit($id)
    {
        if (RoleHelper::Superadmin()) {
            $societe = Societe::findOrfail($id);
            return response()->json(['message' => $societe], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function update(UpdateSocieteRequest $request, $id)
    {
        if (RoleHelper::Superadmin()) {
            $societe = Societe::findOrfail($id);
            $originalRaisonSociale = $societe->raison_sociale_concatene;
            $societe->raison_sociale = $request->raison_sociale;
            $raison_sociale_concatene = str_replace(' ', '', $request->raison_sociale);
            $societe->raison_sociale_concatene = $raison_sociale_concatene;
            $societe->adresse = $request->adresse;
            $societe->nom_contact = $request->nom_contact;
            $societe->prenom_contact = $request->prenom_contact;
            $societe->tel = $request->tel;
            $societe->email = $request->email;
            if ($request->hasFile('logo')) {

                if ($societe->logo != null) {
                    $image_path = public_path('img/' . $societe->raison_sociale_concatene . '_' . $id . '/logos' . $societe->logo);
                    if (file_exists($image_path)) {
                        //unlink(C:\Users\HP\Desktop\20190513_174204.jpg);
                        File::delete('C:\Users\HP\Desktop\20190513_174204.jpg');
                    }
                }
                $logo = time() . '.' . $raison_sociale_concatene . '.' . $request->logo->extension();
                FichierHelper::ajouter_fichier($request->logo,$raison_sociale_concatene,$societe->id,'logos',$logo);
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
            return response()->json(['message' => $societe], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function destroy($id)
    {
        if (RoleHelper::Superadmin()) {
            $user = Auth::guard('api')->user();
            $societe = Societe::findOrFail($id);
            $users = User::where('societe_id', $societe->id)->get();
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