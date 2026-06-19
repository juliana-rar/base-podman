<?php

namespace App\Http\Controllers;

use App\Models\BusinessHour;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BusinessHourController extends Controller
{
    /**
     * Garanteix que existeixin les 7 files (una per dia de la setmana).
     */
    private function ensureRows(): void
    {
        for ($weekday = 0; $weekday < 7; $weekday++) {
            BusinessHour::firstOrCreate(
                ['weekday' => $weekday],
                [
                    'closed' => $weekday >= 5,
                    'opens' => $weekday < 5 ? '09:00' : null,
                    'closes' => $weekday < 5 ? '20:00' : null,
                ],
            );
        }
    }

    /**
     * Pàgina d'admin: editar l'horari d'atenció per dia de la setmana.
     */
    public function index(): Response
    {
        $this->ensureRows();

        $logo = Setting::get('logo');

        return Inertia::render('admin/Informacio', [
            'hours' => BusinessHour::orderBy('weekday')->get(['weekday', 'closed', 'opens', 'closes']),
            'address' => Setting::get('address', ''),
            'email' => Setting::get('email', ''),
            'phone' => Setting::get('phone', ''),
            'instagram' => Setting::get('instagram', ''),
            'facebook' => Setting::get('facebook', ''),
            'linkedin' => Setting::get('linkedin', ''),
            'siteName' => Setting::get('site_name', 'ReservaHores'),
            'logoUrl' => $logo ? Storage::url($logo) : null,
            'legalName' => Setting::get('legal_name', ''),
            'taxId' => Setting::get('tax_id', ''),
            'fiscalAddress' => Setting::get('fiscal_address', ''),
        ]);
    }

    /**
     * L'admin desa l'horari de tots els dies.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hours' => ['required', 'array', 'size:7'],
            'hours.*.weekday' => ['required', 'integer', 'between:0,6'],
            'hours.*.closed' => ['required', 'boolean'],
            'hours.*.opens' => ['nullable', 'date_format:H:i'],
            'hours.*.closes' => ['nullable', 'date_format:H:i'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'linkedin' => ['nullable', 'string', 'max:255'],
            'site_name' => ['nullable', 'string', 'max:100'],
            'legal_name' => ['nullable', 'string', 'max:150'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'fiscal_address' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'removeLogo' => ['nullable', 'boolean'],
        ]);

        foreach ($validated['hours'] as $hour) {
            BusinessHour::where('weekday', $hour['weekday'])->update([
                'closed' => $hour['closed'],
                'opens' => $hour['closed'] ? null : ($hour['opens'] ?? null),
                'closes' => $hour['closed'] ? null : ($hour['closes'] ?? null),
            ]);
        }

        foreach (['address', 'email', 'phone', 'instagram', 'facebook', 'linkedin', 'site_name', 'legal_name', 'tax_id', 'fiscal_address'] as $key) {
            Setting::put($key, $validated[$key] ?? null);
        }

        // Logo: substitueix si se'n puja un de nou; elimina si es demana.
        if ($request->hasFile('logo')) {
            if ($old = Setting::get('logo')) {
                Storage::disk('public')->delete($old);
            }
            Setting::put('logo', $request->file('logo')->store('site', 'public'));
        } elseif ($request->boolean('removeLogo')) {
            if ($old = Setting::get('logo')) {
                Storage::disk('public')->delete($old);
            }
            Setting::put('logo', null);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Informació actualitzada.']);

        return back();
    }
}
