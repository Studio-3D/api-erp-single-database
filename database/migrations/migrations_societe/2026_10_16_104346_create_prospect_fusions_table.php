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
        Schema::create('prospect_fusions', function (Blueprint $table) {
            $table->id();

            // ⭐ Prospect principal (Malika - li kayb9a)
            $table->unsignedBigInteger('prospect_principal_id')
                ->comment('Prospect principal (li kayb9a)');

            // ⭐ Prospect fusionné (Omar - li mdemj)
            $table->unsignedBigInteger('prospect_fusion_id')
                ->comment('Prospect fusionné (li mdemj)');

            // ⭐ Chkoun dar demj
            $table->unsignedBigInteger('user_id')
                ->comment('User li dar fusion');

            // ⭐ Timestamps
            $table->timestamps();
            $table->softDeletes();

            // ⭐ Indexes
            $table->index('prospect_principal_id', 'idx_fusion_principal');
            $table->index('prospect_fusion_id', 'idx_fusion_fusionne');
            $table->index('user_id', 'idx_fusion_user');

            // ⭐ Foreign Keys
            $table->foreign('prospect_principal_id', 'fk_fusion_principal')
                ->references('id')
                ->on('prospects')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('prospect_fusion_id', 'fk_fusion_fusionne')
                ->references('id')
                ->on('prospects')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('user_id', 'fk_fusion_user')
                ->references('id')
                ->on('users')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospect_fusions');
    }
};
