<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create the table
        Schema::create('prospects', function ($table) {
            $table->id();
            $table->string('cin')->nullable();
            $table->integer('client_id')->nullable();
            $table->string('nom')->nullable();
            $table->string('prenom')->nullable();
            $table->string('telephone')->nullable();
            $table->string('telephone_num2')->nullable();
            $table->string('email')->nullable();
            $table->string('origin');
            $table->text('message')->nullable();
            $table->foreignId('source')->nullable();
            $table->foreignId('partenaire_id')->nullable()->constrained('partenaires')->onDelete('cascade');
            $table->boolean('notifie')->default(false)->nullable();
            $table->string('ville')->nullable();
            $table->unsignedBigInteger('commercial_affecte')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Add the trigger using DB::unprepared()
        DB::unprepared('
            CREATE TRIGGER unique_fields_before_insert_or_update
            BEFORE INSERT ON prospects
            FOR EACH ROW
            BEGIN
                IF NEW.deleted_at IS NULL THEN
                    IF EXISTS (
                        SELECT 1
                        FROM prospects
                        WHERE cin = NEW.cin AND deleted_at IS NULL
                    ) THEN
                        SIGNAL SQLSTATE "45000"
                        SET MESSAGE_TEXT = "Duplicate CIN found where deleted_at is NULL";
                    END IF;

                    IF EXISTS (
                        SELECT 1
                        FROM prospects
                        WHERE email = NEW.email AND deleted_at IS NULL
                    ) THEN
                        SIGNAL SQLSTATE "45000"
                        SET MESSAGE_TEXT = "Duplicate Email found where deleted_at is NULL";
                    END IF;

                    IF EXISTS (
                        SELECT 1
                        FROM prospects
                        WHERE telephone = NEW.telephone AND deleted_at IS NULL
                    ) THEN
                        SIGNAL SQLSTATE "45000"
                        SET MESSAGE_TEXT = "Duplicate Telephone found where deleted_at is NULL";
                    END IF;

                    IF NEW.telephone_num2 IS NOT NULL THEN
                        IF EXISTS (
                            SELECT 1
                            FROM prospects
                            WHERE telephone_num2 = NEW.telephone_num2 AND deleted_at IS NULL
                        ) THEN
                            SIGNAL SQLSTATE "45000"
                            SET MESSAGE_TEXT = "Duplicate Telephone Number 2 found where deleted_at is NULL";
                        END IF;
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
        // Drop the trigger
        DB::unprepared('DROP TRIGGER IF EXISTS unique_fields_before_insert_or_update');

        // Drop the table
        Schema::dropIfExists('prospects');
    }
};
