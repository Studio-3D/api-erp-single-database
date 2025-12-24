<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enum\RoleEnum;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            //text
            $table->string('lien');
            $table->dateTime('date')->nullable();;
            $table->bigInteger('type');
            $table->string('description_type');
            $table->enum('role',[RoleEnum::SUPERADMIN->value,RoleEnum::ADMIN->value,RoleEnum::COMMERCIAL->value,RoleEnum::ADMIN_COMMERCIAL])->nullable()->comment('1=>superamin 2=>admin 3=>commercial');
            $table->foreignId('user_id')->nullable()->references('user_id_origin')->on('users')->onDelete('cascade');
            $table->foreignId('visite_id')->nullable()->constrained('visites')->onDelete('cascade');
            $table->foreignId('projet_id')->nullable()->constrained('projets')->onDelete('cascade');
            $table->foreignId('prospect_id')->nullable()->constrained('prospects')->onDelete('cascade');
            $table->foreignId('avance_id')->nullable()->constrained('avances')->onDelete('cascade');
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->onDelete('cascade');
            $table->foreignId('bien_id')->nullable()->constrained('biens')->onDelete('cascade');
            $table->foreignId('traite_appel_id')->nullable()->constrained('traitements_appels')->onDelete('cascade');
            $table->json('seen')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
