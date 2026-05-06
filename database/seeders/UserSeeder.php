<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;


class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       
        \App\Models\User::create([

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
            'email' => 'superadmin@gmail.com',
            'password' => Hash::make('superadmin'), // password

        ]);
    }
}
