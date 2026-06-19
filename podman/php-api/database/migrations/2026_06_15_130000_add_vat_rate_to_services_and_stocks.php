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
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('vat_rate', 5, 2)->default(21)->after('price');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->decimal('vat_rate', 5, 2)->default(21)->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('vat_rate');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn('vat_rate');
        });
    }
};
