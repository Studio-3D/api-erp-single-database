<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enum\TypeEncaissement;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('encaissements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->onDelete('cascade');
            $table->enum('type_encaissement',[TypeEncaissement::AVANCE->value,TypeEncaissement::RESTITUTION->value,TypeEncaissement::REMBOURSEMENT->value,TypeEncaissement::DECHARGE->value,TypeEncaissement::DEBLOCAGE->value])->comment('	1==>Avances 2==>restitution 3=>remboursement 4==>decharge 5=>deblocage credit cih');
            $table->double('montant');
            $table->date('date_reglement');
            $table->date('date_encaissement');
            $table->foreignId('user_id_valider')->constrained('users')->onDelete('cascade');
            $table->foreignId('id_avance')->nullable()->constrained('avances')->onDelete('cascade');
            $table->integer('id_deblocage')->nullable()->comment('Deblocage Credit');
            $table->integer('id_restitution')->nullable()->comment('Table Restitution');
            $table->integer('id_remboursement')->nullable()->comment('Table Remboursement');
            $table->integer('id_decharge')->nullable()->comment('Table Decharge');
            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('encaissements');
    }
};
