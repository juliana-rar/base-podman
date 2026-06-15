<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('title');
        });

        // Omplim els posts existents amb un slug únic a partir del títol.
        $used = [];
        foreach (DB::table('posts')->orderBy('id')->get() as $row) {
            $base = Str::slug($row->title) ?: 'post';
            $slug = $base;
            $i = 2;
            while (in_array($slug, $used, true)) {
                $slug = $base.'-'.$i;
                $i++;
            }
            $used[] = $slug;
            DB::table('posts')->where('id', $row->id)->update(['slug' => $slug]);
        }

        Schema::table('posts', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
