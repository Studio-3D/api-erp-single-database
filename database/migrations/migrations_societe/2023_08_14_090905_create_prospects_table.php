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
            $table->string('nom')->nullable();
            $table->string('prenom')->nullable();
            $table->string('telephone');
            $table->string('telephone_num2')->nullable();
            $table->string('email')->nullable()->unique();
            $table->foreignId('source')->nullable();
            $table->foreignId('partenaire_id')->nullable()->constrained('partenaires')->onDelete('cascade');
            $table->boolean('notifie')->default(false)->nullable();
            $table->text('message')->nullable();
            $table->string('origin');
            $table->string('ville')->nullable();
            $table->foreignId(column: 'projet_id')->constrained('projets')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
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
