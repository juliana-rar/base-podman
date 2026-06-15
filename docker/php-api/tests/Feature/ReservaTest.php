<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Post;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReservaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_booking_page(): void
    {
        $this->get(route('reservar'))->assertRedirect(route('login'));
    }

    public function test_user_can_view_the_booking_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('reservar'))->assertOk();
    }

    public function test_user_can_reserve_an_available_slot(): void
    {
        $user = User::factory()->create();
        $slot = Slot::factory()->create();
        $service = Service::create(['name' => 'Tall', 'price' => 10, 'duration_minutes' => 30]);
        $employee = Employee::create(['name' => 'Anna']);

        $this->actingAs($user)
            ->post(route('reservas.store'), [
                'slot_id' => $slot->id,
                'service_id' => $service->id,
                'employee_id' => $employee->id,
                'note' => 'Primera visita',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reservations', [
            'slot_id' => $slot->id,
            'user_id' => $user->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_user_cannot_reserve_an_already_booked_slot(): void
    {
        $slot = Slot::factory()->create();
        Reservation::factory()->create(['slot_id' => $slot->id]);
        $service = Service::create(['name' => 'Tall', 'price' => 10, 'duration_minutes' => 30]);
        $employee = Employee::create(['name' => 'Anna']);

        $this->actingAs(User::factory()->create())
            ->post(route('reservas.store'), [
                'slot_id' => $slot->id,
                'service_id' => $service->id,
                'employee_id' => $employee->id,
                'note' => 'Hola',
            ])
            ->assertSessionHasErrors('slot_id');

        $this->assertSame(1, Reservation::where('slot_id', $slot->id)->count());
    }

    public function test_user_can_cancel_their_own_reservation(): void
    {
        $user = User::factory()->create();
        $reservation = Reservation::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->delete(route('reservas.destroy', $reservation), ['reason' => 'No hi puc anar'])
            ->assertRedirect();

        $this->assertDatabaseMissing('reservations', ['id' => $reservation->id]);
    }

    public function test_user_cannot_cancel_someone_elses_reservation(): void
    {
        $reservation = Reservation::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('reservas.destroy', $reservation))
            ->assertForbidden();

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id]);
    }

    public function test_normal_user_cannot_access_admin_pages(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.horas'))
            ->assertForbidden();
    }

    public function test_admin_horas_page_exposes_reserved_service_duration(): void
    {
        $admin = User::factory()->admin()->create();
        $service = Service::create(['name' => 'Tall', 'price' => 10, 'duration_minutes' => 45]);
        $slot = Slot::factory()->create(['starts_at' => now()->addDay()]);
        Reservation::factory()->create(['slot_id' => $slot->id, 'service_id' => $service->id]);

        $this->actingAs($admin)
            ->get(route('admin.horas'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Horas')
                ->where('slots.0.reservation.service.duration_minutes', 45)
            );
    }

    public function test_admin_can_create_a_slot(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.horas.store'), [
                'starts_at' => now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i'),
                'notes' => 'Primera visita',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('slots', ['notes' => 'Primera visita']);
    }

    public function test_admin_can_publish_a_post(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.posts.store'), [
                'title' => 'Hola món',
                'body' => 'Primer post de prova.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'title' => 'Hola món',
            'user_id' => $admin->id,
        ]);
    }

    public function test_welcome_page_shows_published_posts(): void
    {
        Post::factory()->create(['title' => 'Novetat important']);

        $this->get(route('home'))->assertOk();
    }
}
