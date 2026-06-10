<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\Reservation;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. És idempotent: es pot executar a cada
     * arrencada del contenidor sense duplicar dades.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administradora',
                'role' => 'admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $user = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Usuari Normal',
                'role' => 'user',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        // Només sembrem franges i posts el primer cop.
        if (Slot::count() === 0) {
            $hours = [9, 10, 11, 12, 16, 17, 18];

            foreach ([1, 2, 3] as $dayOffset) {
                foreach ($hours as $hour) {
                    Slot::create([
                        'starts_at' => now()->addDays($dayOffset)->setTime($hour, 0),
                        'created_by' => $admin->id,
                    ]);
                }
            }

            // Una franja ja reservada per l'usuari normal (demostra l'estat).
            $firstSlot = Slot::orderBy('starts_at')->first();
            Reservation::create([
                'slot_id' => $firstSlot->id,
                'user_id' => $user->id,
            ]);
        }

        if (Post::count() === 0) {
            Post::create([
                'title' => 'Benvinguts al sistema de reserves',
                'slug' => Str::slug('Benvinguts al sistema de reserves'),
                'body' => 'Aquí pots reservar la teva hora de forma ràpida i senzilla. '
                    .'Tria un dia, escull una hora disponible al rellotge i confirma. Així de fàcil!',
                'user_id' => $admin->id,
            ]);

            Post::create([
                'title' => 'Nou horari d\'estiu',
                'slug' => Str::slug('Nou horari d\'estiu'),
                'body' => 'A partir d\'aquest mes obrim també les tardes de 16h a 19h. '
                    .'Aprofita per reservar en l\'horari que millor t\'encaixi.',
                'user_id' => $admin->id,
            ]);
        }
    }
}
