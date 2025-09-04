<?php

namespace App\Http\Controllers;

use App\Events\NewSocieteEvent;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\FichierHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreSocieteRequest;
use App\Http\Requests\UpdateSocieteRequest;
use App\Models\Societe;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Pusher\Pusher;
use \Illuminate\Support\Facades\Storage;

class SocieteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    // }
    public function get_societes()
    {
        if (RoleHelper::Superadmin()) {
            //$societes = Societe::whereNotIn('id', [1])->get();
            $societes = Societe::all();

            return response()->json(['societes' => $societes]);

        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function index(Request $request)
    {
        if (RoleHelper::Superadmin()) {
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);

            $societes = Societe::where('id', '!=', 1)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json(['societes' => $societes]);
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
            $societe->id_fiscal = $request->id_fiscal;
            $societe->registre_commerce = $request->registre_commerce;
            $societe->capital = $request->capital;
            if ($request->hasFile('logo')) {
                $logo = time() . '.' . $raison_sociale_concatene . '.' . $request->logo->extension();

            }
            $societe->save();
            if ($request->hasFile('logo')) {
                FichierHelper::ajouter_fichier($request->logo, $raison_sociale_concatene, $societe->id, 'logos', $logo);
                //$request->logo->move(public_path('Docs/'. $raison_sociale_concatene.'_'.$societe->id.'/logos'), $logo);
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

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (RoleHelper::Superadmin()) {
            $societe = Societe::findOrfail($id);

            return response()->json(['societe' => $societe], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
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
                    $image_path = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $id . '/logos' . $societe->logo);
                    if (file_exists($image_path)) {
                        //unlink(C:\Users\HP\Desktop\20190513_174204.jpg);
                        File::delete('C:\Users\HP\Desktop\20190513_174204.jpg');
                    }
                }
                $logo = time() . '.' . $raison_sociale_concatene . '.' . $request->logo->extension();
                $request->logo->move(public_path('docs/' . $raison_sociale_concatene . '_' . $societe->id . '/logos'), $logo);
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
    /**
     * Remove the specified resource from storage.
     */
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
    public function restoreSociete($societe_id)
    {
        if (RoleHelper::Superadmin()) {

            Societe::where('id', $societe_id)->withTrashed()->restore();

            return response()->json(['message' => 'Societe est restaurée avec succès'], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedSocietes()
    {

        if (RoleHelper::Superadmin()) {
            $societes = Societe::onlyTrashed()->get();

            return response()->json(['message' => $societes], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function Switch_Societes(Request $request)
    {
        $societe_id = $request->input('societe_id');
        if (RoleHelper::SuperAdmin()) {
            $user = Auth::guard('api')->user();
            if (!empty($societe_id)) {
                $user->societe_id = $societe_id;
                $user->save();
                $societe = Societe::findOrfail($societe_id);

                return response()->json(
                    [
                        'message' => 'Vous êtes sur ERP. ' . $societe->raison_sociale . ' (' . $societe_id . ')',
                        'user' => $user,

                    ],
                    200

                );
            }
            return response()->json(['error' => "Vous n'avez pas choisi une société"], 400);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function ExitSocietes()
    {

        if (RoleHelper::SuperAdmin()) {
            $user = Auth::guard('api')->user();
            $user->societe_id = 1;
            $user->save();
            return response()->json(['message' => "Vous n'êtes pas dans une société"], 200);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    public function Pusher()
    {
        $options = [
            'cluster' => 'eu',
            'useTLS' => true,
        ];

        $pusher = new Pusher(
            '14b5199abafacc5f7509',
            'your_app_sd83981468d9e7b8566b8ecret',
            '1722976',
            $options
        );

    }
}
