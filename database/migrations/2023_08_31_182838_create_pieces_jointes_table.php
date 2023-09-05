<?php

use App\Enum\TypePJ;
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
        Schema::create('pieces_jointes', function (Blueprint $table) {
            $table->id();
            $table->string('fichier');
            $table->enum('type',[TypePJ::PDF->name,TypePJ::WORD->name,TypePJ::PNG->name,TypePJ::JPEG->name,TypePJ::JPG->name,TypePJ::GIF->name,TypePJ::EXCEL->name]);
            $table->foreignId('reservation_id')->constrained('reservations')->onDelete('cascade');
            $table->foreignId('avance_id')->constrained('avances')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pieces_jointes');
    }
};
