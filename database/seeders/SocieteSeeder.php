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
        \App\Models\Societe::create([
            'raison_sociale' => 'studio4d',
            'raison_sociale_concatene' => 'studio4d',
            'nom_contact' => 'Mohamed',
            'prenom_contact' => 'Abid',
            'email' => 'a.airout@gmail.com',
        ]);
    }
}
