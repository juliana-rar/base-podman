<?php

namespace App\Http\Controllers;

use App\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ServiceCategoryController extends Controller
{
    /**
     * L'admin crea una categoria de serveis nova (amb imatge opcional).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:service_categories,name'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        ServiceCategory::create([
            'name' => trim($validated['name']),
            'image_path' => $request->hasFile('image')
                ? $request->file('image')->store('service-categories', 'public')
                : null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Categoria creada.']);

        return back();
    }

    /**
     * L'admin reanomena una categoria de serveis i/o canvia la seva imatge.
     */
    public function update(Request $request, ServiceCategory $serviceCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('service_categories', 'name')->ignore($serviceCategory->id)],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $serviceCategory->name = trim($validated['name']);

        if ($request->hasFile('image')) {
            if ($serviceCategory->image_path) {
                Storage::disk('public')->delete($serviceCategory->image_path);
            }
            $serviceCategory->image_path = $request->file('image')->store('service-categories', 'public');
        }

        $serviceCategory->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Categoria actualitzada.']);

        return back();
    }

    /**
     * L'admin elimina una categoria (els seus serveis queden sense categoria).
     */
    public function destroy(ServiceCategory $serviceCategory): RedirectResponse
    {
        if ($serviceCategory->image_path) {
            Storage::disk('public')->delete($serviceCategory->image_path);
        }

        $serviceCategory->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Categoria eliminada.']);

        return back();
    }
}
