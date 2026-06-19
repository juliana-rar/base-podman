<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InformacioTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Set d'horaris vàlid (7 dies) per passar la validació de l'update.
     *
     * @return array<int, array{weekday: int, closed: bool, opens: string, closes: string}>
     */
    private function defaultHours(): array
    {
        return array_map(fn (int $weekday): array => [
            'weekday' => $weekday,
            'closed' => false,
            'opens' => '09:00',
            'closes' => '20:00',
        ], range(0, 6));
    }

    public function test_admin_can_save_company_fiscal_data(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.informacio.update'), [
                'hours' => $this->defaultHours(),
                'legal_name' => 'La Meva Corona SL',
                'tax_id' => 'B12345678',
                'fiscal_address' => 'Carrer Major 1, Barcelona',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('La Meva Corona SL', Setting::get('legal_name'));
        $this->assertSame('B12345678', Setting::get('tax_id'));
        $this->assertSame('Carrer Major 1, Barcelona', Setting::get('fiscal_address'));
    }

    public function test_informacio_page_exposes_saved_fiscal_data(): void
    {
        $admin = User::factory()->admin()->create();
        Setting::put('legal_name', 'La Meva Corona SL');
        Setting::put('tax_id', 'B12345678');

        $this->actingAs($admin)
            ->get(route('admin.informacio'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Informacio')
                ->where('legalName', 'La Meva Corona SL')
                ->where('taxId', 'B12345678')
            );
    }

    public function test_fiscal_data_is_shared_globally_for_the_excel(): void
    {
        $admin = User::factory()->admin()->create();
        Setting::put('tax_id', 'B99999999');

        $this->actingAs($admin)
            ->get(route('admin.reserves'))
            ->assertInertia(fn (Assert $page) => $page->where('fiscal.taxId', 'B99999999'));
    }

    public function test_non_admins_cannot_update_information(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('admin.informacio.update'), [
                'hours' => $this->defaultHours(),
                'legal_name' => 'Hack SL',
            ])
            ->assertForbidden();

        $this->assertNull(Setting::get('legal_name'));
    }
}
