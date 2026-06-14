<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesImages;
use App\Models\ServiceOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ServiceOptionController extends Controller
{
    use ManagesImages;

    /**
     * L'admin afegeix una opció a un servei (amb galeria d'imatges opcional).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'name' => ['required', 'string', 'max:100'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'description' => ['nullable', 'string', 'max:2000'],
            ...$this->imageRules(),
        ]);

        $paths = $this->syncImages($request, 'service-options');

        ServiceOption::create([
            'service_id' => $validated['service_id'],
            'name' => trim($validated['name']),
            'price' => $validated['price'] ?? 0,
            'duration_minutes' => $validated['duration_minutes'] ?? 0,
            'description' => $validated['description'] ?? null,
            'image_path' => $paths[0] ?? null,
            'images' => $paths ?: null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Opció creada.']);

        return back();
    }

    /**
     * L'admin edita el text, la descripció i/o reorganitza la galeria d'una opció.
     */
    public function update(Request $request, ServiceOption $serviceOption): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'description' => ['nullable', 'string', 'max:2000'],
            ...$this->imageRules(),
        ]);

        $current = $serviceOption->images ?? ($serviceOption->image_path ? [$serviceOption->image_path] : []);
        $paths = $this->syncImages($request, 'service-options', $current);

        $serviceOption->name = trim($validated['name']);
        $serviceOption->price = $validated['price'] ?? 0;
        $serviceOption->duration_minutes = $validated['duration_minutes'] ?? 0;
        $serviceOption->description = $validated['description'] ?? null;
        $serviceOption->image_path = $paths[0] ?? null;
        $serviceOption->images = $paths ?: null;
        $serviceOption->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Opció actualitzada.']);

        return back();
    }

    /**
     * L'admin elimina una opció d'un servei.
     */
    public function destroy(ServiceOption $serviceOption): RedirectResponse
    {
        foreach ($serviceOption->images ?? ($serviceOption->image_path ? [$serviceOption->image_path] : []) as $path) {
            Storage::disk('public')->delete($path);
        }

        $serviceOption->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Opció eliminada.']);

        return back();
    }
}
