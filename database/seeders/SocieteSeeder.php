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

            'raison_sociale' => 'societ',
            'nom_contact' => 'ahmed',
            'prenom_contact' => 'slimani',
            'email' => 'ahmed@email.com',

        ]);
    }
}
