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
        Schema::create('tva_collectes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->onDelete('cascade');
            $table->foreignId('bien_id')->constrained('biens')->onDelete('cascade');
            $table->foreignId('encaissement_id')->constrained('encaissements')->onDelete('cascade');
            $table->double('tva_a_payer', 20, 2)->default(0);
            $table->double('avance_terrain', 20, 2)->default(0);
            $table->double('avance_bien_ttc', 20, 2)->default(0);
            $table->double('avance_bien_ht', 20, 2)->default(0);
            $table->integer('etat')->default(1)->comment('1=>active 4=>ancien');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tva_collectes');
    }
};
