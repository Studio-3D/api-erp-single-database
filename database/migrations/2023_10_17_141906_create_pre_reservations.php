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
        Schema::create('pre_reservations', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('code_pre_reserve');
            $table->timestamp('date_pre_reserve')->useCurrent();
            $table->foreignId('bien_id')->constrained('biens')->onDelete('cascade');
            $table->foreignId('visite_id')->nullable()->constrained('visites')->onDelete('cascade');
            $table->bigInteger('appel_id')->nullable();
           // $table->foreignId('appel_id')->nullable()->constrained('visites')->onDelete('cascade');
           $table->string('commentaire')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pre_reservations');
    }
};
