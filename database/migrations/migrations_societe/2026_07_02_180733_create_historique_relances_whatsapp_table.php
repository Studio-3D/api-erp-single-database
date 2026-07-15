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
        Schema::create('historique_relances_whatsapp', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('projet_id')->constrained('projets')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('User who created/scheduled the message');

            // Store all prospect IDs in JSON array (for bulk sending)
            $table->json('prospect_ids')->nullable()->comment('Array of all prospect IDs for bulk sending');

            // Message content
            $table->text('message');

            // File attachment (single file)
            $table->string('media_url')->nullable()->after('file');
            $table->string('template_sid')->nullable();
            // Scheduling
            $table->timestamp('scheduled_date')->nullable()->comment('Date scheduled for sending');
            $table->timestamp('sent_date')->nullable()->comment('Actual date when message was sent');

            // Status
            $table->enum('status', [
                'pending',      // Waiting to be sent
                'sent',         // Successfully sent
                'failed',       // Failed to send
                'cancelled',    // Cancelled by user
                'processing'  ,  // Currently being sent
                 'partial'       // ✅ ADD THIS - Some sent, some failed
            ])->default('pending');

            // Sending details
            $table->text('response')->nullable()->comment('API response from WhatsApp');
            $table->text('error_message')->nullable()->comment('Error message if failed');

            // Additional info
            $table->json('metadata')->nullable()->comment('Additional metadata (phone numbers, etc.)');
            $table->json('statistics')->nullable()->after('metadata')->comment('Statistics: total, sent, failed, numbers');

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['projet_id', 'status']);
            $table->index(['scheduled_date', 'status']);
            $table->index(['sent_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historique_relances_whatsapp');
    }
};
