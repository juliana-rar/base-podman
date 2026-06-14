<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Afegeix una galeria d'imatges (JSON ordenat) a categories, serveis i opcions.
     * La primera imatge fa de portada i es manté sincronitzada amb `image_path`.
     */
    public function up(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->json('images')->nullable()->after('image_path');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->json('images')->nullable()->after('image_path');
        });

        Schema::table('service_options', function (Blueprint $table) {
            $table->json('images')->nullable()->after('image_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropColumn('images');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('images');
        });

        Schema::table('service_options', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }
};
