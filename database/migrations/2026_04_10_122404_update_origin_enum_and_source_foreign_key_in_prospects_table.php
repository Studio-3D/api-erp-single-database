<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the existing foreign key
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropForeign(['source']);
        });

        // For MySQL - modify origin to enum using raw SQL
        DB::statement("ALTER TABLE prospects MODIFY COLUMN origin ENUM('manuel', 'visite', 'whatsapp', 'facebook', 'landingPage', 'import', 'appel')");

        // Modify source column and add new foreign key
        Schema::table('prospects', function (Blueprint $table) {
            $table->foreignId('source')->nullable()->change();
            $table->foreign('source')->references('id')->on('sources')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropForeign(['source']);
        });

        // Revert origin back to string
        DB::statement("ALTER TABLE prospects MODIFY COLUMN origin VARCHAR(255)");

        Schema::table('prospects', function (Blueprint $table) {
            $table->foreignId('source')->nullable()->change();
        });
    }
};
