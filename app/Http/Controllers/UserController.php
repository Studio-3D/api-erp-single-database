<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Societe;
use App\Models\User;
use \Illuminate\Support\Facades\DB;
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

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function index()
    {
        if (Auth::guard('api')->check()){
            if(Auth::guard('api')->user()->type == 1) {
                $users = User::all();
                return response()->json(['user' => $users]);
            }else if(Auth::guard('api')->user()->type == 2){
                $users = User::where('societe_id', Auth::guard('api')->user()->societe_id)->get();
                return response()->json(['message' => $users], 200);
            }
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
        if (Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1) {

            $user = new User();

            $user->name = $request->name;
            $user->societe_id = $request->societe_id;
            $user->prenom = $request->prenom;
            $user->email = $request->email;
            $user->password = $request->password;
            $user->gender = $request->gender;
            $user->type = $request->type;
            $user->phone = $request->phone;
            $user->cin = $request->cin;
            $user->fonction = $request->fonction;
            $user->date_embauche = $request->date_embauche;
            $user->niveau_etude = $request->niveau_etude;
            $user->adresse = $request->adresse;
            $user->cnss = $request->cnss;
            $user->is_actif = $request->is_actif?$request->is_actif:1;
            $user->nb_appel_recu = $request->nb_appel_recu;
            $user->nb_appel_traite = $request->nb_appel_traite;
            $user->solde_conge = $request->solde_conge;
            if ($request->has('photo')) {
                $societe=Societe::findOrFail($request->societe_id);
                $photo= $request->file('photo')->store($societe->raison_sociale.'/photos_users', 'public');
                $user->photo = $photo;
            }
            $user->save();

            return response()->json(['message' => $user], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(user $user)
    {
        if (Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1) {
           if($user){
            return response()->json(['message' => $user], 200);
           }
           else{
            return response()->json(['message' => 'User not found'], 200);
           }
            
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(user $user)
    {
        if (Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1) {
            return response()->json(['message' => $user], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        if (Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1) {
            if ($request->hasFile('photo')) {
                if ($user->photo) {
                    $exist = Storage::disk('public')->exists("{$user->societe->raison_sociale}/photo_users/{$user->photo}");
                    if ($exist) {
                        Storage::disk('public')->delete("{$user->societe->raison_sociale}/photo_users/{$user->photo}");
                    }
                }
                $photo= $request->file('photo')->store($request->raison_sociale.'/photos_users', 'public');
                $request['photo'] = $photo;
               
            }

            $user->update($request->all());


            return response()->json(['message' => $user], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(user $user)
    {
        if (Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1) {
            
            if ($user->delete()) {
                return response()->json(['message' => 'user deleted succesfully'], 200);
            } else {
                return response()->json(['message' => 'user non deleted'], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }
    public function getUsersBySocieteId($societe_id){
        if (Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1){
            $users = User::where('societe_id', $societe_id)->get();
            return response()->json(['message' => $users], 200);
            
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }
    public function activateUser($user_id)
    {  
        if (Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1 ) {
            $user = User::findOrFail($user_id);

            $user->is_actif=1;
            $user->save();
            return response()->json(['message' => 'User activated succesfully'], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function desactivateUser($user_id){  
        if (Auth::guard('api')->check() && (Auth::guard('api')->user()->type == 1 || Auth::guard('api')->user()->type == 2)) {
            $user = User::findOrFail($user_id);
            
            $user->is_actif=0;
            $user->save();
            return response()->json(['message' => 'User desactivated succesfully'], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function restoreUser($user_id){  
        if(Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1) {
           
            User::where('id', $user_id)->withTrashed()->restore();

            return response()->json(['message' => 'User est bien restaurer'], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedUsers(){  
        
        if (Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1) {
            $users = User::onlyTrashed()->get();
            

            return response()->json(['message' => $users], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedUsersBySociete($societe_id){  

        if (Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1) {
           
            $users = User::onlyTrashed()->where('societe_id',$societe_id)->get();

            return response()->json(['message' => $users], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    
}

