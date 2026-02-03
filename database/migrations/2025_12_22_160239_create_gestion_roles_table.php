<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Enum\RoleEnum;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gestion_roles', function (Blueprint $table) {
            $table->id();
            $table->enum('role',[
                RoleEnum::COMMERCIAL->value,
                RoleEnum::NOTAIRE->value,
                RoleEnum::RESPO_LIVRAISON->value,
                RoleEnum::COMPTABLE->value,
                RoleEnum::SAV->value
            ])->comment('3=>Commercial 5=>Notaire 6=>RESPO LIVRAISON 7=>Comptable 8=>SAV');
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Insérer le rôle Commercial par défaut après création de la table
        DB::table('gestion_roles')->insert([
            'role' => RoleEnum::COMMERCIAL->value,
            'actif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('gestion_roles');
    }
};
