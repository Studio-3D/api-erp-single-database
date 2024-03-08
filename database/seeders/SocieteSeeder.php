<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SocieteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Societe::factory()->create([



            /* 'raison_sociale' => 'alpha',
            'nom_contact' => 'Mohamed',
            'prenom_contact' => 'Abid',
            'email' => 'a.airout@gmail.com', */

            'raison_sociale' => 'studio4d',
            'nom_contact' => 'Mohamed',
            'prenom_contact' => 'Abid',
            'email' => 'a.airout@gmail.com',



        ]);
    }
}
