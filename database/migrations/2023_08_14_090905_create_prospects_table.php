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
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->string('cin')->unique()->nullable();
            $table->integer('client_id')->nullable();
            $table->string('nom');
            $table->string('prenom')->nullable();
            $table->string('telephone');
            $table->string('telephone_num2')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('origin');
            $table->integer('source');
            $table->text('message')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['cin','telephone','telephone_num2']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospect');
    }
};
