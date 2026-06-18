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
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->integer('quantity')->default(0);
            $table->decimal('price', 8, 2)->default(0);
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->json('images')->nullable();
            $table->foreignId('stock_category_id')->nullable()->constrained('stock_categories')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
