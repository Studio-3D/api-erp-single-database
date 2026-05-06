<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->timestamps();
            $table->softDeletes();
        });

        // Trigger BEFORE INSERT
        DB::unprepared('
            CREATE TRIGGER enforce_unique_source_before_insert
            BEFORE INSERT ON sources
            FOR EACH ROW
            BEGIN
                IF NEW.deleted_at IS NULL THEN
                    IF EXISTS (
                        SELECT 1 FROM sources
                        WHERE source = NEW.source
                        AND deleted_at IS NULL
                    ) THEN
                        SIGNAL SQLSTATE "45000"
                        SET MESSAGE_TEXT = "Duplicate source found where deleted_at IS NULL";
                    END IF;
                END IF;
            END;
        ');

        // Trigger BEFORE UPDATE
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
                        AND id != OLD.id
                    ) THEN
                        SIGNAL SQLSTATE "45000"
                        SET MESSAGE_TEXT = "Duplicate source found where deleted_at IS NULL";
                    END IF;
                END IF;
            END;
        ');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS enforce_unique_source_before_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS enforce_unique_source_before_update');
        Schema::dropIfExists('sources');
    }
};
