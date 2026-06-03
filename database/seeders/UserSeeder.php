<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Créer l'utilisateur
        $user = User::create([
            'societe_id' => 1,
            'name' => 'super_admin',
            'prenom' => 'super_admin',
            'role' => '1',
            'nb_appel_recu' => '0',
            'nb_appel_traite' => '0',
            'cin' => 'BH1111',
            'date_embauche' => now(),
            'niveau_etude' => 'bac',
            'is_actif' => '1',
            'solde_conge' => '0',
            'email' => 'co@gmail.com',
            'password' => Hash::make('superadmin'),
        ]);

        // Mettre à jour user_id_origin avec l'ID de l'utilisateur
        $user->user_id_origin = $user->id;
        $user->save();

        $this->command->info('✅ SuperAdmin créé avec succès !');
        $this->command->info('ID: ' . $user->id);
        $this->command->info('user_id_origin: ' . $user->user_id_origin);
        $this->command->info('Email: superadmin@gmail.com');
        $this->command->info('Password: superadmin');
    }
}
