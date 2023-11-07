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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Mail\ResetPasswordMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;



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
        return response()->json(['error' => 'email ou mot de passe incorrect'], 422);
    }

    public function logout(Request $request)
    {
        $user = Auth::guard('api')->user();
        $request->user()->tokens()->delete(); // Revoke all access tokens for the user
        if (RoleHelper::SuperAdmin()) {
            $user->societe_id = 1;
            $user->save();
        }
        return response()->json([
            'message' => 'Logout successful',
        ]);
    }

    public function Dashboard()
    {
        if (Auth::guard('api')->check()) {

            $users = Auth::guard('api')->user();

            return response()->json(['user' => $users]);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function get_users()
    {
        if (RoleHelper::Superadmin() && Auth::guard('api')->user()->societe_id == 1) {
            $users = User::all();
            return response()->json(['users' => $users]);
        } else if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $users = User::on('temp')->get();
            return response()->json(['users' => $users], 200);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }
    public function index(Request $request)
    {
        if (RoleHelper::Superadmin() && Auth::guard('api')->user()->societe_id == 1) {

            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);
            $users = User::orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
            return response()->json(['users' => $users]);
        } else if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);
            $users = User::on('temp')->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json(['users' => $users], 200);
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
        if (RoleHelper::Superadmin() && Auth::guard('api')->user()->societe_id == 1) {
            $user = User::findOrfail($id);
            if ($user) {
                return response()->json(['user' => $user], 200);
            } else {
                return response()->json(['message' => 'Utilisateur non trouvé'], 200);
            }
        } else if (RoleHelper::AdminSup()) {

            DatabaseHelper::Config();
            $user = User::on('temp')->findOrfail($id);
            return response()->json(['user' => $user], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function update(UpdateUserRequest $request, $id)
    {
        if($request->is_profil) {
            if ($request->cin != null) {
                $cin_exist = User::where('cin', $request->cin)->where('id', '!=', $id)->count();
                if ($cin_exist > 0) {
                    return response()->json(['error' => 'Le Cin que vous avez saisi' . $request->cin . ' apprtient à un autre utilisateur'], 422);
                }}
            $user = User::findOrFail($id);
            $user->name = $request->input('name');
            $user->prenom = $request->input('prenom');
            $user->gender = $request->input('gender');
            $user->role = $request->input('role');
            $user->phone = $request->input('phone');
            $user->cin = $request->input('cin');
            $user->fonction = $request->input('fonction');
            $user->date_embauche = $request->input('date_embauche');
            $user->niveau_etude = $request->input('niveau_etude');
            $user->adresse = $request->input('adresse');
            $user->cnss = $request->input('cnss');
            $user->is_actif = $request->input('is_actif'); // Default to 1 if not provided
            $user->solde_conge = $request->input('solde_conge');

            if ($request->hasFile('photo')) {
                if ($user->photo != null) {
                    $image_path = public_path('img/users/' . $user->photo);
                    if (file_exists($image_path)) {
                        unlink($image_path);
                    }
                }
                $photo = time() . '.' . $user->name . '.' . $request->photo->extension();
                $request->photo->move(public_path('img/users'), $photo);
                $user->photo = $photo;
            }

            if ($user->save()) {
                // Update the user in the 'temp' database connection (assuming this is what you intend to do)
                DatabaseHelper::Config($user->societe_id);
                $user_societes = User::on('temp')->where('user_id_origin', $user->id)->first();

                if ($user_societes) {
                    $user_societes->update($request->all());
                }
            }

            return response()->json(['message' => 'profil modifié avec succès'], 200);

        }
        else if (RoleHelper::Superadmin() && Auth::guard('api')->user()->societe_id == 1) {

            if ($request->cin != null) {
                $cin_exist = User::where('cin', $request->cin)->where('id', '!=', $id)->count();
                if ($cin_exist > 0) {
                    return response()->json(['error' => 'Le Cin que vous avez saisi' . $request->cin . ' apprtient à un autre utilisateur'], 422);
                }
            }
            $user = User::findOrFail($id);
            $user->name = $request->input('name');
            $user->prenom = $request->input('prenom');
            $user->gender = $request->input('gender');
            $user->role = $request->input('role');
            $user->phone = $request->input('phone');
            $user->cin = $request->input('cin');
            $user->fonction = $request->input('fonction');
            $user->date_embauche = $request->input('date_embauche');
            $user->niveau_etude = $request->input('niveau_etude');
            $user->adresse = $request->input('adresse');
            $user->cnss = $request->input('cnss');
            $user->is_actif = $request->input('is_actif'); // Default to 1 if not provided
            $user->solde_conge = $request->input('solde_conge');

            if ($request->hasFile('photo')) {
                if ($user->photo != null) {
                    $image_path = public_path('img/users/' . $user->photo);
                    if (file_exists($image_path)) {
                        unlink($image_path);
                    }
                }
                $photo = time() . '.' . $user->name . '.' . $request->photo->extension();
                $request->photo->move(public_path('img/users'), $photo);
                $user->photo = $photo;
            }

            if ($user->save()) {
                // Update the user in the 'temp' database connection (assuming this is what you intend to do)
                DatabaseHelper::Config($user->societe_id);
                $user_societes = User::on('temp')->where('user_id_origin', $user->id)->first();

                if ($user_societes) {
                    $user_societes->update($request->all());
                }
            }

            return response()->json(['message' => 'Utilisateur bien modifié'], 200);

        } else if (RoleHelper::AdminSup()) {

            if ($request->cin != null) {
                $cin_exist = User::where('cin', $request->cin)->where('id', '!=', $id)->count();
                if ($cin_exist > 0) {
                    return response()->json(['error' => 'Le Cin que vous avez saisi' . $request->cin . ' apprtient à un autre utilisateur'], 422);
                }}

            DatabaseHelper::Config();
            $user = User::on('temp')->findOrfail($id);
            $user->name = $request->input('name');
            $user->prenom = $request->input('prenom');
            $user->gender = $request->input('gender');
            $user->role = $request->input('role');
            $user->phone = $request->input('phone');
            $user->cin = $request->input('cin');
            $user->fonction = $request->input('fonction');
            $user->date_embauche = $request->input('date_embauche');
            $user->niveau_etude = $request->input('niveau_etude');
            $user->adresse = $request->input('adresse');
            $user->cnss = $request->input('cnss');
            $user->is_actif = $request->input('is_actif'); // Default to 1 if not provided
            $user->solde_conge = $request->input('solde_conge');

            if ($request->hasFile('photo')) {
                if ($user->photo != null) {
                    $image_path = public_path('img/users/' . $user->photo);
                    if (file_exists($image_path)) {
                        unlink($image_path);
                    }
                }
                $photo = time() . '.' . $user->name . '.' . $request->photo->extension();
                $request->photo->move(public_path('img/users'), $photo);
                $user->photo = $photo;
            }

            if ($user->save()) {
                $user_societes = User::where('id', $user->user_id_origin)->first();

                if ($user_societes) {
                    $user_societes->update($request->all());
                }
            }

            return response()->json(['message' => 'Utilisateur modifié avec succès'], 200);
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
                return response()->json(['message' => 'utilisateur supprimé avec succès'], 200);
            } else {
                return response()->json(['message' => "Oups l'utilisatuer n'a pas été supprimé"], 404);
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
            return response()->json(['message' => 'Utilisateur activé avec succès'], 200);
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
            return response()->json(['message' => 'Utilisateur désactivé avec succès'], 200);
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

            return response()->json(['message' => 'Utilisateur restauré avec succès'], 200);
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
            if ($request->selectedProjets) {
                foreach ($request->selectedProjets as $valeur) {
                    UserProjetHelper::createUserProjet($valeur, $user_id);
                }
                return response()->json(['message' => "projets affecté avec succès à l'utilisateur"], 200);
            } else {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }
    }
    public function sendEmail()

    {
        if (RoleHelper::SuperAdmin()) {

            $user = Auth::guard('api')->user()->email;

            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }
            DB::table('password_reset_tokens')
                ->where('email', $user)
                ->delete();

            $token = Str::random(60);
            $confirmationCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $expirationTime = now()->addMinutes(3); // Expires in 3 minute
            // Store the token in the 'password_resets' tablee chabge what time wann  to  expire tokkeenn
            DB::table('password_reset_tokens')->insert([
                'email' => $user,
                'token' => $token,
                'expires_at' => $expirationTime,
                'created_at' => now(),
            ]);

            // Construct the reset URL you can chenbge the url
            $resetUrl = 'http://localhost:3000/reset-password/' . $token;

            // Send an email to the user with the reset URL
            Mail::to($user)->send(new ResetPasswordMail($resetUrl,  $confirmationCode));

            return response()->json(['message' => 'Password reset email sent']);
        }
    }
    public function resendEmail()

    {
        if (RoleHelper::SuperAdmin()) {
            // Validate the request and check for user existence
            $user = Auth::guard('api')->user()->email;


            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }
            DB::table('password_reset_tokens')
                ->where('email', $user)
                ->delete();



            $token = Str::random(60);
            $confirmationCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $expirationTime = now()->addMinutes(3); // Expires in 1 minute
            // Store the token in the 'password_resets' table
            DB::table('password_reset_tokens')->insert([
                'email' => $user,
                'token' => $token,
                'expires_at' => $expirationTime,
                'created_at' => now(),
            ]);

            // Construct the reset URL
            $resetUrl = 'http://localhost:3000/reset-password/' . $token;

            // Send an email to the user with the reset URL
            Mail::to($user)->send(new ResetPasswordMail($resetUrl,  $confirmationCode));

            return response()->json(['message' => 'Password reset email sent']);
        }
    }


    public function resetPassword(Request $request, $token)
    {


        if (RoleHelper::ACSup()) {
            $passwordReset = DB::table('password_reset_tokens')
                ->where('token', $token)
                ->first();

            if (!$passwordReset) {
                return response()->json(['message' => 'Token not found'], 404);
            }

            if (now() > $passwordReset->expires_at) {
                return response()->json(['message' => 'Token has expired'], 401);
            }


            $user = User::where('email', $passwordReset->email)->first();
            $user->update([
                'password' => Hash::make($request->password),
            ]);


            DB::table('password_reset_tokens')
                ->where('token', $token)
                ->delete();

            return response()->json(['message' => 'Password reset successful']);
        }
    }
    public function validateToken($token)
    {



        $passwordReset = DB::table('password_reset_tokens')
            ->where('token', $token)
            ->first();

        if (!$passwordReset) {
            return response()->json(['message' => 'Token not found'], 404);
        }

        if (Carbon::now() > $passwordReset->expires_at) {
            DB::table('password_reset_tokens')
                ->where('token', $token)
                ->delete();
            return response()->json(['message' => 'Token has expired'], 401);
        }
        return response()->json(['message' => 'Token valid'], 200);
    }
    public function confirmReset(Request $request, $token)
    {
        $confirmationCode = $request->input('confirmationCode');

        $passwordReset = DB::table('password_reset_tokens')
            ->where('token', $token)
            ->first();

        if (!$passwordReset) {
            return response()->json(['message' => 'Token not found'], 404);
        }

        if (now() > $passwordReset->expires_at) {
            DB::table('password_reset_tokens')
                ->where('token', $token)
                ->delete();
            return response()->json(['message' => 'Token has expired'], 401);
        }

        if ($passwordReset->confirmation_code !== $confirmationCode) {
            return response()->json(['message' => 'Invalid confirmation code'], 400);
        }

        return response()->json(['message' => 'Code is valid'], 200);
    }

}
