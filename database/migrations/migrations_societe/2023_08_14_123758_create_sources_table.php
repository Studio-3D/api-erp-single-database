<?php

use Database\Seeders\SourceSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->timestamps();
            $table->softDeletes();
        });

        // Add the trigger to enforce uniqueness including soft-deleted records
        DB::unprepared('
            CREATE TRIGGER enforce_unique_source_before_insert_or_update
            BEFORE INSERT ON sources
            FOR EACH ROW
            BEGIN
                IF NEW.deleted_at IS NULL THEN
                    IF EXISTS (
                        SELECT 1 FROM sources
                        WHERE source = NEW.source
                        AND deleted_at IS NULL
                        AND id != NEW.id
                    ) THEN
                        SIGNAL SQLSTATE "45000"
                        SET MESSAGE_TEXT = "Duplicate source found where deleted_at IS NULL";
                    END IF;
                END IF;
            END;
        ');

        DB::unprepared('
            CREATE TRIGGER enforce_unique_source_before_update
            BEFORE UPDATE ON sources
            FOR EACH ROW
            BEGIN
                IF NEW.deleted_at IS NULL AND NEW.source != OLD.source THEN
                    IF EXISTS (
                        SELECT 1 FROM sources
                        WHERE source = NEW.source
                        AND deleted_at IS NULL
                        AND id != NEW.id
                    ) THEN
                        SIGNAL SQLSTATE "45000"
                        SET MESSAGE_TEXT = "Duplicate source found where deleted_at IS NULL";
                    END IF;
                END IF;
            END;
        ');

        Artisan::call('db:seed', [
            '--class' => SourceSeeder::class,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the triggers first
        DB::unprepared('DROP TRIGGER IF EXISTS enforce_unique_source_before_insert_or_update');
        DB::unprepared('DROP TRIGGER IF EXISTS enforce_unique_source_before_update');

        // Then drop the table
        Schema::dropIfExists('sources');
    }
};
