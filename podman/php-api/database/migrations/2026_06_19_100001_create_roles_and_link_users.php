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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // Pantalles del dashboard que aquest rol pot veure (claus de pantalla).
            $table->json('screens')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            // Rol de personal (atorga pantalles concretes). Els admins ho veuen tot.
            $table->foreignId('role_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('roles');
    }
};
