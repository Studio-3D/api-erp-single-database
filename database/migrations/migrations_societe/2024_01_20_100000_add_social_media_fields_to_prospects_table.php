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
        Schema::table('prospects', function (Blueprint $table) {
            if (!Schema::hasColumn('prospects', 'social_user_id')) {
                $table->string('social_user_id')->nullable()->after('telephone');
                $table->string('interaction_type')->nullable()->after('social_user_id');
                $table->string('post_id')->nullable()->after('interaction_type');
                
                $table->index(['social_user_id', 'origin']);
                $table->index(['interaction_type']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            if (Schema::hasColumn('prospects', 'social_user_id')) {
                $table->dropIndex(['social_user_id', 'origin']);
                $table->dropIndex(['interaction_type']);
                $table->dropColumn(['social_user_id', 'interaction_type', 'post_id']);
            }
        });
    }
};
