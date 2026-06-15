<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesImages;
use App\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ServiceCategoryController extends Controller
{
    use ManagesImages;

    /**
     * L'admin crea una categoria de serveis nova (amb galeria d'imatges opcional).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:service_categories,name'],
            'description' => ['nullable', 'string', 'max:2000'],
            ...$this->imageRules(),
        ]);

        $paths = $this->syncImages($request, 'service-categories');

        ServiceCategory::create([
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'image_path' => $paths[0] ?? null,
            'images' => $paths ?: null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Categoria creada.']);

        return back();
    }

    /**
     * L'admin reanomena una categoria i/o reorganitza la seva galeria d'imatges.
     */
    public function update(Request $request, ServiceCategory $serviceCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('service_categories', 'name')->ignore($serviceCategory->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            ...$this->imageRules(),
        ]);

        $current = $serviceCategory->images ?? ($serviceCategory->image_path ? [$serviceCategory->image_path] : []);
        $paths = $this->syncImages($request, 'service-categories', $current);

        $serviceCategory->name = trim($validated['name']);
        $serviceCategory->description = $validated['description'] ?? null;
        $serviceCategory->image_path = $paths[0] ?? null;
        $serviceCategory->images = $paths ?: null;
        $serviceCategory->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Categoria actualitzada.']);

        return back();
    }

    /**
     * L'admin elimina una categoria (els seus serveis queden sense categoria).
     */
    public function destroy(ServiceCategory $serviceCategory): RedirectResponse
    {
        foreach ($serviceCategory->images ?? ($serviceCategory->image_path ? [$serviceCategory->image_path] : []) as $path) {
            Storage::disk('public')->delete($path);
        }

        $serviceCategory->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Categoria eliminada.']);

        return back();
    }
}
