<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\FichierHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Helpers\UserProjetHelper;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Mail\ResetPasswordMail;
use App\Models\Objectif;
use App\Models\Societe;
use App\Models\UserProjet;
use App\Models\V1\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\Projet;


class UserController extends Controller
{

    /**
     * GET    /       index
     * POST   /       store
     * GET    /{id}   show
     * PUT    /{id}   update
     * DELETE /{id}   destroy
     */
    public function get_commerciaux($projet_id)
    {
        if (RoleHelper::AdminSup()  ||RoleHelper::RespoCommercial()||RoleHelper::AgentAdmin()) {
            DatabaseHelper::Config();
            //->where('role',3)
            $users = UserProjet::on('temp')->with('user')
                ->where('projet_id', $projet_id)
                ->whereHas('user', function ($q) {
                    $q->where('role', 3);

                })->distinct()->get();
            return response()->json(['users' => $users], 200);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function get_users()
    {
        if (RoleHelper::Superadmin()) {

                DatabaseHelper::Config();
                $users = User::on('temp')->where('role','>',1)->where('is_actif',1)->get();
                return response()->json(['users' => $users]);


        } else if (RoleHelper::Admin()||RoleHelper::AgentAdmin()) {
            DatabaseHelper::Config();
            $users = User::on('temp')->where('role','>',1)->where('is_actif',1)->get();
            return response()->json(['users' => $users], 200);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }
    public function list_commerciaux_objectif($projet_id)
    {

        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            if (RoleHelper::AdminSup() ||RoleHelper::AgentAdmin() ) {
                $objectifs = Objectif::on('temp')->distinct(['user_id'])->get('user_id');
                $arrQuery  = [];
                for ($i = 0; $i < count($objectifs); $i++) {
                    array_push($arrQuery, $objectifs[$i]->user_id);
                }
                //stock all user_id into array and get users where user_id not in tble objectif
                $data = UserProjet::on('temp')->with('user')
                    ->where('projet_id', $projet_id)
                    ->whereHas('user', function ($q) {
                        $q->where('role', 3);

                    })->whereNotIn('user_id', $arrQuery)->get();

            }
            return response()->json(['users' => $data]);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function list_commerciaux($projet_id)
    {

        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            if (RoleHelper::AdminSup() ||RoleHelper::AgentAdmin() ) {

                //stock all user_id into array and get users where user_id not in tble objectif
                $query = UserProjet::on('temp')->with('user')->distinct(['user_id'])
                    ->whereHas('user', function ($q) {
                        $q->where('role', 3);
                    });
                if ($projet_id != 0) {
                    $query->where('projet_id', $projet_id);
                }
                $query->get('user_id');

                $data = $query->get('user_id');
                return response()->json(['data' => $data]);
            }

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function index(Request $request)
    {

        // Vérifier si l'utilisateur est authentifié
        if (! Auth::guard('api')->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Identifier l'utilisateur authentifié
        $user = Auth::guard('api')->user();

        // Définir la taille de pagination par défaut
        $size = $request->input('size', config('app.default_item_number_perpage'));
        $page = $request->input('page', 1);

        $query = User::query();
        // Filtrer par nom si le nom est spécifié
        if ($request->filled('nom')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->input('nom') . '%')
                    ->orWhere('prenom', 'like', '%' . $request->input('nom') . '%');
            });
        }

        // Filtrer par prénom si l'email est spécifié
        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->input('email') . '%');
        }
        // Filtrer par téléphone
        if ($request->filled('telephone')) {
            $query->where('phone', 'like', '%' . $request->input('telephone') . '%');
        }
        if ($request->filled('status')) {
            $query->where('is_actif', 'like', '%' . $request->input('status') . '%');
        }
        if ($request->filled('gender')) {
            $query->where('gender', 'like', '%' . $request->input('gender') . '%');
        }
        if ($request->filled('role')) {
            $query->where('role', 'like', '%' . $request->input('role') . '%');
        }
         if ($request->filled('niveau')) {
            $query->where('niveau_etude', 'like', '%' . $request->input('niveau') . '%');
        }


        // Si l'utilisateur s'agit d'un 'superadmin'
        if (RoleHelper::Superadmin() && $user->societe_id == 1) {
            // Filtrer par société si la société est spécifiée
            if ($request->filled('societe')) {
                $query->whereHas('societe', function ($subQuery) use ($request) {
                    $subQuery->where('raison_sociale', 'like', '%' . $request->input('societe') . '%');
                });
            }

            if ($request->filled('role')) {
                $query->where('role', $request->input('role'));
            }
        } // Sinon, si l'utilisateur est 'admin'
        else if (RoleHelper::Admin() ||RoleHelper::AgentAdmin() || (RoleHelper::Superadmin() && $user->societe_id != 1)) {
            // Filtrer avec l'id de la société et exclure les utilisateurs ayant le role superAdmin
            $query->where('societe_id', $user->societe_id)->where('role', '!=', 1);
        }
        // Récupérer les utilisateurs avec pagination en fonction des filtres appliqués
        $users = $query->orderBy('created_at', 'desc')
            ->paginate($size, ['*'], 'page', $page);

        // Extraire les propriétés de pagination du paginateur
        $pagination = [
            'currentPage' => $users->currentPage(),
            'totalItems'  => $users->total(),
            'totalPages'  => $users->lastPage(),
        ];

        // Extraire les éléments d'utilisateur du paginateur
        $users = $users->items();

        // Retourner la réponse simplifiée
        return response()->json([
            'users'      => $users,
            'pagination' => $pagination,
        ], 200);
    }
     private function getRoleLabel($role)
    {
        $roles = [
            1 => 'Super Administrateur',
            2 => 'Administrateur',
            3 => 'Commercial',
            5 => 'Notaire',
            6 => 'Responsable Livraison',
            7 => 'Comptable',
            8 => 'Service Après-Vente',
            9 => 'Responsable Commercial',
            10 => 'Agent de saisie'
        ];

        return $roles[$role] ?? 'Utilisateur';
    }
    public function store(StoreUserRequest $request)
    {
        /*  if ($request->cin != null) {
        $cin_exist = User::where('cin', $request->cin)->count();
        if ($cin_exist > 0) {
        return response()->json(['error' => 'Le Cin que vous avez saisi' . $request->cin . ' apprtient à un autre utilisateur'], 422);
        }
        } */
         DB::connection()->beginTransaction();
        if (RoleHelper::SuperAdmin()) {
            try{
                $user                  = new User();
                $user->name            = $request->name;
                $user->societe_id      = $request->societe_id;
                $user->prenom          = $request->prenom;
                $user->email           = $request->email;
                $user->password        = $request->password;
                $user->gender          = $request->gender;
                $user->role            = $request->role;
                $user->phone           = $request->phone;
                $user->cin             = $request->cin;
                $user->fonction        = $request->fonction;
                $user->date_embauche   = $request->date_embauche;
                $user->niveau_etude    = $request->niveau_etude;
                $user->adresse         = $request->adresse;
                $user->cnss            = $request->cnss;
                $user->is_actif        = $request->is_actif;
                $user->nb_appel_recu   = $request->nb_appel_recu;
                $user->nb_appel_traite = $request->nb_appel_traite;
                $user->solde_conge     = $request->solde_conge;
                // Déterminer le libellé du rôle
                  $roleLabel = $this->getRoleLabel($request->role);
                    if ($request->hasFile('photo')) {
                        $societe = Societe::findOrFail($request->societe_id);
                        $filename = time() . '.' . $request->name . '_' . $request->prenom . '.' . $request->photo->extension();

                        // Utiliser FichierHelper au lieu de move directement
                        FichierHelper::ajouter_fichier(
                            $request->photo,
                            $societe->raison_sociale_concatene,
                            $user->societe_id,
                            'users',
                            $filename
                        );
                        $user->photo = $filename;
                    }

                 if ($user->save()) {
                        $user->user_id_origin=$user->id;
                        $user->save();
                   // $dataArray_projets = json_decode($request->input('selectedProjets', '[]'), true);

                   // $this->createSubUser($request, $user->id, $user->photo, $dataArray_projets == null ? [] : $dataArray_projets);

                    //send accces par email to user

                    $to_email = $user->email;
                    $data = [
                    'password' => $request->password,
                    'sexe' => $request->gender,
                    'nom' => $request->name,
                    'prenom' => $request->prenom,
                    'email' => $request->email,
                    'role' => $roleLabel,
                    'role_value' => $request->role
                    ];
                    Mail::send('User.mail', $data, function ($message) use ($to_email) {
                        $message->to($to_email)
                            ->subject('Bienvenue chez Tracimo - Votre compte a été créé');
                        $message->from(env('MAIL_USERNAME'), 'Tracimo ');
                    });
                }
                // Commit transaction if everything is successful
                DB::connection()->commit();
                return response()->json(['message' => $user], 200);
            }catch (\Exception $e) {
                // Rollback transaction on error
                DB::connection()->rollBack();

                \Log::error("User creation failed: " . $e->getMessage());
                return response()->json(['error' => 'User creation failed: ' . $e->getMessage()], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function show($id)
    {
        $userAuth = Auth::guard('api')->user();

        if (RoleHelper::Admin() || RoleHelper::AgentAdmin()||(RoleHelper::Superadmin())) {
            DatabaseHelper::Config();
            $user = User::on('temp')
               // ->with(['projets', 'reservations', 'desistements', 'visites', 'avances', 'compromis_ventes', 'traitement_appels', 'contrat_ventes'])
               // ->withCount(['projets', 'reservations', 'desistements', 'visites', 'avances', 'compromis_ventes', 'traitement_appels', 'contrat_ventes'])
                ->where('id', $id)
                ->first();
            $projets=Projet::on('temp')->without('typeProjet','userProjet','societe')->get();
            $projets_de_user=userProjet::on('temp')->with('projet')->where('user_id',$user->id)->get()->pluck('projet');
            } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        /*if (RoleHelper::Superadmin() && $userAuth->societe_id == 1) {
            // Récupérer l'utilisateur et compter ses relations
            $user = User::find($id);
            $projets=[];
            $projets_de_user=[];
        } else if (RoleHelper::Admin() || RoleHelper::AgentAdmin()||(RoleHelper::Superadmin() && $userAuth->societe_id != 1)) {
            DatabaseHelper::Config();
            $user = User::on('temp')
               // ->with(['projets', 'reservations', 'desistements', 'visites', 'avances', 'compromis_ventes', 'traitement_appels', 'contrat_ventes'])
               // ->withCount(['projets', 'reservations', 'desistements', 'visites', 'avances', 'compromis_ventes', 'traitement_appels', 'contrat_ventes'])
                ->where('id', $id)
                ->first();
            $projets=Projet::on('temp')->without('typeProjet','userProjet','societe')->get();
            $projets_de_user=userProjet::on('temp')->with('projet')->where('user_id',$user->id)->get()->pluck('projet');
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }*/

        if (! $user) {
            return response()->json(['message' => 'Utilisateur non trouvé'], 404);
        }

        return response()->json([
            'user' => $user,
            'projets'=>$projets,
            'projets_de_user'=>$projets_de_user,
        ], 200);
    }


/**
 * Update user information
 *
 * @param UpdateUserRequest $request
 * @param int $id
 * @return \Illuminate\Http\JsonResponse
 */
public function update(UpdateUserRequest $request, $id)
{
    DB::connection()->beginTransaction();

    try {
        // Récupérer l'utilisateur
        $user = User::findOrFail($id);
        $old_email = $user->email;
        $old_role = $user->role;
        $passwordUpdated = false;

        // ========== 1. VALIDATIONS ==========
        // Validation du CIN
        if ($request->has('cin') && !empty($request->cin)) {
            $request->validate([
                'cin' => [
                    'string',
                    'max:255',
                    Rule::unique('users')->ignore($user->id)->whereNull('deleted_at'),
                ],
            ], [
                'cin.unique' => 'Ce CIN appartient déjà à un autre utilisateur.',
                'cin.string' => 'Le CIN doit être une chaîne de caractères.',
                'cin.max' => 'Le CIN ne doit pas dépasser 255 caractères.',
            ]);
        }

        // Validation de l'email
        if ($request->has('email') && !empty($request->email)) {
            $request->validate([
                'email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users')->ignore($user->id)->whereNull('deleted_at'),
                ],
            ], [
                'email.unique' => 'Cette adresse email est déjà utilisée par un autre utilisateur.',
                'email.required' => 'L\'email est requis.',
                'email.email' => 'Veuillez saisir une adresse email valide.',
            ]);
        }

        // ========== 2. MISE À JOUR DES CHAMPS ==========
        if ($request->has('name')) {
            $user->name = $request->input('name');
        }

        if ($request->has('prenom')) {
            $user->prenom = $request->input('prenom');
        }

        if ($request->has('email')) {
            $user->email = $request->input('email');
        }

        if ($request->has('gender')) {
            $user->gender = $request->input('gender');
        }

        if ($request->has('role')) {
            $user->role = $request->input('role');
        }

        if ($request->has('phone')) {
            $user->phone = $request->input('phone');
        }

        if ($request->has('cin')) {
            $user->cin = $request->input('cin');
        }

        if ($request->has('fonction')) {
            $user->fonction = $request->input('fonction');
        }

        if ($request->has('date_embauche')) {
            $user->date_embauche = $request->input('date_embauche');
        }

        if ($request->has('niveau_etude')) {
            $user->niveau_etude = $request->input('niveau_etude');
        }

        if ($request->has('adresse')) {
            $user->adresse = $request->input('adresse');
        }

        if ($request->has('cnss')) {
            $user->cnss = $request->input('cnss');
        }

        if ($request->has('is_actif')) {
            $user->is_actif = $request->input('is_actif');
        }

        if ($request->has('solde_conge')) {
            $user->solde_conge = $request->input('solde_conge');
        }

        if ($request->has('nb_appel_recu')) {
            $user->nb_appel_recu = $request->input('nb_appel_recu');
        }

        if ($request->has('nb_appel_traite')) {
            $user->nb_appel_traite = $request->input('nb_appel_traite');
        }

        // ========== GESTION DU MOT DE PASSE ==========
        $plainPassword = null;
        if ($request->has('password') && !empty($request->password)) {
            $plainPassword = $request->password;
            $user->password = Hash::make($request->password);
            $passwordUpdated = true;
        }

        // ========== 3. GESTION DE LA PHOTO ==========
        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            $societe = Societe::findOrFail($user->societe_id);

            if ($user->photo && !empty($user->photo)) {
                try {
                    FichierHelper::supprimer_fichier(
                        $societe->raison_sociale_concatene,
                        $user->societe_id,
                        'users',
                        $user->photo
                    );
                } catch (\Exception $e) {
                    \Log::warning("Erreur suppression ancienne photo: " . $e->getMessage());
                }
            }

            $nameForFile = $request->has('name') ? $request->name : $user->name;
            $prenomForFile = $request->has('prenom') ? $request->prenom : $user->prenom;
            $safeName = preg_replace('/[^a-zA-Z0-9]/', '_', $nameForFile);
            $safePrenom = preg_replace('/[^a-zA-Z0-9]/', '_', $prenomForFile);
            $extension = $request->photo->getClientOriginalExtension();
            $filename = time() . '_' . $safeName . '_' . $safePrenom . '.' . $extension;

            FichierHelper::ajouter_fichier(
                $request->photo,
                $societe->raison_sociale_concatene,
                $user->societe_id,
                'users',
                $filename
            );

            $user->photo = $filename;
        }

        // ========== 4. SAUVEGARDE ==========
        $user->save();

        // ========== 5. GESTION DES PROJETS ==========
        if (RoleHelper::AdminSup() || RoleHelper::AgentAdmin()) {
            UserProjet::where('user_id', $user->id)->delete();

            if (!empty($request->selectedProjets)) {
                $projets_array = explode(',', $request->selectedProjets);
                foreach ($projets_array as $id_projet) {
                    UserProjetHelper::createUserProjet($id_projet, $user->id);
                }
            }
        }

        // ========== 6. ENVOI D'EMAIL SI CHANGEMENT D'EMAIL OU MOT DE PASSE OU RÔLE ==========
        if ($old_email != $request->email || $passwordUpdated || ($request->has('role') && $old_role != $request->role)) {
            $to_email = $user->email;

            // Déterminer le libellé du nouveau rôle
            $roleLabel = $this->getRoleLabel($user->role);

            $emailData = [
                'password' => $plainPassword,
                'sexe' => $request->gender,
                'nom' => $request->name,
                'prenom' => $request->prenom,
                'email' => $request->email,
                'role' => $roleLabel,
                'password_changed' => $passwordUpdated,
                'email_changed' => ($old_email != $request->email),
                'role_changed' => ($request->has('role') && $old_role != $request->role)
            ];

            Mail::send('User.mail_update', $emailData, function ($message) use ($to_email) {
                $message->to($to_email)
                    ->subject('Mise à jour de votre compte Tracimo ');
                $message->from(env('MAIL_USERNAME'), 'Tracimo');
            });
        }

        DB::connection()->commit();

        $message = ($request->has('is_profil') && $request->is_profil)
            ? 'Profil modifié avec succès'
            : 'Utilisateur modifié avec succès';

        return response()->json([
            'success' => true,
            'message' => $message,
            'user' => $user->fresh()
        ], 200);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        DB::connection()->rollBack();
        \Log::error("Utilisateur {$id} non trouvé");
        return response()->json([
            'success' => false,
            'message' => 'Utilisateur non trouvé'
        ], 404);

    } catch (\Illuminate\Validation\ValidationException $e) {
        DB::connection()->rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Erreur de validation',
            'errors' => $e->errors()
        ], 422);

    } catch (\Exception $e) {
        DB::connection()->rollBack();
        \Log::error("Update user failed: " . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ], 500);
    }
}



   public function update_personal_info(Request $request, $id)
{
    try {
        DB::connection()->beginTransaction();

        $user = User::findOrFail($id);

        // Update basic info
        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('prenom')) {
            $user->prenom = $request->prenom;
        }
        if ($request->has('gender')) {
            $user->gender = $request->gender;
        }
        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }

        // Handle photo upload if present
        if ($request->hasFile('photo')) {
            $societe = Societe::findOrFail($user->societe_id);

            // Delete old photo if exists
            if ($user->photo != null) {
                /*$old_photo_path = public_path('docs/'. $societe->raison_sociale_concatene . '_' . $user->societe_id . '/users/' . $user->photo);
                if (file_exists($old_photo_path)) {
                    unlink($old_photo_path);
                }*/
                    FichierHelper::supprimer_fichier(
                    $societe->raison_sociale_concatene,
                    $user->societe_id,
                    'users',
                    $user->photo
                );
            }

            // Get the file extension
            $extension = $request->photo->getClientOriginalExtension();

            // Generate filename using current user data (or fallback to user's existing data)
            $nameForFile = $request->has('name') ? $request->name : $user->name;
            $prenomForFile = $request->has('prenom') ? $request->prenom : $user->prenom;

            // Create a safe filename (remove special characters)
            $safeName = preg_replace('/[^a-zA-Z0-9]/', '_', $nameForFile);
            $safePrenom = preg_replace('/[^a-zA-Z0-9]/', '_', $prenomForFile);

            $photo = time() . '.' . $safeName . '_' . $safePrenom . '.' . $extension;

           // Upload new photo - UTILISER FichierHelper
            FichierHelper::ajouter_fichier(
                $request->photo,
                $societe->raison_sociale_concatene,
                $user->societe_id,
                'users',
                $photo
            );
            $user->photo = $photo;
        }

        if ($user->save()) {
            /*Update in temp database if exists
            DatabaseHelper::Config($user->societe_id);
            $user_temp = User::on('temp')->where('id', $user->id)->first();

            if ($user_temp) {
                $user_temp->name = $user->name;
                $user_temp->prenom = $user->prenom;
                $user_temp->gender = $user->gender;
                $user_temp->phone = $user->phone;

                if ($request->hasFile('photo')) {
                    $user_temp->photo = $user->photo;
                }

                $user_temp->save();
            }*/
        }

        DB::connection()->commit();

        // Refresh user model to get all attributes
        $user = User::find($id);

        return response()->json([
            'success' => true,
            'message' => 'Informations personnelles mises à jour avec succès',
            'user' => $user
        ], 200);

    } catch (\Exception $e) {
        DB::connection()->rollBack();
        \Log::error("Update personal info failed: " . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Échec de la mise à jour: ' . $e->getMessage()
        ], 500);
    }
}

public function update_password(Request $request, $id)
    {
        try {
            DB::connection()->beginTransaction();

            $user = User::findOrFail($id);

            // Update password
            $user->password = Hash::make($request->new_password);
            $user->save();

            /* Update in temp database if exists
            DatabaseHelper::Config($user->societe_id);
            $user_temp = User::on('temp')->where('id', $user->id)->first();

            if ($user_temp) {
                $user_temp->password = $user->password;
                $user_temp->save();
            }*/

            DB::connection()->commit();

            // Optional: Log out user from other devices or send notification email
            // $user->tokens()->delete(); // If using Laravel Sanctum

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe mis à jour avec succès'
            ], 200);

        } catch (\Exception $e) {
            DB::connection()->rollBack();
            \Log::error("Update password failed: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Échec de la mise à jour du mot de passe: ' . $e->getMessage()
            ], 500);
        }
    }
    public static function destroy($id)
{
    if (!RoleHelper::SuperAdmin()) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    try {
        DB::connection()->beginTransaction();

        $user = User::findOrFail($id);

        // Récupérer la société pour la suppression du fichier
        $societe = Societe::find($user->societe_id);

        // Supprimer les associations user_projet dans la base principale
        UserProjet::where('user_id', $id)->delete();

        // Supprimer de la base temp si elle existe et configurée
        try {

            if ($user && $$user->photo && $societe) {
                FichierHelper::supprimer_fichier(
                    $societe->raison_sociale_concatene,
                    $user->societe_id,
                    'users',
                    $user->photo
                );
            }

            // Supprimer l'utilisateur temp

        } catch (\Exception $e) {
            \Log::warning("Erreur lors de la suppression dans la base temp: " . $e->getMessage());
            // On continue car l'important est de supprimer l'utilisateur principal
        }

        // Désactiver et supprimer l'utilisateur principal
        $user->is_actif = 0;
        $user->save();

        if (!$user->delete()) {
            throw new \Exception("Impossible de supprimer l'utilisateur");
        }

        DB::connection()->commit();

        return response()->json(['message' => 'Utilisateur et ses associations supprimés avec succès'], 200);

    } catch (\Exception $e) {
        DB::connection()->rollBack();
        \Log::error("Erreur lors de la suppression de l'utilisateur {$id}: " . $e->getMessage());

        return response()->json([
            'message' => "Erreur lors de la suppression: " . $e->getMessage()
        ], 500);
    }
}


    // Methodes utilitaires (partie invisible a l'exterieur de la classe)
 private function createSubUser($request, $user_id, $user_photo, $dataArray_projets)
    {

        // Démarrer la transaction sur la connexion 'temp'
         DB::beginTransaction();

        try {
            $societe = Societe::find($request->societe_id);
            if (!$societe) {
                throw new \Exception("Societe with ID {$request->societe_id} not found");
            }
            DatabaseHelper::Config($request->societe_id);
            $existingUser = User::on('temp')
            ->where('email', $request->email)
            ->first();

            if ($existingUser) {
                // L'email est déjà utilisé dans cette société
                throw new \Exception("L'adresse email est déjà utilisée dans cette société.");
            }
            $user = new User();
            $user->setConnection('temp');
            $user->user_id_origin  = $user_id;
            $user->name            = $request->name;
            $user->prenom          = $request->prenom;
            $user->email           = $request->email;
            $user->password        = $request->password;
            $user->gender          = $request->gender;
            $user->role            = $request->role;
            $user->phone           = $request->phone;
            $user->cin             = $request->cin;
            $user->fonction        = $request->fonction;
            $user->date_embauche   = $request->date_embauche;
            $user->niveau_etude    = $request->niveau_etude;
            $user->adresse         = $request->adresse;
            $user->cnss            = $request->cnss;
            $user->is_actif        = $request->is_actif;
            $user->nb_appel_recu   = $request->nb_appel_recu;
            $user->nb_appel_traite = $request->nb_appel_traite;
            $user->solde_conge     = $request->solde_conge;
            if ($request->hasFile('photo')) {
                $user->photo = $user_photo;
            }

            if ($user->save()) {
                foreach ($dataArray_projets as $valeur) {
                    UserProjetHelper::createUserProjet($valeur['id'], $user->id);
                }
            }
            // Valider la transaction si tout est réussi
        DB::commit();

        } catch (\Exception $e) {
            // Annuler la transaction en cas d'erreur
            DB::rollBack();

            // Re-lancer l'exception pour qu'elle soit capturée par le bloc catch du parent
            throw new \Exception("Erreur lors de la création du sous-utilisateur: " . $e->getMessage());
        }

    }

    public function activateUser($user_id)
    {
        if (RoleHelper::AdminSup()  ) {
            $user           = User::findOrFail($user_id);
            $user->is_actif = 1;
            if ($user->save()) {
                DatabaseHelper::Config($user->societe_id);
                $user_societes = User::on('temp')->where('id', $user->id);
                $user_societes->update(['is_actif' => 1]);
                return response()->json(['message' => 'utilisateur activé avec succès'], 200);

            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function desactivateUser($user_id)
    {
        if (RoleHelper::AdminSup()  ) {
            $user           = User::findOrFail($user_id);
            $user->is_actif = 0;
            if ($user->save()) {
                DatabaseHelper::Config($user->societe_id);
                $user_societes = User::on('temp')->where('id', $user->id);
                $user_societes->update(['is_actif' => 0]);
                return response()->json(['message' => 'utilisateur désactivé avec succès'], 200);

            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }

    public function sendEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Rechercher l'utilisateur par email
        $user = User::where('email', $request->email)->first();

        // Vérifier si l'utilisateur existe
        if (!$user) {
            return response()->json(['error_' => "Nous n'avons pas trouvé de compte associé à cette adresse e-mail."], 404);
        }

        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->delete();

        $token            = Str::random(60);
        $confirmationCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expirationTime   = now()->addMinutes(60);
        DB::table('password_reset_tokens')->insert([
            'email'      => $user->email,
            'token'      => $token,
            'expires_at' => $expirationTime,
            'created_at' => now(),
        ]);

        // Construct the reset URL you can chenbge the url
        $resetUrl = env('FRONTEND_URL').'/reset-password/' . $token;

        // Send an email to the user with the reset URL
        Mail::to($user->email)->send(new ResetPasswordMail($resetUrl, $confirmationCode));

        return response()->json(['message' => 'Un email avec les instructions a été envoyé si l\'adresse est associée à un compte']);
        // }
    }
   /* public function resendEmail(Request $request)
    {
        // Validate the request and check for user existence
        $request->validate([
            'email' => 'required|email',
        ]);

        // Rechercher l'utilisateur par email
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->delete();

        $token            = Str::random(60);
        $confirmationCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expirationTime   = now()->addMinutes(60); // Expires in 1 minute
                                                  // Store the token in the 'password_resets' table
        DB::table('password_reset_tokens')->insert([
            'email'      => $user->email,
            'token'      => $token,
            'expires_at' => $expirationTime,
            'created_at' => now(),
        ]);

        // Construct the reset URL
        $resetUrl = env('HOST_NAME_FRONT') . '/reset-password/' . $token;
        // Send an email to the user with the reset URL
        Mail::to($user->email)->send(new ResetPasswordMail($resetUrl, $confirmationCode));

        return response()->json(['message' => 'Password reset email sent']);

    }*/

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

}
