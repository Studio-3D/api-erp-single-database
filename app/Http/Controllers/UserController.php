<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function register(StoreUserRequest $request)
    {

        if ($request->hasFile('photo')) {
            $photo= $request->file('photo')->store($request->photos_users.'/photos', 'public');
        }
        $user = User::create([
            'name' => $request['name'],
            'prenom' => $request['prenom'],
            'email' => $request['email'],
            'password' => Hash::make($request['password']),
            'gender' => $request['gender'],
            'type' => $request['type'],
            'phone' => $request['phone'],
            'photo' => $photo,
            'cin' => $request['cin'],
            'fonction' => $request['fonction'],
            'date_embauche' => $request['date_embauche'],
            'niveau_etude' => $request['niveau_etude'],
            'adresse' => $request['adresse'],
            'cnss' => $request['cnss'],
            'is_actif' => $request['is_actif'],
            'nb_appel_recu' => $request['nb_appel_recu'],
            'nb_appel_traite' => $request['nb_appel_traite'],
            'sold_conge' => $request['sold_conge'],
        ]);
        $token = $user->createToken('API Token')->accessToken;

        return response()->json(['token' => $token], 200);
    }

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
        if (Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1) {
            $users = User::all();
            return response()->json(['user' => $users]);
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

            if ($request['solde_conge'] == "") {
                $request['solde_conge'] = '0';
            }
            if ($request['is_actif'] == "") {
                $request['is_actif'] = '1';
            }

            if ($request->hasFile('photo')) {
                $logo = $request->file('photo');
                $logoPath = $logo->store('photos', 'public');
                $request['photo'] = $logoPath;
            }
            $user = new User();

            $user->name = $request['name'];
            $user->prenom = $request['prenom'];
            $user->email = $request['email'];
            $user->password = $request['password'];
            $user->gender = $request['gender'];
            $user->type = $request['type'];
            $user->phone = $request['phone'];
            $user->photo = $request['photo'];
            $user->cin = $request['cin'];
            $user->fonction = $request['fonction'];
            $user->date_embauche = $request['date_embauche'];
            $user->niveau_etude = $request['niveau_etude'];
            $user->adresse = $request['adresse'];
            $user->cnss = $request['cnss'];
            $user->is_actif = $request['is_actif'];
            $user->nb_appel_recu = $request['nb_appel_recu'];
            $user->nb_appel_traite = $request['nb_appel_traite'];
            $user->solde_conge = $request['solde_conge'];
            $user->save();
            return response()->json(['message' => 'User creer avec succes'], 200);

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
            return response()->json(['message' => $user], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(user $user)
    {
        //
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        if (Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1) {
            $user->update($request->all());

            if ($request->hasFile('photo')) {
                $logo = $request->file('photo');
                $logoPath = $logo->store('photos', 'public');
                $request['photo'] = $logoPath;
                $user->save();

            }

            return response()->json(['message' => 'user updated succesfully'], 200);
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

}
