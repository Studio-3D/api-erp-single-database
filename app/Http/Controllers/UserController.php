<?php

namespace App\Http\Controllers;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Helpers\UserProjetHelper;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use \Illuminate\Support\Facades\Storage;

class UserController extends Controller
{

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {

            $accessToken = Auth::user()->createToken('API Token')->accessToken;
            return response()->json(['access_token' => $accessToken], 200);
        }

        return response()->json(['error' => 'email or password incorrect'], 422);
    }

    public function logout(Request $request)
    {

        $user = Auth::guard('api')->user();

        $request->user()->tokens()->delete(); // Revoke all access tokens for the user
        if (RoleHelper::SuperAdmin()) {
            $user->societe_id = 1;
            $user->save();}

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }
    /* public function dashboard()
    {   if (Auth::guard('api')->check()) {
    return response()->json(['user' => auth()->user()], 200);

    }
    return response()->json(['error' => 'Unauthorized'], 401);
    } */
    public function Dashboard()
    {
        if (Auth::guard('api')->check()) {

            $users = Auth::guard('api')->user();

            return response()->json(['user' => $users]);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    public function index(Request $request)
    {

        if (RoleHelper::Superadmin() && Auth::guard('api')->user()->societe_id == 1) {

            $perPage = 20; // Number of items per page
            $page = $request->input('page', 1);
            $users = User::orderBy('created_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            return response()->json(['user' => $users]);
        } else if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $perPage = 20; // Number of items per page
            $page = $request->input('page', 1);
            $users = User::on('temp')->orderBy('created_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            return response()->json(['user' => $users], 200);
        }

        return response()->json(['error' => 'Unauthorized'], 401);

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        if (RoleHelper::SuperAdmin()) {

            $user = new User();
            $user->name = $request->name;
            $user->societe_id = $request->societe_id;
            $user->prenom = $request->prenom;
            $user->email = $request->email;
            $user->password = $request->password;
            $user->gender = $request->gender;
            $user->role = $request->role;
            $user->phone = $request->phone;
            $user->cin = $request->cin;
            $user->fonction = $request->fonction;
            $user->date_embauche = $request->date_embauche;
            $user->niveau_etude = $request->niveau_etude;
            $user->adresse = $request->adresse;
            $user->cnss = $request->cnss;
            $user->is_actif = $request->is_actif ? $request->is_actif : 1;
            $user->nb_appel_recu = $request->nb_appel_recu;
            $user->nb_appel_traite = $request->nb_appel_traite;
            $user->solde_conge = $request->solde_conge;
            if ($request->hasFile('photo')) {
                $photo = time() . '.' . $request->name . '.' . $request->photo->extension();
                $request->photo->move(public_path('img/users'), $photo);
                $user->photo = $photo;
                $request->photo = $photo;
            }

            if ($user->save()) {

                $this->createSubUser($request, $user->id);

            }
            return response()->json(['message' => $user], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function createSubUser($request, $user_id)
    {
        DatabaseHelper::Config($request->societe_id);
        $user = new User();
        $user->setConnection('temp');
        $user->user_id_origin = $user_id;
        $user->name = $request->name;
        $user->prenom = $request->prenom;
        $user->email = $request->email;
        $user->password = $request->password;
        $user->gender = $request->gender;
        $user->role = $request->role;
        $user->phone = $request->phone;
        $user->cin = $request->cin;
        $user->fonction = $request->fonction;
        $user->date_embauche = $request->date_embauche;
        $user->niveau_etude = $request->niveau_etude;
        $user->adresse = $request->adresse;
        $user->cnss = $request->cnss;
        $user->is_actif = $request->is_actif ? $request->is_actif : 1;
        $user->nb_appel_recu = $request->nb_appel_recu;
        $user->nb_appel_traite = $request->nb_appel_traite;
        $user->solde_conge = $request->solde_conge;
        if ($request->hasFile('photo')) {
            $user->photo = $request->photo;

        }
        $user->save();
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (RoleHelper::SuperAdmin()) {

            $user = User::with('societe')->findOrfail($id);

            if ($user) {
                return response()->json(['user' => $user], 200);

            } else {
                return response()->json(['message' => 'User not found'], 200);
            }

        } else if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $user = User::on('temp')->with('societe')->findOrfail($id);
            return response()->json(['user' => $user], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
   /* public function edit($id)
    {
        if (RoleHelper::AdminSup()) {
            dd('hh');
            $user=User::firstorfail($id);
            return response()->json(['message' => $user->with('societe')], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
*/
    public function update(UpdateUserRequest $request, $id)
    {
        if (RoleHelper::SuperAdmin()) {
            $user = User::findOrfail($id);
            $originalName = $user->name;
            if ($request->hasFile('photo')) {
                $photo = time() . '.' . $originalName . '.' . $request->photo->extension();
                $request->photo->move(public_path('img/users'), $photo);
                $user->photo = $photo;
            }
            $update = $request->all();
            foreach ($update as $key => $value) {
                $user->$key = $value;
            }
            $user->save();
             if ($user) {
                DatabaseHelper::Config();

                $user_societes = User::on('temp')->where('user_id_origin', $user->id);
                $update = $request->all();
                foreach ($update as $key => $value) {
                    $user->$key = $value;
                }
                $user_societes->update($request->all());

            }
            return response()->json(['message' => $user], 200);
        } else if (RoleHelper::AdminSup() && RoleHelper::AC()) {
            DatabaseHelper::Config();
            $user = User::on('temp')->findOrfail($id);
            $originalName = $user->name;
            if ($request->hasFile('photo')) {
                $photo = time() . '.' . $originalName . '.' . $request->photo->extension();
                $request->photo->move(public_path('img/users'), $photo);
                $user->photo = $photo;
            }
            $update = $request->all();
            foreach ($update as $key => $value) {
                $user->$key = $value;
            }
            $user->save();
            return response()->json(['message' => $user], 200);

        }

        else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(user $user)
    {
        if (RoleHelper::SuperAdmin()) {

            if ($user->delete()) {
                DatabaseHelper::Config();
                $user_societes = User::on('temp')->where('user_id_origin', $user->id);
                $user_societes->delete();
                return response()->json(['message' => 'user deleted succesfully'], 200);
            } else {
                return response()->json(['message' => 'user non deleted'], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }
    public function getUsersBySocieteId($societe_id)
    {
        if (RoleHelper::SuperAdmin()) {
            $users = User::where('societe_id', $societe_id)->get();
            return response()->json(['message' => $users], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }
    public function activateUser($user_id)
    {
        if (RoleHelper::SuperAdmin()) {
            $user = User::findOrFail($user_id);
            $user->is_actif = 1;
            if ($user->save()) {
                DatabaseHelper::Config();
                $user_societes = User::on('temp')->where('user_id_origin', $user->id);
                $user_societes->update(['is_actif' => 1]);
            }
            return response()->json(['message' => 'User activated succesfully'], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function desactivateUser($user_id)
    {
        if (RoleHelper::AdminSup()) {
            $user = User::findOrFail($user_id);

            $user->is_actif = 0;
            if ($user->save()) {
                DatabaseHelper::Config();
                $user_societes = User::on('temp')->where('user_id_origin', $user->id);
                $user_societes->update(['is_actif' => 0]);
            }
            return response()->json(['message' => 'User desactivated succesfully'], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function restoreUser($user_id)
    {
        if (RoleHelper::SuperAdmin()) {

            User::where('id', $user_id)->withTrashed()->restore();
            DatabaseHelper::Config();
            $user_societes = User::on('temp')->where('user_id_origin', $user_id)->withTrashed()->restore();

            return response()->json(['message' => 'User est bien restaurer'], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedUsers()
    {

        if (RoleHelper::SuperAdmin()) {
            $users = User::onlyTrashed()->get();

            return response()->json(['message' => $users], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedUsersBySociete($societe_id)
    {

        if (RoleHelper::SuperAdmin()) {

            $users = User::onlyTrashed()->where('societe_id', $societe_id)->get();

            return response()->json(['message' => $users], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function addUserProjet($user_id, Request $request)
    {

        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            if($request->selectedProjets){
                foreach($request->selectedProjets as $valeur){
                    UserProjetHelper::createUserProjet($valeur, $user_id);
                }
                return response()->json(['message' => 'les lignes bien ajouter'], 200);

            } else {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

    }
}
