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
        if (!Schema::hasTable('facebook_configurations')) {
            Schema::create('facebook_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('page_fcb_id');
            $table->longText('acces_token_page');
            $table->unsignedBigInteger('projet_id');
            $table->string('webhook_verify_token')->nullable();
            $table->boolean('webhook_enabled')->default(false);
            $table->json('webhook_subscriptions')->nullable();
            $table->softDeletes();
            $table->timestamps();
            
            $table->foreign('projet_id')->references('id')->on('projets')->onDelete('cascade');
            $table->index(['projet_id', 'deleted_at']);
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facebook_configurations');
    }
};
