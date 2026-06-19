<?php

use App\Models\Employee;
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
            // Slug per a la pàgina pública de detall (/equip/{slug}).
            $table->string('slug')->nullable()->unique()->after('name');
        });

        // Generem un slug únic per als empleats ja existents.
        Employee::query()->whereNull('slug')->get(['id', 'name'])
            ->each(fn (Employee $employee) => $employee->update([
                'slug' => Employee::uniqueSlug((string) $employee->name, $employee->id),
            ]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
