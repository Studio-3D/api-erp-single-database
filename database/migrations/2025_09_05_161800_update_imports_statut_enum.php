<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            // Update the enum to include the new 'en_attente' status
            $table->enum('statut', [0, 1, 2, 3])
                  ->comment('0=>en_attente 1=>en_cours 2=>importe 3=>echoue')
                  ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            // Revert back to the original 3-status enum
            $table->enum('statut', [0, 1, 2])
                  ->comment('0=>en cours 1=>success 2=>echoué')
                  ->change();
        });
    }
};
