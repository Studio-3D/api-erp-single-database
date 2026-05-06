<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enum\RoleEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, modify the column to be nullable
        Schema::table('notifications', function (Blueprint $table) {
            // For MySQL, we need to change the column definition
            $table->enum('role', [
                RoleEnum::SUPERADMIN->value,
                RoleEnum::ADMIN->value,
                RoleEnum::COMMERCIAL->value,
                RoleEnum::ADMIN_COMMERCIAL->value,
                RoleEnum::NOTAIRE->value,
                RoleEnum::RESPO_LIVRAISON->value,
                RoleEnum::COMPTABLE->value,
                RoleEnum::SAV->value,
                RoleEnum::RESPO_COMMERCIAL->value,
                RoleEnum::AGENT_ADMINISTRATIF->value
            ])->nullable()->comment('1=>superadmin 2=>Admin 3=>Commercial 4=>admin_commercial 5=>Notaire 6=>RESPO LIVRAISON 7=>Comptable 8=>SAV 9==>RESPO_COMMERCIAL 10==>AGENT_ADMINISTRATIF')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to NOT NULL
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('role', [
                RoleEnum::SUPERADMIN->value,
                RoleEnum::ADMIN->value,
                RoleEnum::COMMERCIAL->value,
                RoleEnum::ADMIN_COMMERCIAL->value,
                RoleEnum::NOTAIRE->value,
                RoleEnum::RESPO_LIVRAISON->value,
                RoleEnum::COMPTABLE->value,
                RoleEnum::SAV->value,
                RoleEnum::RESPO_COMMERCIAL->value,
                RoleEnum::AGENT_ADMINISTRATIF->value
            ])->comment('1=>superadmin 2=>Admin 3=>Commercial 4=>admin_commercial 5=>Notaire 6=>RESPO LIVRAISON 7=>Comptable 8=>SAV 9==>RESPO_COMMERCIAL 10==>AGENT_ADMINISTRATIF')->change();
        });
    }
};
