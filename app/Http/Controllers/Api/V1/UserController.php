<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\FichierHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Societe;
use App\Models\UserProjet;
use App\Models\Objectif;
use App\Http\Helpers\UserProjetHelper;
use App\Models\V1\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Mail;
use Illuminate\Support\Facades\DB;


use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Mail\ResetPasswordMail;
use Illuminate\Support\Facades\Hash;


class UserController extends Controller
{

    /**
     * GET    /       index
     * POST   /       store
     * GET    /{id}   show
     * PUT    /{id}   update
     * DELETE /{id}   destroy
     */

    public function list_commerciaux_objectif ($projet_id){

        if (Auth::guard('api')->check() ) {
            DatabaseHelper::Config();
            if(RoleHelper::AdminSup()){
                $objectifs = Objectif::on('temp')->distinct(['user_id'])->get('user_id');
                $arrQuery = array();;
                for ($i = 0; $i < count($objectifs); $i++) {
                array_push($arrQuery, $objectifs[$i]->user_id);
                  }
                  //stock all user_id into array and get users where user_id not in tble objectif
               $data=UserProjet::on('temp')->with('user')
               ->where('projet_id',$projet_id)
               ->whereHas('user', function($q){
                $q->where('role',3);

            })->whereNotIn('user_id', $arrQuery)->get();

            }
           return response()->json(['users' => $data]);
        }
         else{
            return response()->json(['error' => 'Unauthorized'], 401);
         }
    }

    public function list_commerciaux ($projet_id){

        if (Auth::guard('api')->check() ) {
            DatabaseHelper::Config();
            if(RoleHelper::AdminSup()){

                  //stock all user_id into array and get users where user_id not in tble objectif
                $query = UserProjet::on('temp')->with('user')->distinct(['user_id'])
                ->whereHas('user', function($q){
                    $q->where('role',3);
                    });
                    if ($projet_id!=0) {
                        $query->where('projet_id',  $projet_id );
                    }
                    $query->get('user_id');

                $data = $query->get('user_id');
                return response()->json(['data' => $data]);
            }

        }
         else{
            return response()->json(['error' => 'Unauthorized'], 401);
         }
    }

    public function index(Request $request)
    {

        // Vérifier si l'utilisateur est authentifié
        if (!Auth::guard('api')->check()) {
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
            $query->where('name', 'like', '%' . $request->input('nom') . '%');
        }
        // Filtrer par prénom si le prénom est spécifié
        if ($request->filled('prenom')) {
            $query->where('prenom', 'like', '%' . $request->input('prenom') . '%');
        }
        // Filtrer par prénom si l'email est spécifié
        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->input('email') . '%');
        }
        // Filtrer par téléphone
        if ($request->filled('telephone')) {
            $query->where('phone', 'like', '%' . $request->input('telephone') . '%');
        }

        // Si l'utilisateur s'agit d'un 'superadmin'
        if (RoleHelper::Superadmin() && $user->societe_id == 1) {
            // Filtrer par société si la société est spécifiée
            if ($request->filled('societe')) {
                $query->whereHas('societe', function ($subQuery) use ($request) {
                    $subQuery->where('raison_sociale', 'like', '%' . $request->input('societe') . '%');
                });
            }

            // Filtrer par rôle si le rôle est spécifié
            if ($request->filled('role')) {
                $query->where('role', $request->input('role'));
            }
        } // Sinon, si l'utilisateur est 'admin'
        else if (RoleHelper::Admin() ||( RoleHelper::Superadmin() && $user->societe_id != 1 )) {
            // Filtrer avec l'id de la société et exclure les utilisateurs ayant le role superAdmin
            $query->where('societe_id', $user->societe_id)->where('role', '!=', 1);
        }
        // Récupérer les utilisateurs avec pagination en fonction des filtres appliqués
        $users = $query->orderBy('created_at', 'desc')
            ->paginate($size, ['*'], 'page', $page);

        // Extraire les propriétés de pagination du paginateur
        $pagination = [
            'currentPage' => $users->currentPage(),
            'totalItems' => $users->total(),
            'totalPages' => $users->lastPage(),
        ];

        // Extraire les éléments d'utilisateur du paginateur
        $users = $users->items();

        // Retourner la réponse simplifiée
        return response()->json([
            'users' => $users,
            'pagination' => $pagination,
        ], 200);
    }
    public function store(StoreUserRequest $request)
    {
        /*  if ($request->cin != null) {
        $cin_exist = User::where('cin', $request->cin)->count();
        if ($cin_exist > 0) {
        return response()->json(['error' => 'Le Cin que vous avez saisi' . $request->cin . ' apprtient à un autre utilisateur'], 422);
        }
        } */
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
                    //$request->photo->move(public_path('img/' . $societe->raison_sociale_concatene . '_' . $user->societe_id . '/users'), $photo);
                    FichierHelper::ajouter_fichier($request->photo, $societe->raison_sociale_concatene, $user->societe_id, 'users', $photo);

                }

                $dataArray_projets = json_decode($request->input('selectedProjets', '[]'), true);

                $this->createSubUser($request, $user->id, $user->photo,$dataArray_projets);

                //send accces par email to user

                    $to_email=$user->email;
                    $data=array('password'=>$request->password,'sexe'=>$request->gender,'nom'=>$request->name,'prenom'=>$request->prenom,'email'=>$request->email);
                    Mail::send('User.mail', $data, function($message) use($to_email){
                        $message->to($to_email)
                            ->subject ('Codes Accés au Immo Gestion');
                        $message->from('hhhh.test022@gmail.com','Immo Gestion');

                    });
            }
            return response()->json(['message' => $user], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function show($id)
    {
        $user = null;
        if (RoleHelper::Superadmin()) {
            $user = User::findOrfail($id);
        } else{
            DatabaseHelper::Config();
            $user = User::on('temp')->where('user_id_origin', $id)->first();
        }
       /* if (!$user) {
            return response()->json(['message' => 'Utilisateur non trouvé'], 200);
        }*/
        return response()->json(['user' => $user], 200);
    }
    public function update(UpdateUserRequest $request, $id)
    {

        $user = User::findOrFail($id);
        if ($request->has('cin')) {
            $request->validate([
                'cin' => [
                    Rule::unique('users')->ignore($user->id)->whereNull('deleted_at'),
                ],
            ]);
        }
        if ($request->has('email')) {
            $request->validate([
                'email' => [
                    Rule::unique('users')->ignore($user->id)->whereNull('deleted_at'),
                ],
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
                    $image_path = asset('img/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/users' . $user_origin->photo);
                    //$image_path = public_path('img/users/' . $user->photo);
                    if (file_exists($image_path)) {
                        unlink($image_path);
                    }
                }
                $photo = time() . '.' . $request->name . '_' . $request->prenom . '.' . $request->photo->extension();
                FichierHelper::ajouter_fichier($request->photo, $societe->raison_sociale_concatene, $societe->id, 'users', $photo);
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
        } else if (RoleHelper::AdminSup()) {
            $user = User::findOrFail($id);
            $old_email=$user->email;
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
                    $image_path = public_path('img/users/' . $user->photo);
                    if (file_exists($image_path)) {
                        unlink($image_path);
                    }
                }
                $photo = time() . '.' . $request->name . '_' . $request->prenom . '.' . $request->photo->extension();
                $societe = Societe::findOrfail($user->societe_id);
                //$request->photo->move(public_path('img/' . $societe->raison_sociale_concatene . '_' . $user->societe_id . '/users'), $photo);
                FichierHelper::ajouter_fichier($request->photo, $societe->raison_sociale_concatene, $user->societe_id, 'users', $photo);
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
                //modifier user projet
                $user_projets = UserProjet::on('temp')->where('user_id', $user_societes->id)->delete();
                if (!empty($request->selectedProjets )) {

                    $dataArray_projets = json_decode($request->input('selectedProjets', '[]'), true);
                    foreach ($dataArray_projets as $valeur) {
                        UserProjetHelper::createUserProjet($valeur['id'],$user_societes->id);
                    }
                }

                if($old_email!=$request->email){
                    $to_email=$user->email;
                    $data=array('password'=>'Votre Ancien Password','sexe'=>$request->gender,'nom'=>$request->name,'prenom'=>$request->prenom,'email'=>$request->email);
                    Mail::send('User.mail', $data, function($message) use($to_email){
                        $message->to($to_email)
                            ->subject ('Codes Accés au Immo Gestion');
                        $message->from('hhhh.test022@gmail.com','Immo Gestion');

                    });
                }
            }

            return response()->json(['message' => 'Utilisateur modifié avec succès par super admin'], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
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
                $image_path = public_path('img/users/' . $user_societes->photo);
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

    // Methodes utilitaires (partie invisible a l'exterieur de la classe)
    private function createSubUser($request, $user_id, $user_photo,$dataArray_projets)
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

        if($user->save()){
            foreach ($dataArray_projets as $valeur) {
                UserProjetHelper::createUserProjet($valeur['id'],$user->id);
            }
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


    public function sendEmail()
    {
      //  if (RoleHelper::SuperAdmin()) {

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
            $resetUrl = 'http://localhost:3000/reset-password/' . $token;

            // Send an email to the user with the reset URL
            Mail::to($user)->send(new ResetPasswordMail($resetUrl, $confirmationCode));

            return response()->json(['message' => 'Password reset email sent']);
       // }
    }
    public function resendEmail()
    {
        //if (RoleHelper::SuperAdmin()) {
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
            $resetUrl = env('HOST_NAME_FRONT') . '/reset-password/' . $token;
            // Send an email to the user with the reset URL
            Mail::to($user)->send(new ResetPasswordMail($resetUrl, $confirmationCode));

            return response()->json(['message' => 'Password reset email sent']);
       // }
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
}
