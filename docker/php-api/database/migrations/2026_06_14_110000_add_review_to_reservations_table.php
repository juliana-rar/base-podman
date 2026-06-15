<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Valoració de l'usuari sobre una reserva ja feta: puntuació (1-5), comentari
     * i galeria d'imatges.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable()->after('note');
            $table->text('review')->nullable()->after('rating');
            $table->json('review_images')->nullable()->after('review');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['rating', 'review', 'review_images']);
        });
    }
};
