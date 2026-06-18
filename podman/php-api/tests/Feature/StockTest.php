<?php

namespace Tests\Feature;

use App\Models\Stock;
use App\Models\StockCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_the_stock_page(): void
    {
        $this->get('/admin/stock')->assertRedirect(route('login'));
    }

    public function test_non_admins_cannot_access_the_stock_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/stock')
            ->assertForbidden();
    }

    public function test_admin_can_view_the_stock_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/stock')
            ->assertOk();
    }

    public function test_admin_can_create_a_stock_category_with_images(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/stock-categories', [
                'name' => 'Material',
                'description' => 'Productes de consum',
                'images' => [
                    UploadedFile::fake()->image('a.png'),
                    UploadedFile::fake()->image('b.png'),
                ],
                'order' => json_encode(['new:0', 'new:1']),
            ])
            ->assertRedirect();

        $category = StockCategory::firstOrFail();

        $this->assertSame('Material', $category->name);
        $this->assertCount(2, $category->images);
        $this->assertSame($category->images[0], $category->image_path);
        Storage::disk('public')->assertExists($category->images[0]);
        Storage::disk('public')->assertExists($category->images[1]);
    }

    public function test_admin_can_create_a_stock_item_in_a_category(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $category = StockCategory::create(['name' => 'Material']);

        $this->actingAs($admin)
            ->post('/admin/stock', [
                'name' => 'Xampú',
                'quantity' => 12,
                'price' => 8.5,
                'description' => 'Ampolla de 500 ml',
                'stock_category_id' => $category->id,
                'images' => [UploadedFile::fake()->image('x.png')],
                'order' => json_encode(['new:0']),
            ])
            ->assertRedirect(route('admin.stock'));

        $stock = Stock::firstOrFail();

        $this->assertSame('Xampú', $stock->name);
        $this->assertSame(12, $stock->quantity);
        $this->assertSame('8.50', $stock->price);
        $this->assertSame($category->id, $stock->stock_category_id);
        $this->assertCount(1, $stock->images);
        Storage::disk('public')->assertExists($stock->images[0]);
    }

    public function test_stock_name_is_required_and_unique(): void
    {
        $admin = User::factory()->admin()->create();
        Stock::create(['name' => 'Xampú', 'quantity' => 1, 'price' => 1]);

        $this->actingAs($admin)
            ->post('/admin/stock', ['name' => '', 'quantity' => 1, 'price' => 1])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->post('/admin/stock', ['name' => 'Xampú', 'quantity' => 1, 'price' => 1])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Stock::where('name', 'Xampú')->count());
    }

    public function test_admin_can_update_a_stock_item(): void
    {
        $admin = User::factory()->admin()->create();
        $stock = Stock::create(['name' => 'Xampú', 'quantity' => 5, 'price' => 4]);

        $this->actingAs($admin)
            ->post("/admin/stock/{$stock->id}", [
                'name' => 'Xampú gran',
                'quantity' => 20,
                'price' => 9.99,
                'order' => json_encode([]),
            ])
            ->assertRedirect(route('admin.stock'));

        $stock->refresh();

        $this->assertSame('Xampú gran', $stock->name);
        $this->assertSame(20, $stock->quantity);
        $this->assertSame('9.99', $stock->price);
    }

    public function test_deleting_a_category_keeps_its_items_without_category(): void
    {
        $admin = User::factory()->admin()->create();
        $category = StockCategory::create(['name' => 'Material']);
        $stock = Stock::create(['name' => 'Xampú', 'quantity' => 1, 'price' => 1, 'stock_category_id' => $category->id]);

        $this->actingAs($admin)
            ->delete("/admin/stock-categories/{$category->id}")
            ->assertRedirect();

        $stock->refresh();

        $this->assertDatabaseMissing('stock_categories', ['id' => $category->id]);
        $this->assertNull($stock->stock_category_id);
    }

    public function test_admin_can_delete_a_stock_item_and_its_images(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/stock', [
            'name' => 'Tovalloles',
            'quantity' => 3,
            'price' => 2,
            'images' => [UploadedFile::fake()->image('t.png')],
            'order' => json_encode(['new:0']),
        ])->assertRedirect();

        $stock = Stock::firstOrFail();
        $stored = $stock->images[0];

        $this->actingAs($admin)
            ->delete("/admin/stock/{$stock->id}")
            ->assertRedirect(route('admin.stock'));

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
        Storage::disk('public')->assertMissing($stored);
    }
}
