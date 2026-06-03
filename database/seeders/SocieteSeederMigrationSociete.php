<?php
// database/seeders/SocieteSeeder.php

namespace Database\Seeders;

use App\Models\Societe;
use Illuminate\Database\Seeder;

class SocieteSeederMigrationSociete extends Seeder
{
    public function run(): void
    {
        // Récupérer les données depuis .env ou utiliser des valeurs par défaut
        $societeData = [
            'raison_sociale' => env('SOCIETE_RAISON_SOCIALE', 'societe'),
            'raison_sociale_concatene' => env('SOCIETE_RAISON_SOCIALE_CONCAT', 'societe'),
            'adresse' => env('SOCIETE_ADRESSE', 'Adresse par défaut'),
            'capital' => env('SOCIETE_CAPITAL', 1000000.00),
            'id_fiscal' => env('SOCIETE_ID_FISCAL', 12345678.00),
            'registre_commerce' => env('SOCIETE_REGISTRE_COMMERCE', 123456.00),
            'nom_contact' => env('SOCIETE_NOM_CONTACT', 'Admin'),
            'prenom_contact' => env('SOCIETE_PRENOM_CONTACT', 'Super'),
            'tel' => env('SOCIETE_TEL', '+212600000000'),
            'email' => env('SOCIETE_EMAIL', 'contact@studio4d.com'),
            'logo' => env('SOCIETE_LOGO', null),
        ];

        // Vérifier si la société existe déjà
        $existingSociete = Societe::where('raison_sociale_concatene', $societeData['raison_sociale_concatene'])->first();

        if (!$existingSociete) {
            Societe::create($societeData);
            $this->command->info('✅ Société créée avec succès !');
        } else {
            $this->command->info('⚠️ La société existe déjà !');
        }
    }
}
