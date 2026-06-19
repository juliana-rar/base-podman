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
        Schema::table('employees', function (Blueprint $table) {
            // Bio/descripció de l'empleat, mostrada al home.
            $table->text('description')->nullable()->after('works');
            // Peus de foto de les obres: mapa { ruta => caption }.
            $table->json('work_captions')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['description', 'work_captions']);
        });
    }
};
