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
        Schema::create('commission_cumule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('projet_id')->constrained('projets')->onDelete('cascade');
            $table->foreignId('commission_id')->constrained('commission_mensuelle')->onDelete('cascade');
            $table->double('montant');
            $table->enum('etat', [0,1])->comment('0=>non payé 1=>payé');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
