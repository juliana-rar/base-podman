<?php

namespace Tests\Feature;

use App\Models\BusinessHour;
use App\Models\Employee;
use App\Models\Post;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceOption;
use App\Models\Slot;
use App\Models\Stock;
use App\Models\Tag;
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

    /**
     * Configura el dia de l'hora indicada com a obert (08:00–21:00) i la retorna.
     */
    private function openTime(): \Carbon\CarbonInterface
    {
        $when = now()->addDay()->setTime(10, 0);

        BusinessHour::updateOrCreate(
            ['weekday' => $when->dayOfWeekIso - 1],
            ['closed' => false, 'opens' => '08:00', 'closes' => '21:00'],
        );

        return $when;
    }

    public function test_user_can_reserve_a_free_time_within_business_hours(): void
    {
        $user = User::factory()->create();
        $when = $this->openTime();
        $service = Service::create(['name' => 'Tall', 'price' => 10, 'duration_minutes' => 30]);
        $employee = Employee::create(['name' => 'Anna']);

        $this->actingAs($user)
            ->post(route('reservas.store'), [
                'starts_at' => $when->format('Y-m-d H:i'),
                'service_id' => $service->id,
                'employee_id' => $employee->id,
                'note' => 'Primera visita',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // S'ha creat la franja a l'hora triada i la reserva associada.
        $this->assertDatabaseHas('slots', ['starts_at' => $when->format('Y-m-d H:i:s')]);
        $this->assertDatabaseHas('reservations', [
            'user_id' => $user->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_user_can_reserve_without_a_note(): void
    {
        $user = User::factory()->create();
        $when = $this->openTime();
        $service = Service::create(['name' => 'Tall', 'price' => 10, 'duration_minutes' => 30]);
        $employee = Employee::create(['name' => 'Anna']);

        $this->actingAs($user)
            ->post(route('reservas.store'), [
                'starts_at' => $when->format('Y-m-d H:i'),
                'service_id' => $service->id,
                'employee_id' => $employee->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('reservations', [
            'user_id' => $user->id,
            'note' => null,
        ]);
    }

    public function test_user_can_reserve_with_optional_products(): void
    {
        $user = User::factory()->create();
        $when = $this->openTime();
        $service = Service::create(['name' => 'Tall', 'price' => 10, 'duration_minutes' => 30]);
        $employee = Employee::create(['name' => 'Anna']);
        $product = Stock::create(['name' => 'Xampú', 'quantity' => 5, 'price' => 8.50]);

        $this->actingAs($user)
            ->post(route('reservas.store'), [
                'starts_at' => $when->format('Y-m-d H:i'),
                'service_id' => $service->id,
                'employee_id' => $employee->id,
                'products' => [
                    ['stock_id' => $product->id, 'quantity' => 2],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('reservation_stock', [
            'stock_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_reserved_product_quantity_is_capped_at_available_stock(): void
    {
        $user = User::factory()->create();
        $when = $this->openTime();
        $service = Service::create(['name' => 'Tall', 'price' => 10, 'duration_minutes' => 30]);
        $employee = Employee::create(['name' => 'Anna']);
        $product = Stock::create(['name' => 'Crema', 'quantity' => 3, 'price' => 12]);

        $this->actingAs($user)
            ->post(route('reservas.store'), [
                'starts_at' => $when->format('Y-m-d H:i'),
                'service_id' => $service->id,
                'employee_id' => $employee->id,
                'products' => [
                    ['stock_id' => $product->id, 'quantity' => 10],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('reservation_stock', [
            'stock_id' => $product->id,
            'quantity' => 3,
        ]);
    }

    public function test_user_cannot_reserve_an_already_booked_time(): void
    {
        $when = $this->openTime();
        $slot = Slot::factory()->create(['starts_at' => $when]);
        Reservation::factory()->create(['slot_id' => $slot->id]);
        $service = Service::create(['name' => 'Tall', 'price' => 10, 'duration_minutes' => 30]);
        $employee = Employee::create(['name' => 'Anna']);

        $this->actingAs(User::factory()->create())
            ->post(route('reservas.store'), [
                'starts_at' => $when->format('Y-m-d H:i'),
                'service_id' => $service->id,
                'employee_id' => $employee->id,
                'note' => 'Hola',
            ])
            ->assertSessionHasErrors('starts_at');

        $this->assertSame(1, Reservation::where('slot_id', $slot->id)->count());
    }

    public function test_user_cannot_reserve_on_a_closed_day(): void
    {
        $user = User::factory()->create();
        $service = Service::create(['name' => 'Tall', 'price' => 10, 'duration_minutes' => 30]);
        $employee = Employee::create(['name' => 'Anna']);
        $when = now()->addDay()->setTime(10, 0);
        BusinessHour::updateOrCreate(
            ['weekday' => $when->dayOfWeekIso - 1],
            ['closed' => true, 'opens' => null, 'closes' => null],
        );

        $this->actingAs($user)
            ->post(route('reservas.store'), [
                'starts_at' => $when->format('Y-m-d H:i'),
                'service_id' => $service->id,
                'employee_id' => $employee->id,
            ])
            ->assertSessionHasErrors('starts_at');

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_user_cannot_reserve_outside_business_hours(): void
    {
        $user = User::factory()->create();
        $service = Service::create(['name' => 'Tall', 'price' => 10, 'duration_minutes' => 30]);
        $employee = Employee::create(['name' => 'Anna']);
        $when = now()->addDay()->setTime(14, 0);
        BusinessHour::updateOrCreate(
            ['weekday' => $when->dayOfWeekIso - 1],
            ['closed' => false, 'opens' => '09:00', 'closes' => '12:00'],
        );

        $this->actingAs($user)
            ->post(route('reservas.store'), [
                'starts_at' => $when->format('Y-m-d H:i'),
                'service_id' => $service->id,
                'employee_id' => $employee->id,
            ])
            ->assertSessionHasErrors('starts_at');

        $this->assertDatabaseCount('reservations', 0);
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

    public function test_admin_posts_page_lists_tag_catalog_with_post_counts(): void
    {
        $admin = User::factory()->admin()->create();
        $tag = Tag::create(['name' => 'Novetats', 'color' => '#ff0000']);
        Post::factory()->create()->tags()->attach($tag);

        $this->actingAs($admin)
            ->get(route('admin.posts'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Posts')
                ->where('allTags.0.name', 'Novetats')
                ->where('allTags.0.posts_count', 1)
            );
    }

    public function test_admin_can_create_a_tag_from_posts(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.tags.store'), ['name' => 'Oferta', 'color' => '#00ff00'])
            ->assertRedirect();

        $this->assertDatabaseHas('tags', ['name' => 'Oferta', 'color' => '#00ff00']);
    }

    public function test_admin_history_exposes_service_price_for_billing(): void
    {
        $admin = User::factory()->admin()->create();
        $service = Service::create(['name' => 'Tall', 'price' => 25, 'duration_minutes' => 30]);
        $slot = Slot::factory()->create();
        Reservation::factory()->create(['slot_id' => $slot->id, 'service_id' => $service->id]);

        $this->actingAs($admin)
            ->get(route('admin.reserves'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Historial')
                ->where('reservations.0.service.price', '25.00')
            );
    }

    public function test_admin_history_exposes_service_category_and_option_for_excel(): void
    {
        $admin = User::factory()->admin()->create();
        $category = ServiceCategory::create(['name' => 'Perruqueria']);
        $service = Service::create([
            'name' => 'Tall',
            'price' => 25,
            'duration_minutes' => 30,
            'service_category_id' => $category->id,
        ]);
        $option = ServiceOption::create(['service_id' => $service->id, 'name' => 'Amb rentat', 'price' => 30]);
        $slot = Slot::factory()->create();
        Reservation::factory()->create([
            'slot_id' => $slot->id,
            'service_id' => $service->id,
            'service_option_id' => $option->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reserves'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Historial')
                ->where('reservations.0.service.category.name', 'Perruqueria')
                ->where('reservations.0.service_option.name', 'Amb rentat')
            );
    }

    public function test_admin_history_exposes_vat_rates_for_excel(): void
    {
        $admin = User::factory()->admin()->create();
        $service = Service::create(['name' => 'Tall', 'price' => 10, 'duration_minutes' => 30, 'vat_rate' => 10]);
        $product = Stock::create(['name' => 'Xampú', 'quantity' => 5, 'price' => 8, 'vat_rate' => 21]);
        $slot = Slot::factory()->create();
        $reservation = Reservation::factory()->create(['slot_id' => $slot->id, 'service_id' => $service->id]);
        $reservation->stocks()->attach($product->id, ['quantity' => 1]);

        $this->actingAs($admin)
            ->get(route('admin.reserves'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('reservations.0.service.vat_rate', '10.00')
                ->where('reservations.0.stocks.0.vat_rate', '21.00')
            );
    }

    public function test_admin_can_edit_a_reservation_service_employee_and_note(): void
    {
        $admin = User::factory()->admin()->create();
        $oldService = Service::create(['name' => 'Tall', 'price' => 10, 'duration_minutes' => 30]);
        $newService = Service::create(['name' => 'Tint', 'price' => 40, 'duration_minutes' => 60]);
        $oldEmployee = Employee::create(['name' => 'Anna']);
        $newEmployee = Employee::create(['name' => 'Berta']);
        $reservation = Reservation::factory()->create([
            'service_id' => $oldService->id,
            'employee_id' => $oldEmployee->id,
            'note' => 'Nota original',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.reserves.update', $reservation), [
                'service_id' => $newService->id,
                'employee_id' => $newEmployee->id,
                'note' => 'Nota actualitzada',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'service_id' => $newService->id,
            'employee_id' => $newEmployee->id,
            'note' => 'Nota actualitzada',
        ]);
    }

    public function test_admin_can_edit_reservation_products(): void
    {
        $admin = User::factory()->admin()->create();
        $service = Service::create(['name' => 'Tall', 'price' => 10, 'duration_minutes' => 30]);
        $employee = Employee::create(['name' => 'Anna']);
        $oldProduct = Stock::create(['name' => 'Xampú', 'quantity' => 5, 'price' => 8]);
        $newProduct = Stock::create(['name' => 'Crema', 'quantity' => 4, 'price' => 12]);
        $reservation = Reservation::factory()->create([
            'service_id' => $service->id,
            'employee_id' => $employee->id,
        ]);
        $reservation->stocks()->attach($oldProduct->id, ['quantity' => 1]);

        $this->actingAs($admin)
            ->put(route('admin.reserves.update', $reservation), [
                'service_id' => $service->id,
                'employee_id' => $employee->id,
                'note' => 'Amb productes',
                'products' => [
                    ['stock_id' => $newProduct->id, 'quantity' => 3],
                ],
            ])
            ->assertRedirect();

        // El producte antic es treu i s'hi posa el nou amb la quantitat indicada.
        $this->assertDatabaseMissing('reservation_stock', [
            'reservation_id' => $reservation->id,
            'stock_id' => $oldProduct->id,
        ]);
        $this->assertDatabaseHas('reservation_stock', [
            'reservation_id' => $reservation->id,
            'stock_id' => $newProduct->id,
            'quantity' => 3,
        ]);
    }

    public function test_normal_user_cannot_edit_a_reservation(): void
    {
        $service = Service::create(['name' => 'Tall', 'price' => 10, 'duration_minutes' => 30]);
        $employee = Employee::create(['name' => 'Anna']);
        $reservation = Reservation::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('admin.reserves.update', $reservation), [
                'service_id' => $service->id,
                'employee_id' => $employee->id,
                'note' => 'Intent no autoritzat',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('reservations', [
            'id' => $reservation->id,
            'note' => 'Intent no autoritzat',
        ]);
    }

    public function test_admin_editing_a_reservation_requires_valid_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $reservation = Reservation::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.reserves.update', $reservation), [
                'service_id' => 999999,
                'employee_id' => null,
                'note' => '',
            ])
            ->assertSessionHasErrors(['service_id', 'employee_id', 'note']);
    }
}
