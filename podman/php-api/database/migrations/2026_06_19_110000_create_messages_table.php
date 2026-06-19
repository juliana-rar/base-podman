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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            // Fil de conversa: l'usuari (client) propietari del xat.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Qui envia: 'user' (el client), 'admin' (l'equip) o 'system' (notificacions).
            $table->string('sender');
            $table->text('body');
            // Quan l'ha llegit el destinatari (null = no llegit).
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
