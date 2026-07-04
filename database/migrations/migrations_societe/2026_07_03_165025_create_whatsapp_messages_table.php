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
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->unsignedBigInteger('projet_id');
            $table->unsignedBigInteger('prospect_id')->nullable();
            $table->unsignedBigInteger('bulk_id')->nullable()->comment('Reference to historique_relances_whatsapp id');

            // Message details
            $table->string('from_number');
            $table->string('to_number');
            $table->text('message');
            $table->string('message_sid')->unique();
            $table->string('profile_name')->nullable();

            // Media attachments
            $table->string('media_url')->nullable();
            $table->string('media_type')->nullable()->comment('image, audio, application/pdf, document');
            $table->string('media_sid')->nullable();

            // Status tracking
            $table->timestamp('read_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default('received')->comment('received, sent, delivered, read, failed');

            // Error handling
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('projet_id');
            $table->index('prospect_id');
            $table->index('bulk_id');
            $table->index('to_number');
            $table->index('from_number');
            $table->index('message_sid');
            $table->index('status');
            $table->index(['projet_id', 'status']);
            $table->index(['prospect_id', 'status']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
