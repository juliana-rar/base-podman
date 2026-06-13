<?php

namespace App\Http\Controllers;

use App\Models\ServiceOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ServiceOptionController extends Controller
{
    /**
     * L'admin afegeix una opció a un servei (amb imatge i descripció opcionals).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        ServiceOption::create([
            'service_id' => $validated['service_id'],
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'image_path' => $request->hasFile('image')
                ? $request->file('image')->store('service-options', 'public')
                : null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Opció creada.']);

        return back();
    }

    /**
     * L'admin edita el text, la descripció i/o la imatge d'una opció.
     */
    public function update(Request $request, ServiceOption $serviceOption): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $serviceOption->name = trim($validated['name']);
        $serviceOption->description = $validated['description'] ?? null;

        if ($request->hasFile('image')) {
            if ($serviceOption->image_path) {
                Storage::disk('public')->delete($serviceOption->image_path);
            }
            $serviceOption->image_path = $request->file('image')->store('service-options', 'public');
        }

        $serviceOption->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Opció actualitzada.']);

        return back();
    }

    /**
     * L'admin elimina una opció d'un servei.
     */
    public function destroy(ServiceOption $serviceOption): RedirectResponse
    {
        if ($serviceOption->image_path) {
            Storage::disk('public')->delete($serviceOption->image_path);
        }

        $serviceOption->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Opció eliminada.']);

        return back();
    }
}
