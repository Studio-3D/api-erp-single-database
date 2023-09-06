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
        Schema::create('frein_typologies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('frein_id')->constrained('freins')->onDelete('cascade');
            $table->foreignId('typologie_id')->constrained('typologies')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('frein_typologies');
    }
};
