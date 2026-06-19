<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_admin_can_save_description_and_image_captions(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/empleats', [
            'name' => 'Dora',
            'description' => '  Especialista en color.  ',
            'images' => [UploadedFile::fake()->image('o1.png'), UploadedFile::fake()->image('o2.png')],
            'order' => json_encode(['new:0', 'new:1']),
            'captions' => json_encode(['Abans', 'Després']),
        ])->assertRedirect();

        $employee = Employee::firstOrFail();

        // La bio es retalla; les captions queden alineades a l'ordre de les obres.
        $this->assertSame('Especialista en color.', $employee->description);
        $this->assertSame(['Abans', 'Després'], $employee->captionList());
        $this->assertSame('Abans', $employee->work_captions[$employee->works[0]]);
    }

    public function test_captions_follow_images_when_reordered(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/empleats', [
            'name' => 'Elsa',
            'images' => [UploadedFile::fake()->image('o1.png'), UploadedFile::fake()->image('o2.png')],
            'order' => json_encode(['new:0', 'new:1']),
            'captions' => json_encode(['Primera', 'Segona']),
        ])->assertRedirect();

        $employee = Employee::firstOrFail();
        [$first, $second] = $employee->works;

        // Invertim l'ordre; cada caption viatja amb la seva ruta.
        $this->actingAs($admin)->post("/admin/empleats/{$employee->id}", [
            'name' => 'Elsa',
            'order' => json_encode([$second, $first]),
            'captions' => json_encode(['Segona', 'Primera']),
        ])->assertRedirect();

        $employee->refresh();

        $this->assertSame([$second, $first], $employee->works);
        $this->assertSame(['Segona', 'Primera'], $employee->captionList());
    }

    public function test_empty_caption_is_not_stored(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/empleats', [
            'name' => 'Fina',
            'images' => [UploadedFile::fake()->image('o1.png'), UploadedFile::fake()->image('o2.png')],
            'order' => json_encode(['new:0', 'new:1']),
            'captions' => json_encode(['', '  ']),
        ])->assertRedirect();

        $employee = Employee::firstOrFail();

        // Cap caption real → no es desa res al mapa, però segueix alineat (buits).
        $this->assertNull($employee->work_captions);
        $this->assertSame(['', ''], $employee->captionList());
    }

    public function test_creating_an_employee_generates_a_unique_slug(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/empleats', ['name' => 'Maria Garcia'])->assertRedirect();
        $this->actingAs($admin)->post('/admin/empleats', ['name' => 'Maria Garcia'])->assertRedirect();

        $this->assertSame('maria-garcia', Employee::where('name', 'Maria Garcia')->oldest()->first()->slug);
        $this->assertSame('maria-garcia-2', Employee::where('name', 'Maria Garcia')->latest('id')->first()->slug);
    }

    public function test_renaming_an_employee_regenerates_the_slug(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::create(['name' => 'Joana']);

        $this->actingAs($admin)->post("/admin/empleats/{$employee->id}", ['name' => 'Joana Pons'])->assertRedirect();

        $this->assertSame('joana-pons', $employee->refresh()->slug);
    }

    public function test_public_detail_page_renders_for_an_employee(): void
    {
        Storage::fake('public');
        $employee = Employee::create([
            'name' => 'Nuria',
            'description' => 'Especialista en color.',
            'works' => ['employee-works/a.png'],
            'work_captions' => ['employee-works/a.png' => 'Balaiage'],
        ]);

        $this->get("/equip/{$employee->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('EmployeeDetail')
                ->where('employee.name', 'Nuria')
                ->where('employee.description', 'Especialista en color.')
                ->has('employee.work_urls', 1)
                ->where('employee.work_captions.0', 'Balaiage')
            );
    }
}
