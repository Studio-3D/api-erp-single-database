<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Gestion_Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Enum\RoleEnum;
use App\Http\Helpers\DatabaseHelper;

class GestionRolesController extends Controller
{


    public function roles_actives($societe_id)
    {
        // Configurer la connexion pour cette société
        DatabaseHelper::Config($societe_id);

        try {
            // Utiliser la connexion temp (qui pointe vers la base de la société)
            $roles = Gestion_Roles::on('temp')->where('actif', 1)->get();

            return response()->json([
                'success' => true,
                'roles' => $roles,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des rôles',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // Récupérer tous les rôles
    public function index()
    {
        DatabaseHelper::Config();

        try {
            // Utiliser la connexion temp
            $roles = Gestion_Roles::on('temp')->get();
            return response()->json([
                'success' => true,
                'roles' => $roles,
                'available_roles' => $this->getAvailableRoles()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des rôles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Créer un nouveau rôle
    public function store(Request $request)
    {
        DatabaseHelper::Config();

        $validator = Validator::make($request->all(), [
            'role' => 'required|in:' . implode(',', $this->getRoleValues()),
            'actif' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Vérifier si le rôle existe déjà (sur temp)
            $existingRole = Gestion_Roles::on('temp')
                ->where('role', $request->role)
                ->first();

            if ($existingRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce rôle existe déjà'
                ], 400);
            }

            $role = Gestion_Roles::on('temp')->create([
                'role' => $request->role,
                'actif' => $request->boolean('actif', true)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rôle créé avec succès',
                'role' => $role
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du rôle',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Afficher un rôle spécifique


    // Mettre à jour un rôle
    public function update(Request $request, $id)
    {
        DatabaseHelper::Config();

        $validator = Validator::make($request->all(), [
            'actif' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $role = Gestion_Roles::on('temp')->findOrFail($id);

            // Le rôle lui-même n'est pas modifiable, seulement l'état actif
            $role->update([
                'actif' => $request->boolean('actif')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rôle mis à jour avec succès',
                'role' => $role
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du rôle'
            ], 500);
        }
    }

    // Supprimer un rôle (soft delete)
    public function destroy($id)
    {
        DatabaseHelper::Config();

        try {
            $role = Gestion_Roles::on('temp')->findOrFail($id);
            $role->delete();

            return response()->json([
                'success' => true,
                'message' => 'Rôle supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du rôle'
            ], 500);
        }
    }







    // Méthodes helper privées
    private function getRoleValues()
    {
        return [
            RoleEnum::COMMERCIAL->value,
            RoleEnum::NOTAIRE->value,
            RoleEnum::RESPO_LIVRAISON->value,
            RoleEnum::COMPTABLE->value,
            RoleEnum::SAV->value,
        ];
    }

    private function getAvailableRoles()
    {
        DatabaseHelper::Config();

        $roles = [];
        $existingRoles = Gestion_Roles::on('temp')->pluck('role')->toArray();

        foreach ($this->getRoleValues() as $roleValue) {
            $roleEnum = RoleEnum::tryFrom($roleValue);
            if ($roleEnum) {
                $roles[] = [
                    'value' => $roleValue,
                    'label' => $roleEnum->name,
                    'description' => $this->getRoleDescription($roleValue),
                    'exists' => in_array($roleValue, $existingRoles)
                ];
            }
        }

        return $roles;
    }

    private function getRoleDescription($roleValue)
    {
        $descriptions = [
            RoleEnum::COMMERCIAL->value => 'Service Commercial (Visite & Ventes)',
            RoleEnum::NOTAIRE->value => 'Service Notarial',
            RoleEnum::RESPO_LIVRAISON->value => 'Service Livraison',
            RoleEnum::COMPTABLE->value => 'Service Comptable',
            RoleEnum::SAV->value => 'Service Après-Vente (SAV)',
        ];

        return $descriptions[$roleValue] ?? 'Description non définie';
    }


}
