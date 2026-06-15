<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_review_a_past_reservation_with_images(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $slot = Slot::factory()->create(['starts_at' => now()->subDay()]);
        $reservation = Reservation::factory()->create(['user_id' => $user->id, 'slot_id' => $slot->id]);

        $this->actingAs($user)
            ->post(route('reservas.review', $reservation), [
                'rating' => 4,
                'review' => 'Molt bé',
                'images' => [UploadedFile::fake()->image('a.png')],
                'order' => json_encode(['new:0']),
            ])
            ->assertRedirect();

        $reservation->refresh();

        $this->assertSame(4, $reservation->rating);
        $this->assertSame('Molt bé', $reservation->review);
        $this->assertCount(1, $reservation->review_images);
        Storage::disk('public')->assertExists($reservation->review_images[0]);
    }

    public function test_user_cannot_review_a_future_reservation(): void
    {
        $user = User::factory()->create();
        $slot = Slot::factory()->create(['starts_at' => now()->addDay()]);
        $reservation = Reservation::factory()->create(['user_id' => $user->id, 'slot_id' => $slot->id]);

        $this->actingAs($user)
            ->post(route('reservas.review', $reservation), ['rating' => 5])
            ->assertSessionHasErrors('rating');

        $this->assertNull($reservation->refresh()->rating);
    }

    public function test_user_cannot_review_someone_elses_reservation(): void
    {
        $slot = Slot::factory()->create(['starts_at' => now()->subDay()]);
        $reservation = Reservation::factory()->create(['slot_id' => $slot->id]);

        $this->actingAs(User::factory()->create())
            ->post(route('reservas.review', $reservation), ['rating' => 3])
            ->assertForbidden();
    }

    public function test_admin_reviews_list_is_paginated_ten_per_page(): void
    {
        $admin = User::factory()->admin()->create();

        Reservation::factory()->count(11)->create(['rating' => 5]);

        $this->actingAs($admin)
            ->get(route('reserves-admin'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/ReservesAdmin')
                ->has('reviews.data', 10)
                ->where('reviews.current_page', 1)
                ->where('reviews.last_page', 2)
                ->where('reviews.total', 11)
            );

        $this->actingAs($admin)
            ->get(route('reserves-admin', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('reviews.data', 1)
                ->where('reviews.current_page', 2)
            );
    }
}
