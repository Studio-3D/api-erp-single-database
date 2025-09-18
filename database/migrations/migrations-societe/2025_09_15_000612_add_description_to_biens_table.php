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
        Schema::table('biens', function (Blueprint $table) {
            // Check if description column doesn't exist before adding it
            if (!Schema::hasColumn('biens', 'description')) {
                $table->text('description')->nullable()->after('etat');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('biens', function (Blueprint $table) {
            if (Schema::hasColumn('biens', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
