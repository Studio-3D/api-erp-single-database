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
       
        \App\Models\User::factory()->create([

            'name' => 'super_admin',
            'prenom' => 'super_admin',
            'type' => '1',
            'nb_appel_recu' => '11',
            'nb_appel_traite' => '11',
            'cin' => 'BH111',
            'date_embauche' => now(),
            'niveau_etude' => 'bac',
            'is_actif' => '1',
            'solde_conge' => '1000',
            'email' => 'superadmin@email.com',
            'password' => Hash::make('superadmin'), // password

        ]);
    }
}
