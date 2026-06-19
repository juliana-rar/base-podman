<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Message;
use App\Models\Role;
use App\Models\Service;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_send_a_message(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('xat.send'), ['body' => 'Hola'])
            ->assertRedirect();

        $this->assertDatabaseHas('messages', ['user_id' => $user->id, 'sender' => 'user', 'body' => 'Hola']);
    }

    public function test_opening_chat_marks_team_messages_read(): void
    {
        $user = User::factory()->create();
        Message::create(['user_id' => $user->id, 'sender' => 'admin', 'body' => 'Bones']);

        $this->actingAs($user)->get(route('xat'))->assertOk();

        $this->assertNotNull(Message::where('user_id', $user->id)->where('sender', 'admin')->first()->read_at);
    }

    public function test_making_a_reservation_posts_a_system_message(): void
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
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $message = Message::where('user_id', $user->id)->where('sender', 'system')->first();
        $this->assertNotNull($message);
        $this->assertStringContainsString('Tall', $message->body);
    }

    public function test_admin_sees_threads_and_can_reply(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->create();
        Message::create(['user_id' => $client->id, 'sender' => 'user', 'body' => 'Hola']);

        $this->actingAs($admin)
            ->get(route('admin.xat'))
            ->assertInertia(fn (Assert $page) => $page->component('admin/Xat')->has('threads', 1));

        $this->actingAs($admin)
            ->post(route('admin.xat.send', $client), ['body' => 'Et responc'])
            ->assertRedirect();

        $this->assertDatabaseHas('messages', ['user_id' => $client->id, 'sender' => 'admin', 'body' => 'Et responc']);
    }

    public function test_admin_opening_a_thread_marks_it_read(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->create();
        Message::create(['user_id' => $client->id, 'sender' => 'user', 'body' => 'Hola']);

        $this->actingAs($admin)->get(route('admin.xat', ['user' => $client->id]))->assertOk();

        $this->assertNotNull(Message::where('user_id', $client->id)->where('sender', 'user')->first()->read_at);
    }

    public function test_staff_with_xat_screen_can_access_admin_chat(): void
    {
        $role = Role::create(['name' => 'Recepció', 'screens' => ['xat']]);
        $staff = User::factory()->create(['role' => 'client']);
        $staff->role_id = $role->id;
        $staff->save();

        $this->actingAs($staff)->get(route('admin.xat'))->assertOk();
    }

    public function test_user_without_xat_screen_cannot_access_admin_chat(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client)->get(route('admin.xat'))->assertForbidden();
    }

    public function test_unread_chat_count_is_shared_for_the_client(): void
    {
        $user = User::factory()->create();
        Message::create(['user_id' => $user->id, 'sender' => 'admin', 'body' => 'Bones']);

        $this->actingAs($user)
            ->get(route('reservar'))
            ->assertInertia(fn (Assert $page) => $page->where('unreadChat', 1));
    }
}
