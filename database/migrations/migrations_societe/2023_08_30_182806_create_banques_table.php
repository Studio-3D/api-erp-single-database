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
        Schema::create('banques', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->timestamps();
            $table->softDeletes();
        });

        // Remove the standard unique constraint since we'll handle it with triggers
        // Schema::table('banques', function (Blueprint $table) {
        //     $table->dropUnique(['nom']);
        // });

        // Add triggers to enforce uniqueness including soft-deleted records
        DB::unprepared('
            CREATE TRIGGER enforce_unique_banque_nom_before_insert
            BEFORE INSERT ON banques
            FOR EACH ROW
            BEGIN
                IF NEW.deleted_at IS NULL THEN
                    IF EXISTS (
                        SELECT 1 FROM banques
                        WHERE nom = NEW.nom
                        AND deleted_at IS NULL
                    ) THEN
                        SIGNAL SQLSTATE "45000"
                        SET MESSAGE_TEXT = "Duplicate banque name found where deleted_at IS NULL";
                    END IF;
                END IF;
            END;
        ');

        DB::unprepared('
            CREATE TRIGGER enforce_unique_banque_nom_before_update
            BEFORE UPDATE ON banques
            FOR EACH ROW
            BEGIN
                IF NEW.deleted_at IS NULL AND NEW.nom != OLD.nom THEN
                    IF EXISTS (
                        SELECT 1 FROM banques
                        WHERE nom = NEW.nom
                        AND deleted_at IS NULL
                        AND id != NEW.id
                    ) THEN
                        SIGNAL SQLSTATE "45000"
                        SET MESSAGE_TEXT = "Duplicate banque name found where deleted_at IS NULL";
                    END IF;
                END IF;
            END;
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the triggers first
        DB::unprepared('DROP TRIGGER IF EXISTS enforce_unique_banque_nom_before_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS enforce_unique_banque_nom_before_update');

        // Then drop the table
        Schema::dropIfExists('banques');
    }
};
