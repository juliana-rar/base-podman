<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServiceImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_category_with_multiple_images(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/serveis-categories', [
                'name' => 'Perruqueria',
                'images' => [
                    UploadedFile::fake()->image('a.png'),
                    UploadedFile::fake()->image('b.png'),
                ],
                'order' => json_encode(['new:0', 'new:1']),
            ])
            ->assertRedirect();

        $category = ServiceCategory::firstOrFail();

        $this->assertCount(2, $category->images);
        $this->assertSame($category->images[0], $category->image_path);
        Storage::disk('public')->assertExists($category->images[0]);
        Storage::disk('public')->assertExists($category->images[1]);
    }

    public function test_admin_can_reorder_add_and_remove_service_images(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        // Creem un servei amb dues imatges.
        $this->actingAs($admin)->post('/admin/serveis', [
            'name' => 'Tall',
            'price' => 10,
            'duration_minutes' => 30,
            'images' => [UploadedFile::fake()->image('1.png'), UploadedFile::fake()->image('2.png')],
            'order' => json_encode(['new:0', 'new:1']),
        ])->assertRedirect();

        $service = Service::firstOrFail();
        [$first, $second] = $service->images;

        // La segona passa a portada, traiem la primera i n'afegim una de nova al final.
        $this->actingAs($admin)->post("/admin/serveis/{$service->id}", [
            'name' => 'Tall',
            'price' => 10,
            'duration_minutes' => 30,
            'images' => [UploadedFile::fake()->image('3.png')],
            'order' => json_encode([$second, 'new:0']),
        ])->assertRedirect();

        $service->refresh();

        $this->assertCount(2, $service->images);
        $this->assertSame($second, $service->images[0]);
        $this->assertSame($second, $service->image_path);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
        Storage::disk('public')->assertExists($service->images[1]);
    }

    public function test_admin_can_add_images_to_a_service_option(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $service = Service::create(['name' => 'Servei', 'price' => 0, 'duration_minutes' => 0]);

        $this->actingAs($admin)->post('/admin/serveis-options', [
            'service_id' => $service->id,
            'name' => 'Opció',
            'images' => [UploadedFile::fake()->image('o1.png'), UploadedFile::fake()->image('o2.png')],
            'order' => json_encode(['new:0', 'new:1']),
        ])->assertRedirect();

        $option = ServiceOption::firstOrFail();

        $this->assertCount(2, $option->images);
        Storage::disk('public')->assertExists($option->images[0]);
        Storage::disk('public')->assertExists($option->images[1]);
    }

    public function test_image_urls_fall_back_to_a_legacy_single_image(): void
    {
        // Registre antic: només `image_path`, sense galeria nova.
        $category = ServiceCategory::create([
            'name' => 'Antiga',
            'image_path' => 'service-categories/old.jpg',
        ]);

        $this->assertCount(1, $category->image_urls);
        $this->assertStringContainsString('old.jpg', $category->image_urls[0]);
    }

    public function test_admin_can_remove_all_images_from_a_service(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/serveis', [
            'name' => 'Massatge',
            'price' => 20,
            'duration_minutes' => 60,
            'images' => [UploadedFile::fake()->image('m.png')],
            'order' => json_encode(['new:0']),
        ])->assertRedirect();

        $service = Service::firstOrFail();
        $stored = $service->images[0];

        // Desem sense cap imatge a `order`: la galeria queda buida.
        $this->actingAs($admin)->post("/admin/serveis/{$service->id}", [
            'name' => 'Massatge',
            'price' => 20,
            'duration_minutes' => 60,
            'order' => json_encode([]),
        ])->assertRedirect();

        $service->refresh();

        $this->assertNull($service->images);
        $this->assertNull($service->image_path);
        Storage::disk('public')->assertMissing($stored);
    }
}
