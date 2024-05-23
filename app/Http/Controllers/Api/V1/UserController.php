<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\FichierHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Societe;
use App\Models\V1\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UserController extends Controller
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
        if (RoleHelper::Superadmin()) {
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
        else if (RoleHelper::Admin()) {
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
                $this->createSubUser($request, $user->id, $user->photo);
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
        } elseif (RoleHelper::Admin()) {
            DatabaseHelper::Config();
            $user = User::on('temp')->findOrfail($id);
        }
        if (!$user) {
            return response()->json(['message' => 'Utilisateur non trouvé'], 200);
        }
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
                    $image_path = public_path('img/users/' . $user->photo);
                    if (file_exists($image_path)) {
                        unlink($image_path);
                    }
                }
                $photo = time() . '.' . $request->name . '_' . $request->prenom . '.' . $request->photo->extension();
                $societe = Societe::findOrfail($user_societes->societe_id);
                //$request->photo->move(public_path('img/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/users'), $photo);
                FichierHelper::ajouter_fichier($request->photo, $societe->raison_sociale_concatene, $societe->id, 'users', $photo);
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
    private function createSubUser($request, $user_id, $user_photo)
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
}
