<?php

namespace App\Http\Controllers;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreSocieteRequest;
use App\Http\Requests\UpdateSocieteRequest;
use App\Models\Societe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use \Illuminate\Support\Facades\Storage;

class SocieteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (RoleHelper::Superadmin()) {
            $perPage = 20; // Number of items per page
            $page = $request->input('page', 1);

            $societes = Societe::orderBy('created_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            return response()->json(['societe' => $societes]);
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
        if (RoleHelper::superadmin()) {

            $societe = new Societe();
            $societe->raison_sociale = $request->raison_sociale;
            $societe->adresse = $request->adresse;
            $societe->nom_contact = $request->nom_contact;
            $societe->prenom_contact = $request->prenom_contact;
            $societe->tel = $request->tel;
            $societe->email = $request->email;
            if ($request->hasFile('logo')) {
                $logo = time() . '.' . $request->raison_sociale . '.' . $request->logo->extension();
                $request->logo->move(public_path('img/societes'), $logo);
                $societe->logo = $logo;
            }
            /* if ($request->hasFile('logo')) {
                $logo = $request->file('logo')->store($request->raison_sociale . '/logos', 'public');
                $societe->logo = $logo;

            } */
            $societe->save();
            $raison_sociale_concatene = str_replace(' ', '', $request->raison_sociale);

            $databaseSociete = new DatabaseHelper();
            $response = $databaseSociete->createNewClientDatabase($raison_sociale_concatene, $societe->id);
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
            $societe=Societe::findOrfail($id);
            return response()->json(['message' => $societe], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function update(UpdateSocieteRequest $request, Societe $societe)
    {
        if (RoleHelper::Superadmin()) {

            if ($request->hasFile('logo')) {
                if ($societe->logo) {
                    //$exist = Storage::disk('public')->exists("{$societe->raison_sociale}/logos/{$societe->logo}");
                    //if ($exist) {
                    Storage::disk('public')->delete("{$societe->raison_sociale}/logos/{$societe->logo}");
                    //}
                }
                $logo = $request->file('logo')->store($request->raison_sociale . '/logos', 'public');
                $request['logo'] = $logo;

            }

            $societe->update($request->all());

            return response()->json(['message' => $societe], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Societe $societe)
    {
        if (RoleHelper::Superadmin()) {

            if ($societe->delete()) {
                return response()->json(['message' => 'Societe deleted succesfully'], 200);
            } else {
                return response()->json(['message' => 'Societe non deleted'], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }
    public function restoreSociete($societe_id)
    {
        if (RoleHelper::Superadmin()) {

            Societe::where('id', $societe_id)->withTrashed()->restore();

            return response()->json(['message' => 'Societe est bien restaurer'], 200);

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
                $user->societe_id=$societe_id;
                $user->save();
                $societe = Societe::findOrfail($societe_id);

                return response()->json(
                    [
                        'message' => 'You are in ERP. ' . $societe->raison_sociale . ' (' . $societe_id . ')',
                        'user' => $user,

                    ],
                    200

                );
            }
            return response()->json(['error' => 'You have Choice a Societe'], 400);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function Exist_Societes()
    {

        if (RoleHelper::SuperAdmin()) {
            $user = Auth::guard('api')->user();
            $user->societe_id=1 ;
            $user->save();
            return response()->json(['message' => 'you are exists from  societes'], 200);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }


}
