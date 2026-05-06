<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class SyncSuperAdminToSocieteDatabasesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get superadmin from main database (erp_studio4d_1)
        $superAdmin = DB::connection('mysql')->table('users')
            ->where('role', 1)
            ->where('email', 'superadmin@gmail.com')
            ->first();

        if (!$superAdmin) {
            $message = "❌ Superadmin not found in main database!";
            Log::error($message);
            if ($this->command) {
                $this->command->error($message);
            }
            return;
        }

        $message = "✅ Superadmin found: {$superAdmin->name} {$superAdmin->prenom} (ID: {$superAdmin->id})";
        Log::info($message);
        if ($this->command) {
            $this->command->info($message);
        }

        // Check if users table exists in current (temp) database
        if (!Schema::connection('temp')->hasTable('users')) {
            $message = "⚠ Users table not found in current database!";
            Log::warning($message);
            if ($this->command) {
                $this->command->warn($message);
            }
            return;
        }

        // Check if superadmin already exists in this database
        $existingUser = DB::connection('temp')->table('users')
            ->where('user_id_origin', $superAdmin->id)
            ->orWhere('id', $superAdmin->id)
            ->first();

        if (!$existingUser) {
            // Insert superadmin user into current tenant database
            DB::connection('temp')->table('users')->insert([
                'id' => $superAdmin->id,
                'user_id_origin' => $superAdmin->id,
                'name' => $superAdmin->name,
                'prenom' => $superAdmin->prenom,
                'email' => $superAdmin->email,
                'role' => $superAdmin->role,
                'gender' => $superAdmin->gender ?? null,
                'phone' => $superAdmin->phone ?? null,
                'photo' => $superAdmin->photo ?? null,
                'password' => $superAdmin->password,
                'nb_appel_recu' => $superAdmin->nb_appel_recu ?? 0,
                'nb_appel_traite' => $superAdmin->nb_appel_traite ?? 0,
                'remember_token' => $superAdmin->remember_token ?? null,
                'cin' => $superAdmin->cin ?? null,
                'date_embauche' => $superAdmin->date_embauche ?? null,
                'niveau_etude' => $superAdmin->niveau_etude ?? null,
                'adresse' => $superAdmin->adresse ?? null,
                'cnss' => $superAdmin->cnss ?? null,
                'is_actif' => 1,
                'is_connected' => 0,
                'fonction' => $superAdmin->fonction ?? null,
                'solde_conge' => $superAdmin->solde_conge ?? 0,
                'nb_prospects' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $message = "✅ Superadmin inserted with ID: {$superAdmin->id}";
            Log::info($message);
            if ($this->command) {
                $this->command->info($message);
            }
        } else {
            $message = "⚠ Superadmin already exists with ID: {$superAdmin->id}";
            Log::info($message);
            if ($this->command) {
                $this->command->info($message);
            }
        }
    }
}
