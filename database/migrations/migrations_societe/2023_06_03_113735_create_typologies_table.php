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
        Schema::create('typologies', function (Blueprint $table) {
            $table->id();
            $table->string('typologie');
            $table->foreignId('projet_id')->constrained('projets')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });

        // Add triggers to enforce unique typologie per projet (excluding soft-deleted)
        DB::unprepared('
            CREATE TRIGGER enforce_unique_typologie_per_projet_before_insert
            BEFORE INSERT ON typologies
            FOR EACH ROW
            BEGIN
                IF NEW.deleted_at IS NULL THEN
                    IF EXISTS (
                        SELECT 1 FROM typologies
                        WHERE typologie = NEW.typologie
                        AND projet_id = NEW.projet_id
                        AND deleted_at IS NULL
                    ) THEN
                        SIGNAL SQLSTATE "45000"
                        SET MESSAGE_TEXT = "Duplicate typologie found for this projet where deleted_at IS NULL";
                    END IF;
                END IF;
            END;
        ');

        DB::unprepared('
            CREATE TRIGGER enforce_unique_typologie_per_projet_before_update
            BEFORE UPDATE ON typologies
            FOR EACH ROW
            BEGIN
                IF NEW.deleted_at IS NULL AND
                   (NEW.typologie != OLD.typologie OR NEW.projet_id != OLD.projet_id) THEN
                    IF EXISTS (
                        SELECT 1 FROM typologies
                        WHERE typologie = NEW.typologie
                        AND projet_id = NEW.projet_id
                        AND deleted_at IS NULL
                        AND id != NEW.id
                    ) THEN
                        SIGNAL SQLSTATE "45000"
                        SET MESSAGE_TEXT = "Duplicate typologie found for this projet where deleted_at IS NULL";
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
        DB::unprepared('DROP TRIGGER IF EXISTS enforce_unique_typologie_per_projet_before_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS enforce_unique_typologie_per_projet_before_update');

        // Then drop the table
        Schema::dropIfExists('typologies');
    }
};
