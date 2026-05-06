<?php

namespace App\Http\Controllers;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Helpers\UserProjetHelper;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Mail\ResetPasswordMail;
use App\Models\Societe;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{

    public function login(Request $request)
    {
        // Validation des entrées
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Rechercher l'utilisateur par email
        $User = User::where('email', $request->email)->first();
        // Vérifier si l'utilisateur existe
        if (!$User) {
            return response()->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        // Vérifier si l'utilisateur est actif
        if ($User->is_actif != 1) {
            return response()->json(['error' => 'Utilisateur non actif'], 422);
        }

        // Vérifier les identifiants
        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $user->is_connected = 1;
            $user->save();

            // Générer le token d'accès
            $accessToken = $user->createToken('API Token')->accessToken;

            return response()->json(['access_token' => $accessToken], 200);
        }

        // Identifiants incorrects
        return response()->json(['error' => 'Email ou mot de passe incorrect'], 422);
    }
    public function logout(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();

            if (!$user) {
                return response()->json([
                    'message' => 'User not authenticated',
                ], 401);
            }

            // Revoke all access tokens for the user
            $user->tokens()->delete();

            // Reset societe_id for SuperAdmin
            if (RoleHelper::SuperAdmin()) {
                $user->societe_id = 1;
            }

            // Set user as disconnected
            $user->is_connected = 0;
            $user->save();

            return response()->json([
                'message' => 'Logout successful',
            ]);
        } catch (\Exception $e) {
            \Log::error('Logout error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Logout completed with warnings',
            ], 200); // Still return success to allow client-side cleanup
        }
    }

    public function Dashboard()
    {
        if (Auth::guard('api')->check()) {
            $user = Auth::guard('api')->user();

            // Update is_connected field
            $user->is_connected = 1;
            $user->save();

            // Return the response with user information
            return response()->json(['user' => $user]);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function get_users()
    {
        if (RoleHelper::Superadmin()) {
            if (Auth::guard('api')->user()->societe_id == 1) {
                $users = User::all();
                return response()->json(['users' => $users]);
            } else {
                DatabaseHelper::Config();
                $users = User::on('temp')->where('role', '!=', 1)->get();
                return response()->json(['users' => $users]);
            }

        } else if (RoleHelper::Admin()) {
            DatabaseHelper::Config();
            $users = User::on('temp')->where('role', '!=', 1)->get();
            return response()->json(['users' => $users], 200);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function get_commerciaux()
    {
        if (RoleHelper::Admin()) {
            DatabaseHelper::Config();

            $users = User::on('temp')->where('role',3)->get();
            return response()->json(['users' => $users], 200);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function index(Request $request)
    {
        if (RoleHelper::Superadmin()) {
            if (Auth::guard('api')->user()->societe_id == 1) {
                $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
                $page = $request->input('page', 1);
                $users = User::orderBy('created_at', 'desc')
                    ->paginate($perPage, ['*'], 'page', $page);
                return response()->json(['users' => $users]);
            } else {
                $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
                $page = $request->input('page', 1);
                $users = User::where('societe_id', Auth::guard('api')->user()->societe_id)
                    ->where('role', '!=', 1)
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage, ['*'], 'page', $page);
                return response()->json(['users' => $users]);
            }

        } else if (RoleHelper::Admin()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);
            $users = User::on('temp')->where('role', '!=', 1)->orderBy('created_at', 'desc')
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
            $user->is_actif = $request->is_actif;
            $user->nb_appel_recu = $request->nb_appel_recu;
            $user->nb_appel_traite = $request->nb_appel_traite;
            $user->solde_conge = $request->solde_conge;

            if ($request->hasFile('photo')) {
                $photo = time() . '.' . $request->name . '_' . $request->prenom . '.' . $request->photo->extension();
                $user->photo = $photo;

            }
            if ($user->save()) {
                if ($request->hasFile('photo')) {
                    $societe = Societe::findOrfail($user->societe_id);
                    $request->photo->move(public_path('docs/' . $societe->raison_sociale_concatene . '_' . $user->societe_id . '/users'), $photo);

                }
                $this->createSubUser($request, $user->id, $user->photo);
            }
            return response()->json(['message' => $user], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function createSubUser($request, $user_id, $user_photo)
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
        $user->is_actif = $request->is_actif;
        $user->nb_appel_recu = $request->nb_appel_recu;
        $user->nb_appel_traite = $request->nb_appel_traite;
        $user->solde_conge = $request->solde_conge;
        if ($request->hasFile('photo')) {
            $user->photo = $user_photo;

        }
        $user->save();

    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $userAuth = Auth::guard('api')->user();

        if (RoleHelper::Superadmin() && $userAuth->societe_id == 1) {
            // Récupérer l'utilisateur et compter ses relations
            $user = User::find($id);
        } else if (RoleHelper::Admin() || (RoleHelper::Superadmin() && $userAuth->societe_id != 1)) {
            DatabaseHelper::Config();
            $user = User::on('temp')
               // ->with(['projets', 'reservations', 'desistements', 'visites', 'avances', 'compromis_ventes', 'traitement_appels', 'contrat_ventes'])
                ->where('user_id_origin', $id)
                ->first();
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (!$user) {
            return response()->json(['message' => 'Utilisateur non trouvé'], 404);
        }

        return response()->json([
            'user' => $user,
        ], 200);
    }

   public function update(UpdateUserRequest $request, $id)
    {
    $user = User::findOrFail($id);

    if ($request->has('cin')) {
        $request->validate([
            'cin' => [
                'string',
                Rule::unique('users')->ignore($user->id)->whereNull('deleted_at'),
            ],
        ], [
            'cin.string' => 'Le CIN doit être une chaîne de caractères.',
            'cin.unique' => 'Ce CIN appartient déjà à un autre utilisateur.',
        ]);
    }

    if ($request->has('email')) {
        $request->validate([
            'email' => [
                'string',
                'email',
                Rule::unique('users')->ignore($user->id)->whereNull('deleted_at'),
            ],
        ], [
            'email.string' => 'L\'email doit être une chaîne de caractères.',
            'email.email' => 'Veuillez saisir une adresse email valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée par un autre utilisateur.',
        ]);
    }
        if ($request->is_profil) {
            $user = Auth::user();
            DatabaseHelper::Config();
            $user = User::on('temp')->where('user_id_origin', Auth::guard('api')->user()->id)->first();
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
            $user_origin = User::where('id', $user->user_id_origin)->first();
            $societe = Societe::findOrfail($user_origin->societe_id);
            $photo = '';
            if ($request->hasFile('photo')) {
                if ($user->photo != null) {
                    $image_path = asset('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/users' . $user_origin->photo);
                    //$image_path = public_path('docs/users/' . $user->photo);
                    if (file_exists($image_path)) {
                        unlink($image_path);
                    }
                }
                $photo = time() . '.' . $request->name . '_' . $request->prenom . '.' . $request->photo->extension();
                $request->photo->move(public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/users'), $photo);
                $user->photo = $photo;
            }

            if ($user->save()) {
                //Modifier dans la BDD Mère
                $user_origin = User::findOrFail($id);
                if ($user_origin) {
                    $user_origin->update($request->all());
                    if ($request->hasFile('photo')) {
                        $user_origin->photo = $photo;
                        $user_origin->save();
                    }
                }

                return response()->json(['message' => 'profil modifié avec succès'], 200);

            }
        } else if (RoleHelper::Superadmin()) {
            $user = User::findOrFail($id);
            $user->name = $request->input('name');
            $user->prenom = $request->input('prenom');
            $user->email = $request->input('email');
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
            $photo = '';
            if ($request->hasFile('photo')) {
                if ($user->photo != null) {
                    $image_path = public_path('docs/users/' . $user->photo);
                    if (file_exists($image_path)) {
                        unlink($image_path);
                    }
                }
                $photo = time() . '.' . $request->name . '_' . $request->prenom . '.' . $request->photo->extension();
                $societe = Societe::findOrfail($user->societe_id);
                $request->photo->move(public_path('docs/' . $societe->raison_sociale_concatene . '_' . $user->societe_id . '/users'), $photo);
                $user->photo = $photo;
            }
            if ($user->save()) {
                // Update the user in the 'temp' database connection (assuming this is what you intend to do)
                DatabaseHelper::Config($user->societe_id);
                $user_societes = User::on('temp')->where('user_id_origin', $user->id)->first();

                if ($user_societes) {
                    $user_societes->update($request->all());
                    if ($request->hasFile('photo')) {
                        $user_societes->photo = $photo;
                        $user_societes->save();
                    }
                }
            }

            return response()->json(['message' => 'Utilisateur modifié avec succès par super admin'], 200);

        } else if (RoleHelper::Admin()) {
            DatabaseHelper::Config();
            $user = User::on('temp')->findOrfail($id);
            $user->name = $request->input('name');
            $user->prenom = $request->input('prenom');
            $user->email = $request->input('email');
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
            $user_societes = User::where('id', $user->user_id_origin)->first();
            $photo = '';
            if ($request->hasFile('photo')) {
                if ($user->photo != null) {
                    $image_path = public_path('docs/users/' . $user->photo);
                    if (file_exists($image_path)) {
                        unlink($image_path);
                    }
                }
                $photo = time() . '.' . $request->name . '_' . $request->prenom . '.' . $request->photo->extension();
                $societe = Societe::findOrfail($user_societes->societe_id);
                $request->photo->move(public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/users'), $photo);
                $user->photo = $photo;
            }

            if ($user->save()) {

                if ($user_societes) {
                    $user_societes->update($request->all());
                    if ($request->hasFile('photo')) {
                        $user_societes->photo = $photo;
                        $user_societes->save();
                    }
                }
            }

            return response()->json(['message' => 'Utilisateur modifié avec succès avec admin'], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public static function destroy($id)
    {
        if (RoleHelper::SuperAdmin()) {
            $user = User::findOrFail($id);
            $user->is_actif = 0;
            $user->save();

            if ($user->delete()) {

                DatabaseHelper::Config($user->societe_id);
                $user_societes = User::on('temp')->where('user_id_origin', $user->id);
                $user_societes->update(['is_actif' => 0]);
                /* if ($user_societes->photo != null) {
                $image_path = public_path('docs/users/' . $user_societes->photo);
                if (file_exists($image_path)) {
                File::delete($image_path);
                }
                } */
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
        if (RoleHelper::AdminSup()) {
            $user = User::findOrFail($user_id);
            $user->is_actif = 1;
            if ($user->save()) {
                DatabaseHelper::Config($user->societe_id);
                $user_societes = User::on('temp')->where('user_id_origin', $user->id);
                $user_societes->update(['is_actif' => 1]);
                return response()->json(['message' => 'utilisateur activé avec succès'], 200);

            }
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
                DatabaseHelper::Config($user->societe_id);
                $user_societes = User::on('temp')->where('user_id_origin', $user->id);
                $user_societes->update(['is_actif' => 0]);
                return response()->json(['message' => 'utilisateur désactivé avec succès'], 200);

            }
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
            $expirationTime = now()->addMinutes(3);
            DB::table('password_reset_tokens')->insert([
                'email' => $user,
                'token' => $token,
                'expires_at' => $expirationTime,
                'created_at' => now(),
            ]);

            // Construct the reset URL you can chenbge the url
            $resetUrl = env('APP_URL').'/reset-password/' . $token;

            // Send an email to the user with the reset URL
            Mail::to($user)->send(new ResetPasswordMail($resetUrl, $confirmationCode));

            return response()->json(['message' => 'Password reset email sent']);
        }
    }
    public function resendEmail(Request $request)
    {
        // Validate the request and check for user existence
        $request->validate([
            'email' => 'required|email',
        ]);

        // Rechercher l'utilisateur par email
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->delete();

        $token = Str::random(60);
        $confirmationCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expirationTime = now()->addMinutes(3); // Expires in 1 minute
        // Store the token in the 'password_resets' table
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => $token,
            'expires_at' => $expirationTime,
            'created_at' => now(),
        ]);

        // Construct the reset URL
        $resetUrl = env('HOST_NAME_FRONT') . '/reset-password/' . $token;
        // Send an email to the user with the reset URL
        Mail::to($user->email)->send(new ResetPasswordMail($resetUrl, $confirmationCode));

        return response()->json(['message' => 'Password reset email sent']);

    }

    public function resetPassword(Request $request, $token)
    {

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
        $user->update(['password' => Hash::make($request->password)]);

        DB::table('password_reset_tokens')
            ->where('token', $token)
            ->delete();

        return response()->json(['message' => 'Password reset successful']);

    }

    public function reset(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|confirmed',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Ancien mot de passe incorrect'], 400);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return response()->json(['message' => 'Mot de passe réinitialisé avec succès'], 200);
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
