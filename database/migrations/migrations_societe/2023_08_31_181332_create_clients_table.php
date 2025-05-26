<?php

use App\Enum\Civilite;
use App\Enum\SituationFamilliale;
use App\Enum\TypeClient;
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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->enum('type_client',[TypeClient::Particulier->value,TypeClient::Société->value])->comment('1=>particulier 2=>societe');;
            $table->string('code_client')->nullable();
            $table->string('nom');
            $table->string('prenom');
            $table->string('telephone_num1');
            $table->string('telephone_num2')->nullable();
            $table->boolean('notifie')->default(false);
            $table->string('email')->nullable();
            $table->enum('civilite',[Civilite::Mr->value,Civilite::Mme->value,Civilite::Mlle->value]);
            $table->string('adresse')->nullable();
            $table->string('ville')->nullable();
            $table->string('pays')->nullable();
            $table->string('profession')->nullable();
            $table->string('cin');
            $table->integer('age')->nullable();
            $table->string('lieu_naissance')->nullable();
            $table->string('nationalite')->nullable();
            $table->date('date_naissance')->nullable();
            $table->string('nom_mari')->nullable();
            $table->string('lieu_mariage')->nullable();
            $table->date('date_mariage')->nullable();
            $table->string('nom_responsable')->nullable();
            $table->string('relation_familliale')->nullable();
            $table->enum('situation_familliale',[SituationFamilliale::Célibataire->value,SituationFamilliale::Marié->value,SituationFamilliale::Divorcé->value,SituationFamilliale::Veuf->value])->comment('1=>celebataire 2=>Marie 3=>Divorcé 4=>veuf');
            $table->string('nom_pere')->nullable();
            $table->string('nom_mere')->nullable();
            $table->string('password')->nullable();
            $table->foreignId('partenaire_id')->nullable()->constrained('partenaires')->onDelete('cascade');
            $table->foreignId('prospect_id')->nullable()->constrained('prospects')->onDelete('cascade');
            $table->foreignId(column: 'projet_id')->constrained('projets')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });

        // Add the trigger using DB::unprepared()
        DB::unprepared('
            CREATE TRIGGER clients_unique_fields_before_insert_or_update
            BEFORE INSERT ON clients
            FOR EACH ROW
            BEGIN
                IF NEW.deleted_at IS NULL THEN
                    IF EXISTS (
                        SELECT 1
                        FROM clients
                        WHERE cin = NEW.cin AND deleted_at IS NULL
                    ) THEN
                        SIGNAL SQLSTATE "45000"
                        SET MESSAGE_TEXT = "Duplicate CIN found where deleted_at is NULL";
                    END IF;

                    IF EXISTS (
                        SELECT 1
                        FROM clients
                        WHERE email = NEW.email AND email IS NOT NULL AND deleted_at IS NULL
                    ) THEN
                        SIGNAL SQLSTATE "45000"
                        SET MESSAGE_TEXT = "Duplicate Email found where deleted_at is NULL";
                    END IF;

                    IF EXISTS (
                        SELECT 1
                        FROM clients
                        WHERE telephone_num1 = NEW.telephone_num1 AND deleted_at IS NULL
                    ) THEN
                        SIGNAL SQLSTATE "45000"
                        SET MESSAGE_TEXT = "Duplicate Telephone Number 1 found where deleted_at is NULL";
                    END IF;

                    IF NEW.telephone_num2 IS NOT NULL THEN
                        IF EXISTS (
                            SELECT 1
                            FROM clients
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
        DB::unprepared('DROP TRIGGER IF EXISTS clients_unique_fields_before_insert_or_update');

        // Drop the table
        Schema::dropIfExists('clients');
    }
};
