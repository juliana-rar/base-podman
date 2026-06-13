<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    /**
     * Pàgina d'admin: gestió dels serveis.
     */
    public function index(): Response
    {
        return Inertia::render('admin/Serveis', [
            'services' => Service::withCount('reservations')
                ->orderBy('name')
                ->get(['id', 'name', 'price', 'image_path']),
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
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        Service::create([
            'name' => trim($validated['name']),
            'price' => $validated['price'],
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
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $service->name = trim($validated['name']);
        $service->price = $validated['price'];

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
