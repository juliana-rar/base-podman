<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->unique(['post_id', 'tag_id']);
        });

        // Migrem les etiquetes que estaven al camp JSON cap al catàleg relacional.
        if (Schema::hasColumn('posts', 'tags')) {
            foreach (DB::table('posts')->whereNotNull('tags')->get(['id', 'tags']) as $row) {
                $names = json_decode((string) $row->tags, true) ?: [];

                foreach ($names as $name) {
                    $name = trim((string) $name);
                    if ($name === '') {
                        continue;
                    }

                    $tagId = DB::table('tags')->where('name', $name)->value('id')
                        ?? DB::table('tags')->insertGetId([
                            'name' => $name,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                    DB::table('post_tag')->insertOrIgnore([
                        'post_id' => $row->id,
                        'tag_id' => $tagId,
                    ]);
                }
            }

            Schema::table('posts', function (Blueprint $table) {
                $table->dropColumn('tags');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('images');
        });

        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('tags');
    }
};
