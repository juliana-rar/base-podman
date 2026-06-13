<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    /**
     * Pàgina d'admin: gestió dels serveis, agrupats per categoria.
     */
    public function index(): Response
    {
        return Inertia::render('admin/Serveis', [
            'categories' => ServiceCategory::with(['services' => function ($query) {
                $query->withCount('reservations')->orderBy('name');
            }])->orderBy('name')->get(['id', 'name', 'description', 'image_path']),
            'uncategorized' => Service::withCount('reservations')
                ->whereNull('service_category_id')
                ->orderBy('name')
                ->get(['id', 'name', 'price', 'duration_minutes', 'description', 'image_path', 'service_category_id']),
        ]);
    }

    /**
     * L'admin crea un servei nou (amb imatge representativa opcional).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:services,name'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'duration_minutes' => ['required', 'integer', 'min:0', 'max:100000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'service_category_id' => ['nullable', 'integer', 'exists:service_categories,id'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        Service::create([
            'name' => trim($validated['name']),
            'price' => $validated['price'],
            'duration_minutes' => $validated['duration_minutes'],
            'description' => $validated['description'] ?? null,
            'service_category_id' => $validated['service_category_id'] ?? null,
            'image_path' => $request->hasFile('image')
                ? $request->file('image')->store('services', 'public')
                : null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Servei creat.']);

        return redirect()->route('admin.serveis');
    }

    /**
     * L'admin edita el nom i/o la imatge d'un servei.
     */
    public function update(Request $request, Service $service): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:services,name,'.$service->id],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'duration_minutes' => ['required', 'integer', 'min:0', 'max:100000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'service_category_id' => ['nullable', 'integer', 'exists:service_categories,id'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $service->name = trim($validated['name']);
        $service->price = $validated['price'];
        $service->duration_minutes = $validated['duration_minutes'];
        $service->description = $validated['description'] ?? null;
        $service->service_category_id = $validated['service_category_id'] ?? null;

        if ($request->hasFile('image')) {
            if ($service->image_path) {
                Storage::disk('public')->delete($service->image_path);
            }
            $service->image_path = $request->file('image')->store('services', 'public');
        }

        $service->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Servei actualitzat.']);

        return redirect()->route('admin.serveis');
    }

    /**
     * L'admin elimina un servei.
     */
    public function destroy(Service $service): RedirectResponse
    {
        if ($service->image_path) {
            Storage::disk('public')->delete($service->image_path);
        }

        $service->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Servei eliminat.']);

        return redirect()->route('admin.serveis');
    }
}
