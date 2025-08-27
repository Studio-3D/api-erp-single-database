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
        Schema::create('bien_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bien_id');
            $table->string('file_path');
            $table->string('file_type');
            $table->string('mime_type');
            $table->string('original_name');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('bien_id')->references('id')->on('biens')->onDelete('cascade');
            $table->index('bien_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bien_media');
    }
};

