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
        if (!Schema::hasTable('webhook_events')) {
            Schema::create('webhook_events', function (Blueprint $table) {
                $table->id();
                $table->string('platform');
                $table->string('type');
                $table->json('data');
                $table->string('page_id')->nullable();
                $table->boolean('processed')->default(false);
                $table->text('processing_notes')->nullable();
                $table->softDeletes();
                $table->timestamps();
                
                $table->index(['platform', 'type']);
                $table->index(['processed']);
                $table->index(['page_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
