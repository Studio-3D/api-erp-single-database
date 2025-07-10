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
        Schema::create('configuration_social_networks', function (Blueprint $table) {
            $table->id();
            $table->string('page_fcb_id')->nullable(); // Make nullable
            $table->longText('acces_token_page')->nullable(); // Make nullable
            $table->string('instagram_id')->nullable(); // Make nullable
            $table->longText('acces_token_user')->nullable(); // Make nullable
            
            // Simplified webhook configuration fields
            $table->string('webhook_verify_token')->nullable();
            $table->boolean('webhook_enabled')->default(false);
            $table->json('webhook_subscriptions')->nullable(); // Store subscribed events
            
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuration_social_networks');
    }
};
