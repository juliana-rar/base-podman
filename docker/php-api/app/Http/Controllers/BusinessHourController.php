<?php

namespace App\Http\Controllers;

use App\Models\BusinessHour;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return Inertia::render('admin/Informacio', [
            'hours' => BusinessHour::orderBy('weekday')->get(['weekday', 'closed', 'opens', 'closes']),
            'address' => Setting::get('address', ''),
            'email' => Setting::get('email', ''),
            'phone' => Setting::get('phone', ''),
            'instagram' => Setting::get('instagram', ''),
            'facebook' => Setting::get('facebook', ''),
            'linkedin' => Setting::get('linkedin', ''),
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
        ]);

        foreach ($validated['hours'] as $hour) {
            BusinessHour::where('weekday', $hour['weekday'])->update([
                'closed' => $hour['closed'],
                'opens' => $hour['closed'] ? null : ($hour['opens'] ?? null),
                'closes' => $hour['closed'] ? null : ($hour['closes'] ?? null),
            ]);
        }

        foreach (['address', 'email', 'phone', 'instagram', 'facebook', 'linkedin'] as $key) {
            Setting::put($key, $validated[$key] ?? null);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Horari actualitzat.']);

        return back();
    }
}
