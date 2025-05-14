<?php

use App\Enum\OrientationEnum;
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
        Schema::create('frein_orientations', function (Blueprint $table)
        {

            $table->id();
            $table->enum('orientation',[OrientationEnum::N->name,OrientationEnum::E->name,OrientationEnum::S->name,OrientationEnum::O->name,OrientationEnum::N_E->name,OrientationEnum::N_O->name,OrientationEnum::S_E->name,OrientationEnum::S_O->name]);
            $table->foreignId('frein_id')->constrained('freins')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('frein_orientations');
    }
};
