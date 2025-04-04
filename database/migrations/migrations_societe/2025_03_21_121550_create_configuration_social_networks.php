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
            $table->string('page_fcb_id'); // facebook, instagram, whatsapp
            $table->longText('acces_token_page'); // comment, reaction, message
            $table->string('instagram_id'); // facebook, instagram, whatsapp
            $table->longText('acces_token_user'); // comment, reaction, message
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
