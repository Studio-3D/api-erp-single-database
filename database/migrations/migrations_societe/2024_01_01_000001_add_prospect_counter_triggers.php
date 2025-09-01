<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop existing triggers first to avoid conflicts
        DB::unprepared('DROP TRIGGER IF EXISTS prospect_commercial_insert_trigger');
        DB::unprepared('DROP TRIGGER IF EXISTS prospect_commercial_update_trigger');
        DB::unprepared('DROP TRIGGER IF EXISTS prospect_commercial_delete_trigger');

        // Trigger for when a prospect is assigned to a commercial (INSERT)
        DB::unprepared('
            CREATE TRIGGER prospect_commercial_insert_trigger
            AFTER INSERT ON prospects
            FOR EACH ROW
            BEGIN
                IF NEW.commercial_affecte IS NOT NULL THEN
                    UPDATE users
                    SET nb_prospects = nb_prospects + 1
                    WHERE id = NEW.commercial_affecte;
                END IF;
            END;
        ');

        // Trigger for when a prospect's commercial assignment changes (UPDATE)
        DB::unprepared('
            CREATE TRIGGER prospect_commercial_update_trigger
            AFTER UPDATE ON prospects
            FOR EACH ROW
            BEGIN
                -- If commercial assignment changed
                IF OLD.commercial_affecte != NEW.commercial_affecte THEN
                    -- Decrement old commercials counter
                    IF OLD.commercial_affecte IS NOT NULL THEN
                        UPDATE users 
                        SET nb_prospects = nb_prospects - 1 
                        WHERE id = OLD.commercial_affecte;
                    END IF;
                    
                    -- Increment new commercials counter
                    IF NEW.commercial_affecte IS NOT NULL THEN
                        UPDATE users 
                        SET nb_prospects = nb_prospects + 1 
                        WHERE id = NEW.commercial_affecte;
                    END IF;
                END IF;
            END;
        ');

        // Trigger for when a prospect is deleted
        DB::unprepared('
            CREATE TRIGGER prospect_commercial_delete_trigger
            AFTER DELETE ON prospects
            FOR EACH ROW
            BEGIN
                IF OLD.commercial_affecte IS NOT NULL THEN
                    UPDATE users 
                    SET nb_prospects = nb_prospects - 1 
                    WHERE id = OLD.commercial_affecte;
                END IF;
            END;
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS prospect_commercial_insert_trigger');
        DB::unprepared('DROP TRIGGER IF EXISTS prospect_commercial_update_trigger');
        DB::unprepared('DROP TRIGGER IF EXISTS prospect_commercial_delete_trigger');
    }
};
