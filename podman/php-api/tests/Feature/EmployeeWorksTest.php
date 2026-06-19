<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeWorksTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_work_photos_to_an_employee(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/empleats', [
            'name' => 'Anna',
            'images' => [UploadedFile::fake()->image('o1.png'), UploadedFile::fake()->image('o2.png')],
            'order' => json_encode(['new:0', 'new:1']),
        ])->assertRedirect();

        $employee = Employee::firstOrFail();

        $this->assertCount(2, $employee->works);
        Storage::disk('public')->assertExists($employee->works[0]);
        Storage::disk('public')->assertExists($employee->works[1]);
    }

    public function test_admin_can_remove_a_work_photo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/empleats', [
            'name' => 'Berta',
            'images' => [UploadedFile::fake()->image('o1.png'), UploadedFile::fake()->image('o2.png')],
            'order' => json_encode(['new:0', 'new:1']),
        ])->assertRedirect();

        $employee = Employee::firstOrFail();
        [$kept, $removed] = $employee->works;

        // Editem deixant només la primera obra.
        $this->actingAs($admin)->post("/admin/empleats/{$employee->id}", [
            'name' => 'Berta',
            'order' => json_encode([$kept]),
        ])->assertRedirect();

        $employee->refresh();

        $this->assertSame([$kept], $employee->works);
        Storage::disk('public')->assertExists($kept);
        Storage::disk('public')->assertMissing($removed);
    }

    public function test_deleting_an_employee_removes_its_work_photos(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/empleats', [
            'name' => 'Carla',
            'images' => [UploadedFile::fake()->image('o1.png')],
            'order' => json_encode(['new:0']),
        ])->assertRedirect();

        $employee = Employee::firstOrFail();
        $work = $employee->works[0];

        $this->actingAs($admin)->delete("/admin/empleats/{$employee->id}")->assertRedirect();

        Storage::disk('public')->assertMissing($work);
    }
}
